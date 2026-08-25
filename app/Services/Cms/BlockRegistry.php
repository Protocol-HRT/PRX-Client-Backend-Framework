<?php

namespace App\Services\Cms;

use App\Cms\Blocks\BlockBlueprint;
use App\Cms\Blocks\TestimonialBlock;
use App\Filament\Support\SectionFormBuilder;
use Filament\Forms\Components\Builder\Block;

/**
 * Lookup point for every SUB-BLOCK type a section can hold as a child.
 *
 * The section-level twin of SectionRegistry, minus its two complications:
 * there is no DB-defined block kind yet (admin-authored flexible types
 * cannot express heterogeneous typed children — FlexibleDefinition fans one
 * shared child schema across `.*.`), and so no shadow/promotion precedence.
 *
 * NOT part of SectionRegistry, deliberately: everything in that registry is
 * offered in the section picker, and a block is not a section. Keeping the
 * namespaces apart is also what stops a block slug from colliding with a
 * section slug of the same name.
 *
 * Registered as a singleton so the map is built once per request.
 */
class BlockRegistry
{
    /**
     * Block blueprint classes, in the order they appear in the admin's
     * "add child" picker.
     *
     * @var list<class-string<BlockBlueprint>>
     */
    private const BLUEPRINTS = [
        TestimonialBlock::class,
    ];

    /** @var array<string, BlockBlueprint>|null */
    private ?array $blocks = null;

    /**
     * All block blueprints keyed by slug.
     *
     * @return array<string, BlockBlueprint>
     */
    public function all(): array
    {
        if ($this->blocks !== null) {
            return $this->blocks;
        }

        $blocks = [];

        foreach (self::BLUEPRINTS as $class) {
            $blueprint = new $class;
            $blocks[$blueprint->type()] = $blueprint;
        }

        return $this->blocks = $blocks;
    }

    public function resolve(string $type): ?BlockBlueprint
    {
        return $this->all()[$type] ?? null;
    }

    public function exists(string $type): bool
    {
        return $this->resolve($type) !== null;
    }

    /**
     * Filament Builder blocks for the given slugs (all of them by default),
     * each already carrying the shared Style / Layout panels.
     *
     * @param  list<string>|null  $only
     * @return array<int, Block>
     */
    public function builderBlocks(?array $only = null): array
    {
        $blocks = [];

        foreach ($this->all() as $slug => $blueprint) {
            if ($only !== null && ! in_array($slug, $only, true)) {
                continue;
            }

            $blocks[] = SectionFormBuilder::blockFor($blueprint);
        }

        return $blocks;
    }
}
