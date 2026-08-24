<?php

namespace Tests\Feature\Cms;

use App\Actions\Cms\UpdateFlexibleSectionTypeAction;
use App\Cms\FlexibleDefinition;
use App\Cms\Support\LayoutDefaults;
use App\Data\Cms\FlexibleSectionTypeData;
use App\Enums\Cms\SectionTypeMode;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\SectionRegistry;
use Database\Seeders\SectionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Layout defaults have to SURVIVE, not just be written once. Two ways they
 * were silently lost, both covered here: an admin form save rewriting the
 * schema without them, and a re-seed skipping a row that already exists.
 */
class LayoutDefaultsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        app(SectionRegistry::class)->flush();
    }

    /**
     * The admin form only round-trips schema.fields. Anything else the schema
     * holds must be carried forward, or saving a type from the UI deletes it
     * with no error and no visible symptom.
     */
    public function test_an_admin_save_does_not_strip_keys_the_form_does_not_manage(): void
    {
        $type = FlexibleSectionType::factory()->create([
            'slug' => 'trust-badges',
            'schema' => [
                'fields' => [['key' => 'heading', 'kind' => 'text']],
                'layout_defaults' => ['content_width' => 'wide'],
                'resolvers' => [['op' => 'cast_string', 'path' => 'heading']],
            ],
        ]);

        app(UpdateFlexibleSectionTypeAction::class)->execute($type, new FlexibleSectionTypeData(
            name: 'Trust Badges',
            slug: 'trust-badges',
            description: null,
            icon: 'heroicon-o-squares-2x2',
            schema: ['fields' => [['key' => 'heading', 'kind' => 'text'], ['key' => 'body', 'kind' => 'richtext']]],
            enabled: true,
        ));

        $schema = $type->fresh()->schema;

        $this->assertSame(['content_width' => 'wide'], $schema['layout_defaults']);
        $this->assertSame([['op' => 'cast_string', 'path' => 'heading']], $schema['resolvers']);
        $this->assertCount(2, $schema['fields'], 'The fields the form DID manage should still be updated.');
    }

    public function test_an_explicit_value_from_the_caller_still_wins(): void
    {
        $type = FlexibleSectionType::factory()->create([
            'slug' => 'trust-badges',
            'schema' => [
                'fields' => [['key' => 'heading', 'kind' => 'text']],
                'layout_defaults' => ['content_width' => 'wide'],
            ],
        ]);

        app(UpdateFlexibleSectionTypeAction::class)->execute($type, new FlexibleSectionTypeData(
            name: 'Trust Badges',
            slug: 'trust-badges',
            description: null,
            icon: 'heroicon-o-squares-2x2',
            schema: [
                'fields' => [['key' => 'heading', 'kind' => 'text']],
                'layout_defaults' => ['content_width' => 'narrow'],
            ],
            enabled: true,
        ));

        $this->assertSame(['content_width' => 'narrow'], $type->fresh()->schema['layout_defaults']);
    }

    /**
     * Asserts the schema key is really written, for EVERY seeded row. Parity
     * alone cannot catch this: FlexibleDefinition falls back to the shared
     * table, so a row missing the key still produces an identical envelope
     * and parity stays green while the persistence quietly does nothing.
     */
    public function test_seeding_writes_the_shared_defaults_into_every_shadow_row(): void
    {
        $this->seed(SectionTypeSeeder::class);

        $rows = FlexibleSectionType::query()->get();
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey(
                'layout_defaults',
                $row->schema,
                "Shadow row '{$row->slug}' has no layout_defaults in its schema; it is only working via the code-table fallback.",
            );
            $this->assertSame(LayoutDefaults::for($row->slug), $row->schema['layout_defaults'], $row->slug);
        }
    }

    /**
     * Re-seeding is how a shadow row picks up a change to the table. The
     * parity suite seeds a fresh database, so this update branch never runs
     * there — it needs its own coverage.
     */
    public function test_reseeding_brings_an_existing_shadow_row_forward(): void
    {
        $this->seed(SectionTypeSeeder::class);

        $row = FlexibleSectionType::query()->where('slug', 'text-block')->firstOrFail();
        $schema = $row->schema;
        $schema['layout_defaults'] = ['content_width' => 'narrow'];
        $row->update(['schema' => $schema]);

        $this->seed(SectionTypeSeeder::class);

        $this->assertSame(
            LayoutDefaults::for('text-block'),
            $row->fresh()->schema['layout_defaults'],
        );
    }

    public function test_reseeding_leaves_a_promoted_row_exactly_as_the_operator_saved_it(): void
    {
        $this->seed(SectionTypeSeeder::class);

        $row = FlexibleSectionType::query()->where('slug', 'text-block')->firstOrFail();
        $schema = $row->schema;
        $schema['layout_defaults'] = ['content_width' => 'narrow'];
        $row->update(['schema' => $schema, 'mode' => SectionTypeMode::Active]);

        $this->seed(SectionTypeSeeder::class);

        $this->assertSame(
            ['content_width' => 'narrow'],
            $row->fresh()->schema['layout_defaults'],
            'A promoted row belongs to the operator; the seeder must not reach into it.',
        );
    }

    /**
     * A type that exists only in a client's database is never in the shared
     * table, so the row's own schema is the only place its default can live.
     */
    public function test_a_client_only_type_reads_its_defaults_from_its_row(): void
    {
        $type = FlexibleSectionType::factory()->create([
            'slug' => 'client-only-block',
            'schema' => [
                'fields' => [['key' => 'heading', 'kind' => 'text']],
                'layout_defaults' => ['content_width' => 'xwide'],
            ],
        ]);

        $this->assertSame([], LayoutDefaults::for('client-only-block'));
        $this->assertSame(['content_width' => 'xwide'], (new FlexibleDefinition($type))->layoutDefaults());
    }

    /**
     * @return list<array{0: mixed}>
     */
    public static function malformedSchemas(): array
    {
        return [
            'missing' => [['fields' => []]],
            'null' => [['fields' => [], 'layout_defaults' => null]],
            'a string' => [['fields' => [], 'layout_defaults' => 'wide']],
            'nested junk' => [['fields' => [], 'layout_defaults' => ['content_width' => ['deep' => 'wide']]]],
        ];
    }

    #[DataProvider('malformedSchemas')]
    public function test_a_malformed_schema_degrades_instead_of_throwing(mixed $schema): void
    {
        $type = FlexibleSectionType::factory()->create([
            'slug' => 'client-only-block',
            'schema' => $schema,
        ]);

        $this->assertSame([], (new FlexibleDefinition($type))->layoutDefaults());
    }
}
