<?php

namespace Tests\Feature;

use App\Actions\Referral\AttributeLeadAction;
use App\Actions\Referral\RecordReferralClickAction;
use App\Enums\Payments\LeadPaymentStatus;
use App\Filament\Partner\Pages\PartnerDashboard;
use App\Filament\Partner\Resources\Organizations\OrganizationResource;
use App\Filament\Partner\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Partner\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Partner\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Partner\Resources\Organizations\RelationManagers\PartnerLinksRelationManager;
use App\Filament\Partner\Resources\Organizations\RelationManagers\PartnerRepsRelationManager;
use App\Filament\Partner\Resources\PartnerResource;
use App\Filament\Widgets\ReferralBreakdownWidget;
use App\Filament\Widgets\ReferralFunnelWidget;
use App\Models\Lead;
use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The partner portal, on the operator's own tree:
 *
 *   Main Org 1
 *   ├── Main Org 2 ── partner A
 *   └── Main Org 3 ── partner B
 */
class PartnerPanelTest extends TestCase
{
    use RefreshDatabase;

    private ReferralSource $org1;

    private ReferralSource $org2;

    private ReferralSource $org3;

    private ReferralSource $partnerA;

    private ReferralSource $partnerB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('affiliate', 'web');
        Role::findOrCreate('super_admin', 'web');

        $this->org1 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 1', 'commission_rate' => 20]);
        $this->org2 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 2', 'parent_id' => $this->org1->id, 'commission_rate' => 15]);
        $this->org3 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 3', 'parent_id' => $this->org1->id, 'commission_rate' => 15]);
        $this->partnerA = ReferralSource::factory()->create(['name' => 'Partner A', 'parent_id' => $this->org2->id, 'commission_rate' => 10]);
        $this->partnerB = ReferralSource::factory()->create(['name' => 'Partner B', 'parent_id' => $this->org3->id, 'commission_rate' => 10]);

