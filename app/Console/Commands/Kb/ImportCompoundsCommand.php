<?php

namespace App\Console\Commands\Kb;

use App\Enums\Kb\RegulatoryStatus;
use App\Models\Catalog\Ingredient;
use App\Models\Kb\Compound;
use App\Services\Kb\MonographMarkdown;
use App\Services\Kb\SqlDumpReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports compound monographs from a prescribe-rx `protocol_compounds` dump.
 *
 * Two files, two responsibilities. The **dump** is content: prose, references,
 * provenance. The **curation sidecar** is Atlas's editorial judgement about
 * that content — display names, URL slugs, whether a row is actually a peptide,
 * what its regulatory standing is, and which rows are the same compound written
 * twice. Keeping them apart is what lets this command ship in the generic
 * backend while the decisions live with the deployment that made them.
 *
 *     php artisan kb:import-compounds \
 *         /var/www/html/atlas-protocol-web/docs/prx-peptide-kb.sql \
 *         --curation=/var/www/html/atlas-protocol-web/scripts/atlas-kb-curation.json \
 *         --dry-run
 *
 * **Import is not publication.** Nothing here sets `is_published`, and the
 * model's `published()` scope additionally demands a regulatory status — which
 * an operator has to confirm per compound, because the imported value is a
 * seeded suggestion. A hundred summarised monographs going live unread is the
 * failure this is built to prevent, so if you find yourself adding a
 * `--publish` flag, that is the decision you are actually making.
 *
 * Re-runnable. Rows are keyed on `(source_system, source_ref)`, so a second run
 * updates rather than duplicates. It will NOT silently overwrite a monograph a
 * human has touched: a row with a reviewer is skipped unless `--force` says
 * otherwise, because the seed is a moving target and losing a review pass to a
 * re-import is expensive in a way that re-running the command is not.
 */
class ImportCompoundsCommand extends Command
{
    protected $signature = 'kb:import-compounds
        {path : Path to the .sql dump containing protocol_compounds}
        {--curation= : Path to the curation JSON sidecar}
        {--table=protocol_compounds : Table name inside the dump}
        {--dry-run : Report what would change without writing}
        {--force : Overwrite monographs that already have a reviewer}';

    protected $description = 'Import compound monographs from a prescribe-rx SQL dump into the knowledge base';

    /** Source columns copied straight across, after markdown conversion. */
    private const PROSE_FIELDS = [
        'description',
        'overview',
        'mechanism_of_action',
        'pharmacology',
        'clinical_evidence',
        'dosing_guidelines',
        'safety_profile',
        'patient_summary',
    ];

