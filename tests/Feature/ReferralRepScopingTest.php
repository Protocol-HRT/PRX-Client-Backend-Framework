<?php

namespace Tests\Feature;

use App\Models\Referral\ReferralSource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The no-bleed guarantees, on the operator's own tree:
 *
 *   Main Org 1
 *   ├── Main Org 2 ── 3 partners
 *   └── Main Org 3 ── 2 partners
 */
class ReferralRepScopingTest extends TestCase
{
    use RefreshDatabase;

    private ReferralSource $org1;

    private ReferralSource $org2;

    private ReferralSource $org3;

    private ReferralSource $partner2a;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie caches the permission map per process, and RefreshDatabase
        // reuses role ids across tests in this class — without this, a role
        // created in setUp reads as absent in the test body.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('affiliate', 'web');
        Role::findOrCreate('admin', 'web');

        $this->org1 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 1']);
        $this->org2 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 2', 'parent_id' => $this->org1->id]);
        $this->org3 = ReferralSource::factory()->salesGroup()->create(['name' => 'Main Org 3', 'parent_id' => $this->org1->id]);
        $this->partner2a = ReferralSource::factory()->create(['name' => 'o2-partner-1', 'parent_id' => $this->org2->id]);
    }

    /**
     * `is_active` is set explicitly because the factory omits it and relies on
     * the column default — so a freshly built instance carries NULL in memory
     * even though the row is true, and `! $this->is_active` in the panel gate
     * then denies a user who is really active. Filament always loads users from
     * the database, so this is a test artifact rather than a defect; setting it
     * here keeps the fixture honest instead of papering over it with `fresh()`.
     */
    private function rep(ReferralSource $source): User
    {
        return tap(User::factory()->create([
            'referral_source_id' => $source->id,
            'is_active' => true,
        ]))->assignRole('affiliate');
    }

    private function staff(): User
    {
        return tap(User::factory()->create([
            'referral_source_id' => null,
            'is_active' => true,
        ]))->assignRole('admin');
    }

    public function test_a_top_level_rep_sees_the_entire_tree(): void
    {
        $ids = $this->rep($this->org1)->visibleReferralSourceIds();

        foreach ([$this->org1, $this->org2, $this->org3, $this->partner2a] as $source) {
            $this->assertContains($source->id, $ids);
        }
    }

    public function test_a_branch_rep_sees_only_their_branch(): void
    {
        $ids = $this->rep($this->org2)->visibleReferralSourceIds();

        $this->assertContains($this->org2->id, $ids);
        $this->assertContains($this->partner2a->id, $ids);

        $this->assertNotContains($this->org3->id, $ids, 'A sibling branch must be invisible.');
        $this->assertNotContains($this->org1->id, $ids, 'A rep must never see UP the tree.');
    }

    public function test_a_leaf_partner_sees_only_themselves(): void
    {
        $this->assertSame(
            [$this->partner2a->id],
            $this->rep($this->partner2a)->visibleReferralSourceIds(),
        );
    }

    /** Staff are not scoped by this at all — null means "no referral filter". */
    public function test_staff_are_not_referral_scoped(): void
    {
        $staff = $this->staff();

        $this->assertNull($staff->visibleReferralSourceIds());
        $this->assertFalse($staff->isPartner());
    }

    /**
     * THE ONE THAT MATTERS MOST. A partner reaching the staff admin would see
     * patient PII, credentials, margin and every other partner's numbers.
     */
    public function test_a_partner_cannot_reach_the_staff_admin_panel(): void
    {
        $rep = $this->rep($this->org2);

        $this->assertFalse($rep->canAccessPanel(Filament::getPanel('admin')));
    }

    /**
     * ...and it holds even with a staff role attached, because the gate is a TYPE
     * boundary rather than a permission one. This is the mistake most likely to
     * be made by hand later.
     */
    public function test_a_partner_with_a_staff_role_still_cannot_reach_the_admin(): void
    {
        $rep = $this->rep($this->org2);
        $rep->assignRole('admin');

        $this->assertFalse($rep->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_staff_can_still_reach_the_admin(): void
    {
        $this->assertTrue($this->staff()->canAccessPanel(Filament::getPanel('admin')));
    }

    /**
     * A partner whose organisation is SOFT deleted must see NOTHING, never
     * everything — the classic permissive-default leak.
     */
    public function test_a_rep_whose_org_was_soft_deleted_sees_no_rows(): void
    {
        $orphan = ReferralSource::factory()->create();
        $rep = $this->rep($orphan);
        $orphan->delete();

        $this->assertSame([], $rep->fresh()->visibleReferralSourceIds());
        $this->assertTrue($rep->fresh()->isPartner(), 'Still a partner — the column is intact.');
    }

    /**
     * AN ORGANISATION WITH PEOPLE CANNOT BE HARD-DELETED, and the reason is
     * privilege escalation rather than tidiness.
     *
     * `referral_source_id` is what marks a user as a partner rather than staff.
     * Had this been nullOnDelete, removing an organisation would silently have
     * promoted every affiliate in it to a staff-eligible account. Failing loudly
     * forces someone to decide what happens to the people.
     */
    public function test_an_organisation_with_people_cannot_be_force_deleted(): void
    {
        $source = ReferralSource::factory()->create();
        $this->rep($source);

        $this->expectException(QueryException::class);

        $source->forceDelete();
    }

    /** Soft delete stays the normal path and touches nobody's account. */
    public function test_soft_deleting_an_organisation_leaves_its_people_intact(): void
    {
        $source = ReferralSource::factory()->create();
        $rep = $this->rep($source);

        $source->delete();

        $this->assertNotNull($rep->fresh());
        $this->assertSame($source->id, $rep->fresh()->referral_source_id);
        $this->assertFalse($rep->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }
}
