<?php

namespace Tests\Feature;

use App\Actions\Referral\AttributeLeadAction;
use App\Actions\Referral\CalculateReferralCommissionAction;
use App\Enums\Payments\LeadPaymentStatus;
use App\Models\Lead;
use App\Models\Referral\ReferralCommission;
use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Telescoping override bands: each tier earns the difference between its rate and
 * what its downline already claimed, so a chain pays the TOP rate in total and
 * never the sum of every rate.
 */
class ReferralCommissionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{ReferralSource, ReferralSource, ReferralSource} leaf, mid, top */
    private function chain(float $leaf, float $mid, float $top): array
    {
        $national = ReferralSource::factory()->salesGroup()->create(['name' => 'National', 'commission_rate' => $top]);
        $region = ReferralSource::factory()->salesGroup()->create(['name' => 'Region', 'parent_id' => $national->id, 'commission_rate' => $mid]);
        $partner = ReferralSource::factory()->create(['name' => 'Partner', 'parent_id' => $region->id, 'commission_rate' => $leaf]);

        ReferralLink::factory()->for($partner, 'source')->create(['code' => 'partner-code']);

        return [$partner, $region, $national];
    }

    private function convert(float $amount, string $code = 'partner-code'): Lead
    {
        $lead = Lead::factory()->create();
        app(AttributeLeadAction::class)->execute($lead, $code);

        // The transition is what the observer watches.
        $lead->forceFill([
            'payment_status' => LeadPaymentStatus::Captured->value,
            'payment_amount' => $amount,
        ])->save();

        return $lead->refresh();
    }

    public function test_bands_telescope_so_the_total_is_the_top_rate(): void
    {
        [$partner, $region, $national] = $this->chain(leaf: 10, mid: 15, top: 20);

        $lead = $this->convert(100.00);

        $rows = ReferralCommission::where('lead_id', $lead->id)->orderBy('tier')->get();

        $this->assertCount(3, $rows);
        $this->assertSame('10.00', $rows[0]->amount, 'The partner earns its own 10%.');
        $this->assertSame('5.00', $rows[1]->amount, 'The region earns the 15-10 band.');
        $this->assertSame('5.00', $rows[2]->amount, 'National earns the 20-15 band.');

        // THE PROPERTY THAT MATTERS: 20% of the sale, not 45%.
        $this->assertSame(20.00, (float) $rows->sum('amount'));
    }

    public function test_a_parent_earning_less_than_its_child_gets_nothing_rather_than_a_negative(): void
    {
        [$partner, $region, $national] = $this->chain(leaf: 10, mid: 5, top: 20);

        $lead = $this->convert(100.00);
        $rows = ReferralCommission::where('lead_id', $lead->id)->get()->keyBy('source_name');

        $this->assertSame('10.00', $rows['Partner']->amount);
        $this->assertArrayNotHasKey('Region', $rows->all(), 'A zero band writes no row.');
        $this->assertSame('10.00', $rows['National']->amount, 'National still earns up to its own 20%.');
        $this->assertSame(20.00, (float) $rows->sum('amount'));
    }

    public function test_the_rate_is_snapshotted_so_renegotiating_cannot_rewrite_history(): void
    {
        [$partner] = $this->chain(leaf: 10, mid: 0, top: 0);

        $lead = $this->convert(200.00);
        $this->assertSame('20.00', ReferralCommission::where('lead_id', $lead->id)->sole()->amount);

        // Renegotiate upward next quarter.
        $partner->update(['commission_rate' => 25]);

        $row = ReferralCommission::where('lead_id', $lead->id)->sole();
        $this->assertSame('10.00', $row->rate, 'The rate that applied is on the row.');
        $this->assertSame('20.00', $row->amount, 'What was owed does not move.');
    }

    /** An authorisation is not money. */
    public function test_nothing_is_earned_until_the_payment_is_captured(): void
    {
        $this->chain(leaf: 10, mid: 0, top: 0);

        $lead = Lead::factory()->create();
        app(AttributeLeadAction::class)->execute($lead, 'partner-code');
        $lead->forceFill([
            'payment_status' => LeadPaymentStatus::Authorized->value,
            'payment_amount' => 100.00,
        ])->save();

        $this->assertSame(0, ReferralCommission::count());
    }

    public function test_an_unattributed_sale_earns_nobody_anything(): void
    {
        $this->chain(leaf: 10, mid: 0, top: 0);

        $lead = Lead::factory()->create();
        $lead->forceFill([
            'payment_status' => LeadPaymentStatus::Captured->value,
            'payment_amount' => 100.00,
        ])->save();

        $this->assertSame(0, ReferralCommission::count());
    }

    public function test_recomputing_updates_in_place_rather_than_paying_twice(): void
    {
        $this->chain(leaf: 10, mid: 0, top: 0);
        $lead = $this->convert(100.00);

        app(CalculateReferralCommissionAction::class)->execute($lead);
        app(CalculateReferralCommissionAction::class)->execute($lead);

        $this->assertSame(1, ReferralCommission::where('lead_id', $lead->id)->count());
        $this->assertSame(10.00, (float) ReferralCommission::sum('amount'));
    }

    /** Paid money has left the building; a recompute may not restate it. */
    public function test_a_paid_row_is_never_recomputed(): void
    {
        [$partner] = $this->chain(leaf: 10, mid: 0, top: 0);
        $lead = $this->convert(100.00);

        $row = ReferralCommission::where('lead_id', $lead->id)->sole();
        $row->update(['status' => ReferralCommission::STATUS_PAID, 'paid_at' => now()]);

        $partner->update(['commission_rate' => 50]);
        app(CalculateReferralCommissionAction::class)->execute($lead);

        $this->assertSame('10.00', $row->fresh()->amount);
        $this->assertSame(ReferralCommission::STATUS_PAID, $row->fresh()->status);
    }

    public function test_a_commission_still_names_its_payee_after_the_source_is_gone(): void
    {
        [$partner] = $this->chain(leaf: 10, mid: 0, top: 0);
        $lead = $this->convert(100.00);

        $row = ReferralCommission::where('lead_id', $lead->id)->sole();
        $partner->links()->forceDelete();
        $partner->forceDelete();

        $row->refresh();
        $this->assertNull($row->referral_source_id);
        $this->assertSame('Partner', $row->source_name);
        $this->assertSame('10.00', $row->amount);
    }

    /** A settled commission pins its lead in place. */
    public function test_a_lead_with_commissions_cannot_be_hard_deleted(): void
    {
        $this->chain(leaf: 10, mid: 0, top: 0);
        $lead = $this->convert(100.00);

        $this->expectException(QueryException::class);

        $lead->forceDelete();
    }

    public function test_outstanding_excludes_paid_and_void(): void
    {
        $this->chain(leaf: 10, mid: 15, top: 20);
        $lead = $this->convert(100.00);

        $rows = ReferralCommission::orderBy('tier')->get();
        $rows[0]->update(['status' => ReferralCommission::STATUS_PAID]);
        $rows[1]->update(['status' => ReferralCommission::STATUS_VOID]);

        $this->assertSame(1, ReferralCommission::outstanding()->count());
        $this->assertSame(5.00, (float) ReferralCommission::outstanding()->sum('amount'));
    }
}
