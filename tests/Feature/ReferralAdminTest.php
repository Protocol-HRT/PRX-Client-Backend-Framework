<?php

namespace Tests\Feature;

use App\Actions\Referral\MintReferralCodeAction;
use App\Actions\Referral\RecordReferralClickAction;
use App\Filament\Resources\Referrals\Pages\CreateReferralSource;
use App\Filament\Resources\Referrals\Pages\EditReferralSource;
use App\Filament\Resources\Referrals\Pages\ListReferralSources;
use App\Filament\Resources\Referrals\RelationManagers\LinksRelationManager;
use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use App\Models\User;
use App\Settings\BrandSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The operator-facing half: the hierarchy, code minting, and the link/QR
 * rendering the admin screens rely on.
 */
class ReferralAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user who can actually reach the panel.
     *
     * `super_admin` bypasses via Shield's Gate::before. A plain user 403s here —
     * correctly, because ReferralSourcePolicy exists and is enforced. (Resources
     * WITHOUT a policy are open by default in Filament's non-strict mode, which
     * is why some older tests get away with a bare user.)
     */
    private function operator(): User
    {
        Role::findOrCreate('super_admin', 'web');

        return tap(User::factory()->create())->assignRole('super_admin');
    }

    private function mint(): MintReferralCodeAction
    {
        return app(MintReferralCodeAction::class);
    }

    public function test_the_hierarchy_is_arbitrary_depth(): void
    {
        $group = ReferralSource::factory()->salesGroup()->create();
        $region = ReferralSource::factory()->salesGroup()->create(['parent_id' => $group->id]);
        $rep = ReferralSource::factory()->create(['parent_id' => $region->id]);
        $sub = ReferralSource::factory()->create(['parent_id' => $rep->id]);

        $ids = $group->descendantAndSelfIds();

        sort($ids);
        $expected = [$group->id, $region->id, $rep->id, $sub->id];
        sort($expected);

        $this->assertSame($expected, $ids, 'A four-level downline must resolve in full.');
    }

    public function test_a_viewer_sees_their_whole_downline_and_nothing_beside_it(): void
    {
        $group = ReferralSource::factory()->salesGroup()->create();
        $region = ReferralSource::factory()->salesGroup()->create(['parent_id' => $group->id]);
        $rep = ReferralSource::factory()->create(['parent_id' => $region->id]);

        $stranger = ReferralSource::factory()->create();
        $strangersRep = ReferralSource::factory()->create(['parent_id' => $stranger->id]);

        $visible = ReferralSource::query()->visibleTo($group)->pluck('id')->all();

        $this->assertContains($rep->id, $visible, 'A group must see three levels down.');
        $this->assertNotContains($stranger->id, $visible);
        $this->assertNotContains($strangersRep->id, $visible);

        // ...and a leaf sees only itself.
        $this->assertSame([$rep->id], ReferralSource::query()->visibleTo($rep)->pluck('id')->all());
    }

    /** A missing viewer must resolve to no rows, never to every row. */
    public function test_an_unknown_viewer_sees_nothing(): void
    {
        ReferralSource::factory()->count(3)->create();

        $this->assertSame(0, ReferralSource::query()->visibleTo(null)->count());
    }

    public function test_a_source_cannot_report_into_itself(): void
    {
        $source = ReferralSource::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $source->update(['parent_id' => $source->id]);
    }

    public function test_a_source_cannot_report_into_its_own_downline(): void
    {
        $group = ReferralSource::factory()->salesGroup()->create();
        $rep = ReferralSource::factory()->create(['parent_id' => $group->id]);
        $sub = ReferralSource::factory()->create(['parent_id' => $rep->id]);

        $this->expectException(InvalidArgumentException::class);

        // Would make group → rep → sub → group.
        $group->update(['parent_id' => $sub->id]);
    }

    /**
     * Even if a cycle somehow reached the database, the walk must terminate.
     * A scope that can hang is a scope that can take the admin panel down.
     */
    public function test_the_downline_walk_terminates_on_a_cycle(): void
    {
        $a = ReferralSource::factory()->create();
        $b = ReferralSource::factory()->create(['parent_id' => $a->id]);

        // Bypass the guard the way a bad import or a raw query would.
        \DB::table('referral_sources')->where('id', $a->id)->update(['parent_id' => $b->id]);

        $ids = $a->fresh()->descendantAndSelfIds();

        sort($ids);
        $expected = [$a->id, $b->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_a_minted_code_carries_the_source_prefix_and_is_unique(): void
    {
        $source = ReferralSource::factory()->create(['code_prefix' => 'ACME']);

        $codes = [];

        for ($i = 0; $i < 25; $i++) {
            $code = $this->mint()->execute($source);
            ReferralLink::create(['referral_source_id' => $source->id, 'code' => $code]);
            $codes[] = $code;
        }

        $this->assertCount(25, array_unique($codes));

        foreach ($codes as $code) {
            $this->assertStringStartsWith('acme-', $code);
        }
    }

    /** Codes get read aloud and typed off paper — no 0/o/1/l/i. */
    public function test_minted_codes_avoid_visually_ambiguous_characters(): void
    {
        $source = ReferralSource::factory()->create(['code_prefix' => 'zz']);

        for ($i = 0; $i < 40; $i++) {
            $tail = substr($this->mint()->execute($source), 3);
            $this->assertSame('', preg_replace('/[^01oli]/', '', $tail), "Ambiguous character in {$tail}");
        }
    }

    public function test_minting_falls_back_to_the_slug_when_no_prefix_is_set(): void
    {
        $source = ReferralSource::factory()->create(['code_prefix' => null, 'slug' => 'north-region']);

        $this->assertStringStartsWith('northregion-', $this->mint()->execute($source));
    }

    /**
     * A soft-deleted link still holds its code, so minting must not reuse it.
     *
     * Asserted on the collision check DIRECTLY rather than by minting in a loop
     * and hoping for a clash — the tail space is 31^6, so a loop never reaches
     * the branch and would pass with the check deleted. (It did, when this test
     * was first written that way.)
     */
    public function test_minting_treats_a_soft_deleted_code_as_taken(): void
    {
        $source = ReferralSource::factory()->create(['code_prefix' => 'zz']);
        $link = ReferralLink::create(['referral_source_id' => $source->id, 'code' => 'zz-aaaaaa']);
        $link->delete();

        $this->assertTrue($link->trashed());
        $this->assertTrue($this->mint()->codeIsTaken('zz-aaaaaa'), 'A retired code must never be handed out again.');
        $this->assertFalse($this->mint()->codeIsTaken('zz-bbbbbb'));
    }

    /** ...and the database agrees, which is why the check has to consider them. */
    public function test_a_soft_deleted_code_still_blocks_reuse_at_the_database(): void
    {
        $source = ReferralSource::factory()->create();
        $link = ReferralLink::create(['referral_source_id' => $source->id, 'code' => 'zz-aaaaaa']);
        $link->delete();

        $this->expectException(QueryException::class);

        ReferralLink::create(['referral_source_id' => $source->id, 'code' => 'zz-aaaaaa']);
    }

    public function test_a_link_builds_its_full_public_url(): void
    {
        $settings = app(BrandSettings::class);
        $settings->site_url = 'https://atlasprotocol.com';
        $settings->save();

        $link = ReferralLink::factory()->create(['code' => 'acme-7k2f9x', 'destination_path' => null]);

        $this->assertSame('https://atlasprotocol.com/?ref=acme-7k2f9x', $link->url());
    }

    public function test_a_link_can_land_on_a_specific_page(): void
    {
        $settings = app(BrandSettings::class);
        $settings->site_url = 'https://atlasprotocol.com/';
        $settings->save();

        $link = ReferralLink::factory()->create([
            'code' => 'acme-7k2f9x',
            'destination_path' => '/stacks/metabolic-reset',
        ]);

        $this->assertSame('https://atlasprotocol.com/stacks/metabolic-reset?ref=acme-7k2f9x', $link->url());
    }

    /** A destination that already carries a query string must not get a second `?`. */
    public function test_the_ref_param_is_appended_correctly_to_an_existing_query(): void
    {
        $settings = app(BrandSettings::class);
        $settings->site_url = 'https://atlasprotocol.com';
        $settings->save();

        $link = ReferralLink::factory()->create([
            'code' => 'acme-7k2f9x',
            'destination_path' => '/stacks?goal=sleep-recovery',
        ]);

        $this->assertSame(
            'https://atlasprotocol.com/stacks?goal=sleep-recovery&ref=acme-7k2f9x',
            $link->url(),
        );
    }

    public function test_a_link_renders_a_scannable_qr_code(): void
    {
        $settings = app(BrandSettings::class);
        $settings->site_url = 'https://atlasprotocol.com';
        $settings->save();

        $link = ReferralLink::factory()->create(['code' => 'acme-7k2f9x']);
        $svg = $link->qrCodeSvg();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $svg);

        $markup = base64_decode(substr($svg, strlen('data:image/svg+xml;base64,')));
        $this->assertStringContainsString('<svg', $markup);
        $this->assertGreaterThan(500, strlen($markup));
    }

    /** No site_url configured must degrade quietly, not throw in the admin. */
    public function test_url_and_qr_are_null_without_a_configured_site_url(): void
    {
        $settings = app(BrandSettings::class);
        $settings->site_url = null;
        $settings->save();

        $link = ReferralLink::factory()->create(['code' => 'acme-7k2f9x']);

        $this->assertNull($link->url());
        $this->assertNull($link->qrCodeSvg());
    }

    /**
     * The screens must actually RENDER. A 302-to-login proves routing, not that
     * the derived-count closures, the relation manager or the QR modal survive a
     * real request — which is exactly where a table like this breaks.
     */
    public function test_the_source_list_renders_with_a_full_hierarchy_and_live_counts(): void
    {
        $this->actingAs($this->operator());

        $group = ReferralSource::factory()->salesGroup()->create();
        $rep = ReferralSource::factory()->create(['parent_id' => $group->id]);
        $link = ReferralLink::factory()->for($rep, 'source')->create(['code' => 'zz-abcdef']);

        app(RecordReferralClickAction::class)->execute('zz-abcdef', (string) Str::uuid());

        Livewire::test(ListReferralSources::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$group, $rep]);
    }

    public function test_the_create_and_edit_forms_render(): void
    {
        $this->actingAs($this->operator());

        $source = ReferralSource::factory()->create();

        Livewire::test(CreateReferralSource::class)->assertSuccessful();
        Livewire::test(EditReferralSource::class, ['record' => $source->getRouteKey()])->assertSuccessful();
    }

    public function test_the_codes_panel_renders_and_mints_a_code_when_left_blank(): void
    {
        $this->actingAs($this->operator());

        $source = ReferralSource::factory()->create(['code_prefix' => 'acme']);

        Livewire::test(LinksRelationManager::class, [
            'ownerRecord' => $source,
            'pageClass' => EditReferralSource::class,
        ])
            ->assertSuccessful()
            ->callTableAction('create', data: [
                'campaign_name' => 'Spring mailshot',
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $link = $source->links()->sole();
        $this->assertStringStartsWith('acme-', $link->code);
        $this->assertSame('Spring mailshot', $link->campaign_name);
    }

    public function test_an_operator_supplied_code_is_kept_rather_than_replaced(): void
    {
        $this->actingAs($this->operator());

        $source = ReferralSource::factory()->create(['code_prefix' => 'acme']);

        Livewire::test(LinksRelationManager::class, [
            'ownerRecord' => $source,
            'pageClass' => EditReferralSource::class,
        ])
            ->callTableAction('create', data: ['code' => 'SUMMER-2026', 'is_active' => true])
            ->assertHasNoTableActionErrors();

        // Kept, but normalised — codes are compared lowercase everywhere.
        $this->assertSame('summer-2026', $source->links()->sole()->code);
    }

    /**
     * The package gives ancestors a NEGATIVE depth, so a plain `orderBy('depth')`
     * silently returns root-first — same ids, wrong order, and a commission
     * roll-up that walks leaf→root would credit the tree upside down. prx has two
     * adjacent methods with opposite orderings and identical comments; this test
     * is why ours cannot drift the same way.
     */
    public function test_ancestors_come_back_nearest_first(): void
    {
        $root = ReferralSource::factory()->salesGroup()->create(['name' => 'root']);
        $mid = ReferralSource::factory()->salesGroup()->create(['name' => 'mid', 'parent_id' => $root->id]);
        $leaf = ReferralSource::factory()->create(['name' => 'leaf', 'parent_id' => $mid->id]);

        $this->assertSame(
            [$leaf->id, $mid->id, $root->id],
            $leaf->ancestorAndSelfIds(),
            'Nearest first: self, parent, grandparent.',
        );
    }

    /** The memo may not outlive a re-parent, or a moved org reports the old tree. */
    public function test_the_downline_memo_is_dropped_when_the_tree_changes(): void
    {
        $a = ReferralSource::factory()->salesGroup()->create();
        $b = ReferralSource::factory()->salesGroup()->create();
        $moving = ReferralSource::factory()->create(['parent_id' => $a->id]);

        $this->assertContains($moving->id, $a->descendantAndSelfIds());

        $moving->update(['parent_id' => $b->id]);

        $this->assertNotContains($moving->id, $a->fresh()->descendantAndSelfIds());
        $this->assertContains($moving->id, $b->fresh()->descendantAndSelfIds());
    }
}
