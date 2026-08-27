<?php

namespace Tests\Feature\Kb;

use App\Enums\Kb\RegulatoryStatus;
use App\Models\Catalog\Ingredient;
use App\Models\Content\Profile;
use App\Models\Kb\Compound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the four things the import must never get wrong: it must not publish,
 * it must not duplicate on a second run, it must not overwrite reviewed work,
 * and it must not leave markdown in a field the frontend renders as HTML.
 */
class ImportCompoundsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dump;

    private string $curation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dump = tempnam(sys_get_temp_dir(), 'kbdump').'.sql';
        $this->curation = tempnam(sys_get_temp_dir(), 'kbcur').'.json';

        // Two INSERT statements, one carrying two rows — the shape that makes a
        // naive tuple-count regex report more rows than the dump contains.
        file_put_contents($this->dump, <<<'SQL'
INSERT INTO `protocol_compounds` (`id`, `generic_name`, `compound_class`, `overview`, `brand_names`, `synonyms`, `description`, `content_model`, `source_document_count`, `source_dosing_count`, `evidence_tier`, `evidence_score`) VALUES
('uuid-1', 'bpc-157', 'Healing Peptide', '## What it is\n\nA **pentadecapeptide**.\n\n| Week | Dose |\n|------|------|\n| 1-4 | 250 mcg |\n', '["BPC-157"]', '["BPC157"]', 'A synthetic peptide.', 'test-model', 43, 22, NULL, NULL),
('uuid-2', 'aod 9604', 'Peptide', '## Overview\n\nA fragment.', '[]', '["AOD 9604"]', 'A fragment of hGH.', 'test-model', 0, 0, NULL, NULL);
INSERT INTO `protocol_compounds` (`id`, `generic_name`, `compound_class`, `overview`, `brand_names`, `synonyms`, `description`, `content_model`, `source_document_count`, `source_dosing_count`, `evidence_tier`, `evidence_score`) VALUES
('uuid-3', 'aod-9604', 'Anti-Obesity Peptide', '## Overview\n\nDuplicate row.', '["AOD-9604","AOD9604"]', '["AOD 9604"]', 'Duplicate.', 'test-model', 5, 5, NULL, NULL);
SQL);

        file_put_contents($this->curation, json_encode([
            'source_system' => 'test-source',
            'merge' => [['primary' => 'aod 9604', 'absorb' => ['aod-9604']]],
            'compounds' => [
                'bpc-157' => ['name' => 'BPC-157', 'slug' => 'bpc-157', 'is_peptide' => true, 'regulatory_status' => 'research_only'],
                'aod 9604' => ['name' => 'AOD-9604', 'slug' => 'aod-9604', 'is_peptide' => true, 'regulatory_status' => 'research_only'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->dump);
        @unlink($this->curation);

        parent::tearDown();
    }

    private function import(array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('kb:import-compounds', array_merge([
            'path' => $this->dump,
            '--curation' => $this->curation,
        ], $options));
    }

    public function test_it_imports_and_deduplicates(): void
    {
        $this->import()->assertSuccessful();

        // Three source rows, one merge pair, two compounds.
        $this->assertSame(2, Compound::count());
        $this->assertNotNull(Compound::where('slug', 'aod-9604')->first());
        $this->assertNull(Compound::where('slug', 'aod-9604-2')->first());
    }

    public function test_the_absorbed_row_donates_its_aliases_but_not_its_prose(): void
    {
        $this->import()->assertSuccessful();

        $aod = Compound::where('slug', 'aod-9604')->firstOrFail();

        $this->assertContains('AOD-9604', $aod->brand_names);
        $this->assertContains('AOD9604', $aod->brand_names);
        $this->assertStringNotContainsString('Duplicate row', (string) $aod->overview);
        $this->assertStringContainsString('A fragment', (string) $aod->overview);
    }

    public function test_markdown_becomes_prose_html_including_tables(): void
    {
        $this->import()->assertSuccessful();

        $bpc = Compound::where('slug', 'bpc-157')->firstOrFail();

        $this->assertStringContainsString('<h3>What it is</h3>', (string) $bpc->overview);
        $this->assertStringContainsString('<strong>pentadecapeptide</strong>', (string) $bpc->overview);
        $this->assertStringContainsString('<table>', (string) $bpc->overview);
        $this->assertStringNotContainsString('## ', (string) $bpc->overview);
        $this->assertStringNotContainsString('**', (string) $bpc->overview);
        $this->assertStringNotContainsString('|---', (string) $bpc->overview);
    }

    /**
     * The page supplies each field's <h2>; the prose inside it must start at
     * h3 or the source's own headings become siblings of the section that
     * contains them.
     *
     * The `h4`-not-`h5` assertion is the one that matters: demoting with two
     * chained regexes (h1-2 → h3, then h3-6 → h4) pushes an original `##`
     * down twice, because the second pass sees what the first just wrote.
     */
    public function test_source_headings_are_demoted_one_level_exactly_once(): void
    {
        $this->import()->assertSuccessful();

        $bpc = Compound::where('slug', 'bpc-157')->firstOrFail();

        $this->assertStringContainsString('<h3>What it is</h3>', (string) $bpc->overview);
        $this->assertStringNotContainsString('<h2', (string) $bpc->overview);
        $this->assertStringNotContainsString('<h4', (string) $bpc->overview);
    }

    public function test_nothing_is_published_and_nothing_is_reviewed(): void
    {
        $this->import()->assertSuccessful();

        $this->assertSame(0, Compound::where('is_published', true)->count());
        $this->assertSame(0, Compound::whereNotNull('reviewed_by_profile_id')->count());
        $this->assertSame(0, Compound::query()->published()->count());
    }

    public function test_curation_supplies_the_classification(): void
    {
        $this->import()->assertSuccessful();

        $bpc = Compound::where('slug', 'bpc-157')->firstOrFail();

        $this->assertTrue($bpc->is_peptide);
        $this->assertSame(RegulatoryStatus::ResearchOnly, $bpc->regulatory_status);
        $this->assertSame('test-source', $bpc->source_system);
        $this->assertSame('uuid-1', $bpc->source_ref);
    }

    /**
     * The provider's id is what an API sync will key on once prescribe-rx
     * exposes compounds. Backfilling it from the dump now means the sync does
     * not need a second pass to work out which local row is which.
     */
    public function test_it_backfills_the_provider_id_from_the_dump(): void
    {
        $this->import()->assertSuccessful();

        $bpc = Compound::where('slug', 'bpc-157')->firstOrFail();

        $this->assertSame('uuid-1', $bpc->provider_compound_id);
        // Same value as source_ref today; they answer different questions and
        // diverge the moment a second content source exists.
        $this->assertSame($bpc->source_ref, $bpc->provider_compound_id);
    }

    /**
     * The retrieval counts are what the public page's provenance block leads
     * with. A ZERO must import as null, not 0 — "0 sources" reads as a
     * failure, and "unrecorded" is what actually happened.
     */
    public function test_it_imports_the_source_counts_and_maps_zero_to_null(): void
    {
        $this->import()->assertSuccessful();

        $bpc = Compound::where('slug', 'bpc-157')->firstOrFail();
        $this->assertSame(43, $bpc->source_document_count);
        $this->assertSame(22, $bpc->source_dosing_count);

        // The surviving row of the merge pair carries 0 in the dump.
        $aod = Compound::where('slug', 'aod-9604')->firstOrFail();
        $this->assertNull($aod->source_document_count);
        $this->assertNull($aod->source_dosing_count);
        $this->assertNull($aod->sourceCount());
    }

    public function test_a_second_run_updates_rather_than_duplicating(): void
    {
        $this->import()->assertSuccessful();
        $this->import()->assertSuccessful();

        $this->assertSame(2, Compound::count());
    }

    /** The seed is a moving target; a re-import must not cost a review pass. */
    public function test_a_reviewed_monograph_is_left_alone_unless_forced(): void
    {
        $this->import()->assertSuccessful();

        $bpc = Compound::where('slug', 'bpc-157')->firstOrFail();
        $bpc->update([
            'overview' => '<p>Rewritten by a human.</p>',
            'reviewed_by_profile_id' => Profile::factory()->create()->id,
            'reviewed_at' => now(),
        ]);

        $this->import()->assertSuccessful();
        $this->assertSame('<p>Rewritten by a human.</p>', Compound::where('slug', 'bpc-157')->first()->overview);

        $this->import(['--force' => true])->assertSuccessful();
        $this->assertStringContainsString('<h3>What it is</h3>', (string) Compound::where('slug', 'bpc-157')->first()->overview);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->import(['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Compound::count());
    }

    public function test_it_links_a_compound_to_a_matching_catalog_ingredient(): void
    {
        $ingredient = Ingredient::create(['name' => 'BPC-157', 'slug' => 'bpc-157']);

        $this->import()->assertSuccessful();

        $this->assertSame($ingredient->id, Compound::where('slug', 'bpc-157')->first()->ingredient_id);
    }

    public function test_it_refuses_to_run_without_a_curation_file(): void
    {
        $this->artisan('kb:import-compounds', ['path' => $this->dump])->assertFailed();

        $this->assertSame(0, Compound::count());
    }

    /** An uncurated row would import with no display name, slug or peptide flag. */
    public function test_it_refuses_when_a_dump_row_has_no_curation_entry(): void
    {
        $curation = json_decode(file_get_contents($this->curation), true);
        unset($curation['compounds']['bpc-157']);
        file_put_contents($this->curation, json_encode($curation));

        $this->import()->assertFailed();

        $this->assertSame(0, Compound::count());
    }
}
