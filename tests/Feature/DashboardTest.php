<?php

namespace Tests\Feature;

use App\Actions\Referral\AttributeLeadAction;
use App\Actions\Referral\RecordReferralClickAction;
use App\Enums\Payments\LeadPaymentStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ReferralBreakdownWidget;
use App\Filament\Widgets\ReferralFunnelWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Models\Lead;
use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The dashboard must RENDER, with data in it. Every widget here computes its
 * numbers in closures over live queries, which is exactly the code a route-level
 * smoke test cannot reach.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        Role::findOrCreate('super_admin', 'web');

        return tap(User::factory()->create(['is_active' => true, 'referral_source_id' => null]))
            ->assignRole('super_admin');
    }

    private function seedFunnel(): ReferralSource
    {
        $group = ReferralSource::factory()->salesGroup()->create(['name' => 'National', 'commission_rate' => 20]);
        $partner = ReferralSource::factory()->create(['name' => 'Partner', 'parent_id' => $group->id, 'commission_rate' => 10]);
        ReferralLink::factory()->for($partner, 'source')->create(['code' => 'nat-p1']);

        $visitor = (string) Str::uuid();
        app(RecordReferralClickAction::class)->execute('nat-p1', $visitor);

        $lead = Lead::factory()->create();
        app(AttributeLeadAction::class)->execute($lead, 'nat-p1', $visitor);
        $lead->forceFill([
            'payment_status' => LeadPaymentStatus::Captured->value,
            'payment_amount' => 300.00,
            'payment_processed_at' => now(),
        ])->save();

        return $group;
    }

    public function test_the_dashboard_page_renders(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(Dashboard::class)->assertSuccessful();
    }

    public function test_the_funnel_widget_reports_the_whole_business_for_staff(): void
    {
        $this->seedFunnel();
        $this->actingAs($this->staff());

        Livewire::test(ReferralFunnelWidget::class)
            ->assertSuccessful()
            ->assertSee('Referred clicks')
            ->assertSee('Commission owed')
            // 10% partner band + 10% national override band on $300.
            ->assertSee('$60.00')
            ->assertSee('$300.00');
    }

    public function test_the_breakdown_widget_rolls_a_group_up_for_staff(): void
    {
        $this->seedFunnel();
        $this->actingAs($this->staff());

        Livewire::test(ReferralBreakdownWidget::class)
            ->assertSuccessful()
            // Top-level rows only, each totalling its branch — the partner's
            // click and sale appear against National, not as a second row.
            ->assertSee('National')
            ->assertDontSee('Partner');
    }

    public function test_the_revenue_chart_renders_with_a_full_window(): void
    {
        $this->seedFunnel();
        $this->actingAs($this->staff());

        Livewire::test(RevenueChartWidget::class)->assertSuccessful();
    }

    /** Empty states must render, not throw — this is a fresh install. */
    public function test_every_widget_renders_with_no_data_at_all(): void
    {
        $this->actingAs($this->staff());

        Livewire::test(ReferralFunnelWidget::class)->assertSuccessful()->assertSee('$0.00');
        Livewire::test(ReferralBreakdownWidget::class)->assertSuccessful()->assertSee('Nothing referred yet');
        Livewire::test(RevenueChartWidget::class)->assertSuccessful();
        Livewire::test(Dashboard::class)->assertSuccessful();
    }
}
