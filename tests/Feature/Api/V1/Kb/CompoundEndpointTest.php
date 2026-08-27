<?php

namespace Tests\Feature\Api\V1\Kb;

use App\Enums\Kb\RegulatoryStatus;
use App\Models\Catalog\Ingredient;
use App\Models\Catalog\Product;
use App\Models\Content\Profile;
use App\Models\Kb\Compound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The publication gate is the point of these tests.
 *
 * A monograph is summarised from a clinical corpus, long, confident, and about
 * medicine. Two rules keep it honest, and they are asymmetric on purpose:
 *
 * - **A regulatory status is required.** Without one the public page renders no
 *   not-approved notice and the structured data carries no `legalStatus`, so a
 *   research compound reads exactly like an approved medicine.
 * - **A clinician reviewer is NOT required**, and the test below pins that. It
 *   was required once; requiring it across ~100 pages manufactures bylines
 *   rather than reviews, and a byline asserting a review that did not happen is
 *   worse on medical content than no byline at all.
 *
 * Both live in `Compound::published()` rather than in each controller, so
 * neither can be forgotten — or quietly reinstated — in one place.
 */
class CompoundEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_publicly_accessible(): void
    {
        $this->getJson('/api/v1/kb/compounds')->assertOk();
    }

    public function test_published_compound_appears_in_the_index(): void
    {
        $compound = Compound::factory()->live()->create(['name' => 'BPC-157', 'slug' => 'bpc-157']);

        $this->getJson('/api/v1/kb/compounds')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $compound->slug);
    }

    public function test_unpublished_compound_is_hidden_from_both_routes(): void
    {
        $compound = Compound::factory()->create(['slug' => 'draft-peptide']);

        $this->getJson('/api/v1/kb/compounds')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/kb/compounds/{$compound->slug}")->assertNotFound();
    }

    /**
     * The reviewer is OPTIONAL, and this pins it against being reinstated.
     *
     * The content is summarised from the operator's clinical literature, not
     * authored by one of their providers. Requiring a provider's name before
     * ~100 pages can publish makes "attach one doctor to all of them" the path
     * of least resistance, which manufactures a false attribution of clinical
     * review on medical content.
     */
    public function test_a_compound_publishes_without_a_reviewer(): void
    {
        $compound = Compound::factory()->create([
            'slug' => 'no-reviewer',
            'is_published' => true,
            'reviewed_by_profile_id' => null,
            'regulatory_status' => RegulatoryStatus::ResearchOnly,
        ]);

        $this->getJson('/api/v1/kb/compounds')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $compound->slug);

        $this->getJson("/api/v1/kb/compounds/{$compound->slug}")
            ->assertOk()
            ->assertJsonPath('data.reviewed_by', null);
    }

    /** Provenance is a trust signal here, not a disclaimer — so it ships on both routes. */
    public function test_provenance_carries_the_source_count(): void
    {
        $compound = Compound::factory()->live()->create([
            'slug' => 'bpc-157',
            'source_document_count' => 43,
        ]);

        $this->getJson('/api/v1/kb/compounds')
            ->assertOk()
            ->assertJsonPath('data.0.provenance.source_count', 43);

        $this->getJson("/api/v1/kb/compounds/{$compound->slug}")
            ->assertOk()
            ->assertJsonPath('data.provenance.source_count', 43);
    }

    /** Zero sources is "unrecorded", not "no evidence" — the block is omitted, not zeroed. */
    public function test_a_zero_source_count_is_reported_as_absent(): void
    {
        Compound::factory()->live()->create(['slug' => 'bpc-157', 'source_document_count' => 0]);

        $this->getJson('/api/v1/kb/compounds')
            ->assertOk()
            ->assertJsonPath('data.0.provenance.source_count', null);
    }

    /**
     * The second half of the gate, and the less obvious one. A monograph with
     * a reviewer but no regulatory status renders NO not-approved notice and
     * carries no `legalStatus` in its structured data — so a research chemical
     * would go live reading exactly like an approved medicine.
     */
    public function test_published_compound_without_a_regulatory_status_is_hidden(): void
    {
        $compound = Compound::factory()->live()->reviewed()->create([
            'slug' => 'no-status',
            'regulatory_status' => null,
        ]);

        $this->getJson('/api/v1/kb/compounds')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/kb/compounds/{$compound->slug}")->assertNotFound();
    }

    public function test_index_returns_only_peptides_by_default(): void
    {
        Compound::factory()->live()->create(['name' => 'BPC-157', 'slug' => 'bpc-157']);
        Compound::factory()->live()->notPeptide()->create(['name' => 'Amoxicillin', 'slug' => 'amoxicillin']);

        $this->getJson('/api/v1/kb/compounds')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'bpc-157');
    }

    public function test_peptides_only_can_be_turned_off(): void
    {
        Compound::factory()->live()->create(['slug' => 'bpc-157']);
        Compound::factory()->live()->notPeptide()->create(['slug' => 'amoxicillin']);

        $this->getJson('/api/v1/kb/compounds?peptides_only=0')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * An unrecognised status must match nothing. Ignoring it would return the
     * whole list, which a caller reads as "every compound has that status".
     */
    public function test_unknown_regulatory_status_filter_matches_nothing(): void
    {
        Compound::factory()->live()->create(['slug' => 'bpc-157']);

        $this->getJson('/api/v1/kb/compounds?regulatory_status=not-a-real-status')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_regulatory_status_filter_matches(): void
    {
        Compound::factory()->live()->create(['slug' => 'bpc-157']);
        Compound::factory()->live()->create([
            'slug' => 'semaglutide',
            'regulatory_status' => RegulatoryStatus::FdaApproved,
        ]);

        $this->getJson('/api/v1/kb/compounds?regulatory_status=fda_approved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'semaglutide');
    }

    /** The prose is ~28,000 characters per row; an index that shipped it would be megabytes. */
    public function test_index_omits_the_monograph_prose(): void
    {
        Compound::factory()->live()->create(['slug' => 'bpc-157']);

        $this->getJson('/api/v1/kb/compounds')
            ->assertOk()
            ->assertJsonMissingPath('data.0.monograph');
    }

    public function test_detail_returns_the_full_monograph_and_regulatory_object(): void
    {
        $compound = Compound::factory()->live()->reviewed()->create(['slug' => 'bpc-157']);

        $this->getJson("/api/v1/kb/compounds/{$compound->slug}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'name', 'slug', 'is_peptide',
                    'regulatory' => ['value', 'label', 'description', 'is_approved_for_human_use'],
                    'reviewed_by' => ['name', 'credentials'],
                    'monograph' => [
                        'overview', 'mechanism_of_action', 'pharmacology',
                        'clinical_evidence', 'dosing_guidelines', 'safety_profile', 'patient_summary',
                    ],
                    'seo', 'provenance',
                ],
            ])
            ->assertJsonPath('data.regulatory.value', RegulatoryStatus::ResearchOnly->value)
            ->assertJsonPath('data.regulatory.is_approved_for_human_use', false);
    }

    public function test_detail_lists_the_products_that_contain_the_compound(): void
    {
        $ingredient = Ingredient::create(['name' => 'BPC-157', 'slug' => 'bpc-157']);
        $product = Product::factory()->create(['name' => 'BPC-157 5mg', 'slug' => 'bpc-157-5mg']);
        $ingredient->products()->attach($product->id);

        $compound = Compound::factory()->live()->create([
            'slug' => 'bpc-157',
            'ingredient_id' => $ingredient->id,
        ]);

        $this->getJson("/api/v1/kb/compounds/{$compound->slug}")
            ->assertOk()
            ->assertJsonPath('data.products.0.slug', 'bpc-157-5mg');
    }

    public function test_unknown_slug_is_a_404(): void
    {
        $this->getJson('/api/v1/kb/compounds/nothing-here')->assertNotFound();
    }

    /**
     * published_at is derived from the flag, so an unpublish cannot leave a
     * stale date behind for the sitemap to emit.
     */
    public function test_unpublishing_clears_the_published_date(): void
    {
        $compound = Compound::factory()->live()->create(['slug' => 'bpc-157']);
        $this->assertNotNull($compound->fresh()->published_at);

        $compound->update(['is_published' => false]);

        $this->assertNull($compound->fresh()->published_at);
    }

    public function test_search_matches_a_synonym(): void
    {
        Compound::factory()->live()->create([
            'name' => 'MGF (Mechano Growth Factor)',
            'slug' => 'mgf',
            'synonyms' => ['Mechano Growth Factor'],
        ]);

        $this->getJson('/api/v1/kb/compounds?search=Mechano')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_reviewer_credentials_reach_the_payload(): void
    {
        $profile = Profile::factory()->create(['name' => 'Jane Roe', 'credentials' => 'PharmD']);
        $compound = Compound::factory()->live()->create([
            'slug' => 'bpc-157',
            'reviewed_by_profile_id' => $profile->id,
        ]);

        $this->getJson("/api/v1/kb/compounds/{$compound->slug}")
            ->assertOk()
            ->assertJsonPath('data.reviewed_by.name', 'Jane Roe')
            ->assertJsonPath('data.reviewed_by.credentials', 'PharmD');
    }
}