        ReferralLink::factory()->for($this->partnerA, 'source')->create(['code' => 'a-code']);
        ReferralLink::factory()->for($this->partnerB, 'source')->create(['code' => 'b-code']);
    }

    /**
     * Acts as a partner AND makes the partner panel current.
     *
     * Filament resolves resource routes against whichever panel is current, and
     * outside an HTTP request that defaults to the first registered one (admin).
     * Without this a partner page renders but then builds links like
     * `filament.admin.resources.organizations.edit`, which does not exist —
     * a test artifact, not a routing defect.
     */
    private function actingAsPartner(ReferralSource $source): User
    {
        $user = tap(User::factory()->create(['referral_source_id' => $source->id, 'is_active' => true]))
            ->assignRole('affiliate');

        Filament::setCurrentPanel('partner');
        $this->actingAs($user);

        return $user;
    }

    private function repFor(ReferralSource $source): User
    {
        return tap(User::factory()->create(['referral_source_id' => $source->id, 'is_active' => true]))
            ->assignRole('affiliate');
    }

    private function sell(string $code, float $amount): Lead
    {
        $visitor = (string) Str::uuid();
        app(RecordReferralClickAction::class)->execute($code, $visitor);

        $lead = Lead::factory()->create();
        app(AttributeLeadAction::class)->execute($lead, $code, $visitor);
        $lead->forceFill([
            'payment_status' => LeadPaymentStatus::Captured->value,
            'payment_amount' => $amount,
            'payment_processed_at' => now(),
        ])->save();

        return $lead;
    }

    /**
     * THE STRUCTURAL GUARANTEE. prescribe-rx's tenancy audit found 20 cross-tenant
     * leaks, root cause "only 4 of 277 models carry the scope trait". The failure
     * is never a wrong scope, it is a screen nobody thought about — so this test
     * enumerates the panel and makes forgetting fail CI.
     */
    public function test_every_partner_panel_resource_inherits_row_scoping(): void
    {
        $resources = Filament::getPanel('partner')->getResources();

        $this->assertNotEmpty($resources, 'A panel with no resources would pass this test vacuously.');

        foreach ($resources as $resource) {
            $this->assertTrue(
                is_subclass_of($resource, PartnerResource::class),
                "{$resource} must extend PartnerResource, which is where row scoping lives.",
            );
        }
    }

    public function test_a_partner_reaches_their_panel_and_staff_do_not(): void
    {
        $rep = $this->repFor($this->org2);
        $staff = tap(User::factory()->create(['referral_source_id' => null, 'is_active' => true]))
            ->assignRole('super_admin');

        $partnerPanel = Filament::getPanel('partner');
        $adminPanel = Filament::getPanel('admin');

        $this->assertTrue($rep->canAccessPanel($partnerPanel));
        $this->assertFalse($rep->canAccessPanel($adminPanel));

        $this->assertTrue($staff->canAccessPanel($adminPanel));
        $this->assertFalse($staff->canAccessPanel($partnerPanel), 'The two audiences are mutually exclusive.');
    }

    public function test_the_dashboard_renders_for_every_level(): void
    {
        foreach ([$this->org1, $this->org2, $this->partnerA] as $source) {
            $this->actingAs($this->repFor($source));
            Livewire::test(PartnerDashboard::class)->assertSuccessful();
        }
    }

    public function test_a_group_sees_its_whole_downline_and_a_sibling_sees_none_of_it(): void
    {
        $this->sell('a-code', 100.00);   // under Org 2
        $this->sell('b-code', 500.00);   // under Org 3

        // Org 2's rep sees only their own branch's $100.
        $this->actingAs($this->repFor($this->org2));
        Livewire::test(ReferralFunnelWidget::class)
            ->assertSuccessful()
            ->assertSee('$100.00')
            ->assertDontSee('$500.00');

        // Org 1 sits above both and sees the combined $600.
        $this->actingAs($this->repFor($this->org1));
        Livewire::test(ReferralFunnelWidget::class)
            ->assertSuccessful()
            ->assertSee('$600.00');
    }

    public function test_a_group_breakdown_lists_its_direct_sub_organisations(): void
    {
        $this->sell('a-code', 100.00);

        $this->actingAs($this->repFor($this->org1));

        Livewire::test(ReferralBreakdownWidget::class)
            ->assertSuccessful()
            ->assertSee('Main Org 2')
            ->assertSee('Main Org 3')
            // One row per DIRECT child, not per leaf.
            ->assertDontSee('Partner A');
    }

    public function test_the_organisations_list_is_scoped_to_the_downline(): void
    {
        $this->actingAsPartner($this->org2);

        Livewire::test(ListOrganizations::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->org2, $this->partnerA])
            ->assertCanNotSeeTableRecords([$this->org1, $this->org3, $this->partnerB]);
    }

    /** Direct record access must be refused, not merely hidden from the list. */
    public function test_a_partner_cannot_open_a_record_outside_their_downline(): void
    {
        $this->actingAs($this->repFor($this->org2));

        $this->assertTrue(OrganizationResource::canView($this->partnerA));
        $this->assertFalse(OrganizationResource::canView($this->partnerB));
        $this->assertFalse(OrganizationResource::canView($this->org1), 'Never upward.');
    }

    public function test_partners_can_never_delete(): void
    {
        $this->actingAs($this->repFor($this->org2));

        $this->assertFalse(OrganizationResource::canDelete($this->partnerA));
        $this->assertFalse(OrganizationResource::canDeleteAny());
    }

    /** A viewer with no resolvable organisation sees nothing, never everything. */
    public function test_an_unidentified_viewer_sees_no_rows(): void
    {
        $orphanSource = ReferralSource::factory()->create();
        $rep = $this->repFor($orphanSource);
        $orphanSource->delete();

        Filament::setCurrentPanel('partner');
        $this->actingAs($rep->fresh());

        Livewire::test(ListOrganizations::class)
            ->assertSuccessful()
            ->assertCanNotSeeTableRecords([$this->org1, $this->org2, $this->partnerA]);
    }

    /**
     * THE GATE CAUGHT THIS: the shared People panel was invisible to partners,
     * because Filament authorises a relation manager through the RELATED model's
     * policy and `UserPolicy::viewAny` needs a permission partner roles do not
     * have. The panel simply did not render and nobody could add a person.
     *
     * `PartnerPanelTest` never mounted a relation manager, which is why 1096
     * green tests missed it. It does now.
     */
    public function test_a_partner_can_see_and_use_both_panels_on_their_organisation(): void
    {
        $this->actingAsPartner($this->org2);

        $this->assertTrue(
            PartnerRepsRelationManager::canViewForRecord($this->partnerA, EditOrganization::class),
            'The People panel must render for an organisation in the downline.',
        );
        $this->assertTrue(
            PartnerLinksRelationManager::canViewForRecord($this->partnerA, EditOrganization::class),
        );

        Livewire::test(PartnerRepsRelationManager::class, [
            'ownerRecord' => $this->partnerA,
            'pageClass' => EditOrganization::class,
        ])
            ->assertSuccessful()
            ->callTableAction('create', data: [
                'name' => 'New Rep',
                'email' => 'rep@example.test',
                'roles' => [Role::findByName('affiliate', 'web')->id],
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $rep = User::where('email', 'rep@example.test')->sole();
        $this->assertSame($this->partnerA->id, $rep->referral_source_id, 'Attached to the owner org, not left as staff.');
        $this->assertTrue($rep->isPartner());
    }

    /** ...and neither panel opens on an organisation outside the downline. */
    public function test_the_relation_managers_refuse_an_organisation_outside_the_downline(): void
    {
        $this->actingAsPartner($this->org2);

        $this->assertFalse(PartnerRepsRelationManager::canViewForRecord($this->partnerB, EditOrganization::class));
        $this->assertFalse(PartnerLinksRelationManager::canViewForRecord($this->partnerB, EditOrganization::class));
        $this->assertFalse(PartnerRepsRelationManager::canViewForRecord($this->org1, EditOrganization::class), 'Never upward.');
    }

    /**
     * ALSO FROM THE GATE: a partner's own top row was unsaveable. `parent_id` was
     * required with options scoped to the downline, but that row's parent is the
     * UPLINE — deliberately not an option — so every edit failed validation on a
     * field the user never touched.
     */
    public function test_a_partner_can_save_an_edit_to_their_own_organisation(): void
    {
        $this->actingAsPartner($this->org2);

        Livewire::test(EditOrganization::class, ['record' => $this->org2->getRouteKey()])
            ->fillForm(['name' => 'Main Org 2 Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Main Org 2 Renamed', $this->org2->fresh()->name);
        $this->assertSame($this->org1->id, $this->org2->fresh()->parent_id, 'The upline is untouched.');
    }

    public function test_a_partner_can_create_a_sub_organisation_under_their_own_tree(): void
    {
        $this->actingAsPartner($this->org2);

        Livewire::test(CreateOrganization::class)
            ->fillForm([
                'name' => 'New Sub',
                'slug' => 'new-sub',
                'type' => 'affiliate',
                'parent_id' => $this->partnerA->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = ReferralSource::where('slug', 'new-sub')->sole();
        $this->assertSame($this->partnerA->id, $created->parent_id);
    }

    /** A new organisation may not be attached outside the viewer's downline. */
    public function test_a_partner_cannot_attach_a_new_organisation_elsewhere_in_the_tree(): void
    {
        $this->actingAsPartner($this->org2);

        Livewire::test(CreateOrganization::class)
            ->fillForm([
                'name' => 'Hijack',
                'slug' => 'hijack',
                'type' => 'affiliate',
                'parent_id' => $this->org3->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['parent_id']);

        $this->assertNull(ReferralSource::where('slug', 'hijack')->first());
    }

    /** Queued payloads carry lead PII, so the side door has to match the front. */
    public function test_a_partner_cannot_view_horizon(): void
    {
        $rep = $this->repFor($this->org2);

        $this->assertFalse(Gate::forUser($rep)->allows('viewHorizon'));
    }

    /**
     * AND HERE IS THE HONEST LIMIT OF THE TYPE BOUNDARY.
     *
     * Shield registers a `Gate::before` that grants `super_admin` EVERY ability,
     * which short-circuits Horizon's gate and every policy in the app. So "a
     * partner with a staff role still cannot get in" is NOT true for
     * `super_admin` — the protection is that the combination must not be
     * creatable, not that it is harmless.
     *
     * `canAccessPanel()` still refuses them both panels (it is not a Gate
     * ability), so this is a narrower hole than it sounds — but it is a hole, and
     * the guard is `User::STAFF_ONLY_ROLES` plus the staff form's role filter.
     */
    public function test_a_partner_holding_a_staff_role_is_a_detectable_conflict(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $rep = $this->repFor($this->org2);

        $this->assertFalse($rep->hasConflictingStaffRole());

        $rep->assignRole('super_admin');
        $rep = $rep->fresh();

        $this->assertTrue($rep->hasConflictingStaffRole(), 'The combination must be detectable.');

        // Both panels still refuse them — canAccessPanel is not a Gate ability,
        // so Shield's before-callback does not reach it.
        $this->assertFalse($rep->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($rep->canAccessPanel(Filament::getPanel('partner')));

        // ...but Horizon's gate IS an ability, and super_admin bypasses it.
        $this->assertTrue(
            Gate::forUser($rep)->allows('viewHorizon'),
            'Documenting the real behaviour: Shield Gate::before wins. The guard is preventing this user from existing.',
        );
    }
}
