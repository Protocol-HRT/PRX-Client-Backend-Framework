<?php

namespace Tests\Feature;

use App\Actions\Referral\AttributeLeadAction;
use App\Actions\Referral\RecordReferralClickAction;
use App\Enums\Payments\LeadPaymentStatus;
use App\Models\Lead;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use App\Services\Referral\ReferralMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The operator's own scenario, built literally:
 *
 *   Main Org 1
 *   ├── Main Org 2 ── 3 referral partners
 *   └── Main Org 3 ── 2 referral partners
 *
 * Org 1 sees everything, Org 2 sees only its three, Org 3 only its two.
 */
class ReferralMetricsTest extends TestCase
{
    use RefreshDatabase;

    private ReferralSource $org1;

    private ReferralSource $org2;

    private ReferralSource $org3;

    /** @var array<string, ReferralSource> */
    private array $partners = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->org1 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 1']);
        $this->org2 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 2', 'parent_id' => $this->org1->id]);
        $this->org3 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 3', 'parent_id' => $this->org1->id]);

        foreach ([['o2', $this->org2, 3], ['o3', $this->org3, 2]] as [$tag, $parent, $count]) {
            for ($i = 1; $i <= $count; $i++) {
                $partner = ReferralSource::factory()->create([
                    'name' => "{$tag}-partner-{$i}",
                    'parent_id' => $parent->id,
                ]);
                ReferralLink::factory()->for($partner, 'source')->create(['code' => "{$tag}-p{$i}"]);
                $this->partners["{$tag}-{$i}"] = $partner;
            }
        }
    }

    private function metrics(): ReferralMetrics
    {
        return app(ReferralMetrics::class);
    }

    private function click(string $code, ?string $visitor = null): string
    {
        $visitor ??= (string) Str::uuid();
        app(RecordReferralClickAction::class)->execute($code, $visitor);

        return $visitor;
    }

    private function convert(string $code, string $visitor, ?float $amount = null): Lead
    {
        $lead = Lead::factory()->create();
        app(AttributeLeadAction::class)->execute($lead, $code, $visitor);

        if ($amount !== null) {
            $lead->forceFill([
                'payment_status' => LeadPaymentStatus::Captured->value,
                'payment_amount' => $amount,
            ])->save();
        }

        return $lead->refresh();
    }

    /**
     * THE BUG THIS SERVICE EXISTS TO FIX. Clicks land on the LEAF that owns the
     * code, so a parent counting only its own rows reports zero no matter how
     * much its downline produced — which is what made the hierarchy look broken.
     */
    public function test_a_parent_rolls_up_its_entire_downline(): void
    {
        foreach (['o2-p1', 'o2-p2', 'o2-p3', 'o3-p1', 'o3-p2'] as $code) {
            $this->click($code);
        }

        $this->assertSame(5, $this->metrics()->funnel($this->org1)['clicks'], 'Org 1 must see all five.');
        $this->assertSame(3, $this->metrics()->funnel($this->org2)['clicks']);
        $this->assertSame(2, $this->metrics()->funnel($this->org3)['clicks']);

        // ...and its own direct activity is zero, which is why direct-only lied.
        $this->assertSame(0, $this->metrics()->funnel($this->org1, rollup: false)['clicks']);
    }

    public function test_a_sibling_sees_nothing_of_the_other_branch(): void
    {
        $this->click('o2-p1');
        $this->click('o2-p2');
        $this->click('o3-p1');

        $this->assertSame(2, $this->metrics()->funnel($this->org2)['clicks']);
        $this->assertSame(1, $this->metrics()->funnel($this->org3)['clicks']);

        // The whole point: no bleed across branches.
        $visibleToOrg2 = ReferralSource::query()->visibleTo($this->org2)->pluck('id')->all();
        $this->assertNotContains($this->org3->id, $visibleToOrg2);
        $this->assertNotContains($this->partners['o3-1']->id, $visibleToOrg2);
        $this->assertNotContains($this->org1->id, $visibleToOrg2, 'A child must never see its parent.');
    }

    public function test_the_full_funnel_rolls_up(): void
    {
        $v1 = $this->click('o2-p1');
        $v2 = $this->click('o2-p2');
        $this->click('o3-p1');

        $this->convert('o2-p1', $v1, 249.00);   // converted
        $this->convert('o2-p2', $v2);            // lead only, never paid

        $org1 = $this->metrics()->funnel($this->org1);
        $this->assertSame(3, $org1['clicks']);
        $this->assertSame(2, $org1['leads']);
        $this->assertSame(1, $org1['conversions']);
        $this->assertSame(249.00, $org1['revenue']);

        $org3 = $this->metrics()->funnel($this->org3);
        $this->assertSame(1, $org3['clicks']);
        $this->assertSame(0, $org3['leads']);
        $this->assertSame(0.0, $org3['revenue']);
    }

    /** Authorised-but-never-captured is money nobody received. */
    public function test_only_captured_payments_count_as_conversions(): void
    {
        $v = $this->click('o2-p1');
        $lead = $this->convert('o2-p1', $v);
        $lead->forceFill([
            'payment_status' => LeadPaymentStatus::Authorized->value,
            'payment_amount' => 500.00,
        ])->save();

        $funnel = $this->metrics()->funnel($this->org2);
        $this->assertSame(1, $funnel['leads']);
        $this->assertSame(0, $funnel['conversions']);
        $this->assertSame(0.0, $funnel['revenue']);
    }

    /** One person reloading a page is not five prospects. */
    public function test_rates_are_computed_against_unique_visitors(): void
    {
        $visitor = $this->click('o2-p1');
        $this->click('o2-p1', $visitor);
        $this->click('o2-p1', $visitor);
        $this->convert('o2-p1', $visitor);

        $funnel = $this->metrics()->funnel($this->org2);

        $this->assertSame(1, $funnel['clicks'], 'Idempotent per visitor+code.');
        $this->assertSame(1, $funnel['unique_visitors']);
        $this->assertSame(100.0, $funnel['click_to_lead']);
    }

    public function test_a_parent_sees_one_row_per_sub_org(): void
    {
        $this->click('o2-p1');
        $this->click('o2-p2');
        $this->click('o3-p1');

        $rows = $this->metrics()->breakdown($this->org1);
        $byLabel = collect($rows)->keyBy('label');

        $this->assertSame(2, $byLabel['Main Org 2']['metrics']['clicks']);
        $this->assertSame(1, $byLabel['Main Org 3']['metrics']['clicks']);

        // A row per direct child, not per leaf partner.
        $this->assertFalse($byLabel->has('o2-partner-1'));
    }

    /** A group running its own campaigns must not have that activity vanish. */
    public function test_a_parents_own_direct_activity_appears_in_its_breakdown(): void
    {
        ReferralLink::factory()->for($this->org1, 'source')->create(['code' => 'org1-house']);
        $this->click('org1-house');
        $this->click('o2-p1');

        $rows = collect($this->metrics()->breakdown($this->org1))->keyBy('label');

        $this->assertSame(1, $rows['Main Org 1 (direct)']['metrics']['clicks']);
        $this->assertSame(1, $rows['Main Org 2']['metrics']['clicks']);
        $this->assertSame(2, $this->metrics()->funnel($this->org1)['clicks'], 'Rollup includes the house code.');
    }

    public function test_a_leaf_partner_reads_the_same_shape_as_a_group(): void
    {
        $this->click('o2-p1');

        $partner = $this->partners['o2-1'];
        $funnel = $this->metrics()->funnel($partner);

        $this->assertSame(1, $funnel['clicks']);
        // One dashboard for every level: a leaf's breakdown is just itself.
        $rows = $this->metrics()->breakdown($partner);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['own']);
    }

    public function test_a_date_range_narrows_every_metric(): void
    {
        $old = $this->click('o2-p1');
        ReferralClick::query()->update(['clicked_at' => now()->subDays(40)]);
        $this->click('o2-p2');

        $recent = $this->metrics()->funnel($this->org2, from: now()->subDays(7));
        $this->assertSame(1, $recent['clicks']);
        $this->assertSame(2, $this->metrics()->funnel($this->org2)['clicks']);
    }
}