    public function handle(SqlDumpReader $reader, MonographMarkdown $markdown): int
    {
        $path = (string) $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $curation = $this->readCuration();
            $source = $this->readDump($reader, $path);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Read %d rows from %s%s',
            count($source),
            basename($path),
            $dryRun ? ' — DRY RUN, nothing will be written' : ''
        ));

        $merged = $this->applyMerges($source, $curation['merge'] ?? []);

        if (($unknown = $this->reportCoverage($merged, $curation['compounds'])) > 0 && ! $this->option('force')) {
            $this->error("{$unknown} row(s) have no curation entry. Add them, or re-run with --force to import them unclassified.");

            return self::FAILURE;
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'linked' => 0];
        $skippedNames = [];

        foreach ($merged as $sourceName => $row) {
            $entry = $curation['compounds'][$sourceName] ?? null;

            $attributes = $this->buildAttributes($row, $entry, $markdown, $curation['source_system']);

            $existing = Compound::withTrashed()
                ->where('source_system', $attributes['source_system'])
                ->where('source_ref', $attributes['source_ref'])
                ->first();

            // A reviewer is the marker of human work on a page. It is optional
            // for publication now, but where one exists it means somebody read
            // the monograph — and a re-import must not silently discard that.
            if ($existing?->reviewed_by_profile_id !== null && ! $this->option('force')) {
                $stats['skipped']++;
                $skippedNames[] = $existing->name;

                continue;
            }

            if ($ingredientId = $this->matchIngredient($attributes['slug'], $attributes['name'])) {
                $attributes['ingredient_id'] = $ingredientId;
                $stats['linked']++;
            }

            if ($dryRun) {
                $stats[$existing ? 'updated' : 'created']++;

                continue;
            }

            DB::transaction(function () use ($existing, $attributes, &$stats): void {
                if ($existing) {
                    // slug is excluded on update: it is the public URL, and a
                    // curation edit that moves it should be a deliberate act
                    // with a redirect behind it, not a side effect of a re-import.
                    $existing->fill(array_diff_key($attributes, ['slug' => null]));
                    $existing->save();
                    $stats['updated']++;

                    return;
                }

                Compound::create($attributes);
                $stats['created']++;
            });
        }

        $this->newLine();
        $this->table(
            ['Created', 'Updated', 'Skipped (reviewed)', 'Linked to a product ingredient'],
            [[$stats['created'], $stats['updated'], $stats['skipped'], $stats['linked']]]
        );

        if ($skippedNames !== []) {
            $this->components->warn('Left alone because a reviewer is attached: '.implode(', ', $skippedNames));
            $this->line('  Re-run with --force to overwrite them with the dump.');
        }

        $this->components->warn('Every imported monograph is UNPUBLISHED. None are visible on the public API until an operator confirms a regulatory status and publishes them in the admin.');

        return self::SUCCESS;
    }

    /**
     * @return array{source_system: string, merge: list<array{primary: string, absorb: list<string>}>, compounds: array<string, array<string, mixed>>}
     */
    private function readCuration(): array
    {
        $path = $this->option('curation');

        if (blank($path)) {
            throw new RuntimeException('--curation is required: without it every row imports with no display name, no slug, and no is_peptide flag.');
        }

        if (! is_readable((string) $path)) {
            throw new RuntimeException("Curation file not readable: {$path}");
        }

        $data = json_decode((string) file_get_contents((string) $path), true);

        if (! is_array($data) || ! isset($data['compounds']) || ! is_array($data['compounds'])) {
            throw new RuntimeException("Curation file has no `compounds` map: {$path}");
        }

        $data['source_system'] = (string) ($data['source_system'] ?? 'unknown');

        return $data;
    }

    /**
     * @return array<string, array<string, string|null>> keyed by generic_name
     */
    private function readDump(SqlDumpReader $reader, string $path): array
    {
        $rows = [];

        foreach ($reader->rows($path, (string) $this->option('table')) as $row) {
            // generic_name is the curation key and id is the re-import key.
            // Without either, a row cannot be classified or reconciled, and
            // guessing at that is worse than refusing.
            foreach (['generic_name', 'id'] as $required) {
                if (blank($row[$required] ?? null)) {
                    throw new RuntimeException("Dump row has no {$required}; is --table correct?");
                }
            }

            // Keying by generic_name is what lets the curation file address a
            // row, but the source's unique index is on that column, so a
            // collision means the dump is not what we think it is. Say so
            // rather than letting one row quietly replace another.
            if (isset($rows[$row['generic_name']])) {
                $this->components->warn("Duplicate generic_name in the dump: '{$row['generic_name']}' — the later row wins.");
            }

            $rows[$row['generic_name']] = $row;
        }

        if ($rows === []) {
            throw new RuntimeException('No rows found. Check the path and --table.');
        }

        return $rows;
    }

    /**
     * Collapses the duplicate pairs named in the curation file.
     *
     * The primary's prose wins whole — mixing two independently generated
     * monographs field by field would produce a document that reads as one
     * voice but is two, and no reviewer could tell which sentence came from
     * where. Only the absorbed row's aliases carry over, because those are
     * facts rather than prose.
     *
     * @param  array<string, array<string, string|null>>  $rows
     * @param  list<array{primary: string, absorb: list<string>}>  $merges
     * @return array<string, array<string, string|null>>
     */
    private function applyMerges(array $rows, array $merges): array
    {
        foreach ($merges as $merge) {
            $primary = $merge['primary'] ?? null;

            if ($primary === null || ! isset($rows[$primary])) {
                $this->components->warn("Merge skipped — no source row named '{$primary}'.");

                continue;
            }

            foreach ($merge['absorb'] ?? [] as $absorbed) {
                if (! isset($rows[$absorbed])) {
                    $this->components->warn("Merge skipped — no source row named '{$absorbed}'.");

                    continue;
                }

                foreach (['brand_names', 'synonyms'] as $field) {
                    $rows[$primary][$field] = json_encode(array_values(array_unique(array_merge(
                        $this->decodeList($rows[$primary][$field] ?? null),
                        $this->decodeList($rows[$absorbed][$field] ?? null),
                    ))));
                }

                unset($rows[$absorbed]);
                $this->line("  merged <fg=gray>{$absorbed}</> into <fg=cyan>{$primary}</>");
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, array<string, string|null>>  $rows
     * @param  array<string, mixed>  $curated
     */
    private function reportCoverage(array $rows, array $curated): int
    {
        $unknown = array_diff(array_keys($rows), array_keys($curated));
        $stale = array_diff(array_keys($curated), array_keys($rows));

        foreach ($unknown as $name) {
            $this->components->warn("No curation entry for '{$name}' — it would import unclassified.");
        }

        foreach ($stale as $name) {
            $this->components->warn("Curation entry '{$name}' matches no row in the dump.");
        }

        return count($unknown);
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, mixed>|null  $entry
     * @return array<string, mixed>
     */
    private function buildAttributes(array $row, ?array $entry, MonographMarkdown $markdown, string $sourceSystem): array
    {
        $name = $entry['name'] ?? $this->titleCase((string) $row['generic_name']);

        $attributes = [
            'name' => $name,
            'slug' => $entry['slug'] ?? str($name)->slug()->value(),
            'is_peptide' => (bool) ($entry['is_peptide'] ?? false),
            'regulatory_status' => isset($entry['regulatory_status'])
                ? RegulatoryStatus::tryFrom((string) $entry['regulatory_status'])?->value
                : null,
            // Every optional column is read defensively. The dump is someone
            // else's export and its column list is not ours to depend on — a
            // regenerated one that drops `evidence_tier` should import without
            // that field, not abort halfway through a hundred rows.
            'compound_class' => ($row['compound_class'] ?? null) ?: null,
            'brand_names' => $this->decodeList($row['brand_names'] ?? null),
            'synonyms' => $this->decodeList($row['synonyms'] ?? null),
            'clinical_references' => $this->decodeList($row['clinical_references'] ?? null),
            'evidence_tier' => ($row['evidence_tier'] ?? null) ?: null,
            'evidence_score' => ($row['evidence_score'] ?? null) !== null ? (float) $row['evidence_score'] : null,
            'source_system' => $sourceSystem,
            'source_ref' => $row['id'],
            // The dump is the provider's own export, so its row id IS the
            // provider's id for this compound. Backfilling it now means a
            // future API sync has something to key on without a second pass
            // over 100 rows to work out which is which.
            'provider_compound_id' => $row['id'],
            'content_model' => ($row['content_model'] ?? null) ?: null,
            'content_generated_at' => ($row['content_generated_at'] ?? null) ?: null,
            // The retrieval counts behind this monograph. `source_preclusion_count`
            // is skipped on purpose — it is 100 on every source row, which is a
            // cap, not a count.
            'source_document_count' => ($row['source_document_count'] ?? null) ?: null,
            'source_dosing_count' => ($row['source_dosing_count'] ?? null) ?: null,
        ];

        foreach (self::PROSE_FIELDS as $field) {
            $attributes[$field] = $markdown->toHtml($row[$field] ?? null);
        }

        // A meta description that is the first ~155 characters of the summary
        // beats an empty one and beats a generated one nobody read. The admin's
        // SEO generator can replace it per row; this is the floor, not the goal.
        $attributes['meta_description'] = $markdown->toPlainText($row['description'] ?? null, 155);

        return $attributes;
    }

    /**
     * Links the monograph to the catalog ingredient of the same compound, when
     * this install sells it.
     *
     * Slug first, then an exact name match, and nothing fuzzier: a near-match
     * here would attach a monograph to the wrong product, which is worse than
     * no link at all. Eight of the ten seeded ingredients match exactly.
     */
    private function matchIngredient(string $slug, string $name): ?int
    {
        return Ingredient::query()
            ->where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->value('id');
    }

    /** @return list<string> */
    private function decodeList(?string $json): array
    {
        $decoded = json_decode((string) ($json ?? '[]'), true);

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    private function titleCase(string $value): string
    {
        return str($value)->replace(['-', '_'], ' ')->title()->value();
    }
}
