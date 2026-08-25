<?php

namespace App\Cms\Blocks;

use App\Cms\Concerns\DeclaresPresentationKeys;
use App\Cms\Support\BlockDefaults;
use Filament\Schemas\Components\Component;

/**
 * Contract every SUB-BLOCK type implements — a typed child that lives inside
 * a section's `data`, not in a table of its own.
 *
 * Deliberately the same shape as SectionBlueprint, one level down: a block
 * declares defaults, a form schema, the field kinds needing API-side
 * resolution, and (through DeclaresPresentationKeys) which of its keys are
 * presentation rather than content. That symmetry is the point — the
 * transformer recurses with the same four steps it already runs for a
 * section, so a block cannot quietly acquire different rules about media
 * resolution or emptiness.
 *
 * WHY NO TABLE. Children are stored inside `page_sections.data` as Filament
 * Builder writes them — `{type, data}` per item, which is already the
 * envelope the frontend wants. A `parent_id` tree would buy individually
 * addressable and globally reusable children; nothing in the cases driving
 * this needs either, and it would cost a migration plus nested admin UX.
 * The price accepted in exchange: children are invisible to SQL, so any
 * "which sections use X" audit — the palette-deletion guard especially —
 * has to walk the JSON.
 */
abstract class BlockBlueprint
{
    use DeclaresPresentationKeys;

    /**
     * Stable slug stored as the item's `type` discriminator. Must match the
     * Filament Builder block name, which is what writes it.
     */
    abstract public function type(): string;

    abstract public function label(): string;

    public function icon(): string
    {
        return 'heroicon-o-square-2-stack';
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * Initial `data` for a newly added child. Same invariant as a section's:
     * no copy, only nulls, empty arrays and structural flags — which is what
     * lets DeclaresPresentationKeys classify a flagged key automatically.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    /**
     * Design defaults for the shared layout/style knobs this block carries.
     *
     * Keyed by BLOCK slug in its own table rather than LayoutDefaults::MAP,
     * which is keyed by section slug — a block named `testimonial` and a
     * section named `testimonials` are different things and must not share
     * a row.
     *
     * @return array<string, string>
     */
    public function layoutDefaults(): array
    {
        return BlockDefaults::for($this->type());
    }

    /**
     * Filament components edited inside this block, relative to the item's
     * own `data`. The shared Style / Layout panels are appended by
     * SectionFormBuilder::blockFor(); do not repeat them here.
     *
     * @return array<int, Component>
     */
    abstract public function formSchema(): array;

    /**
     * Dot-path => field kind, exactly as SectionBlueprint::fieldKinds(),
     * resolved against this block's own `data`.
     *
     * @return array<string, string>
     */
    public function fieldKinds(): array
    {
        return [];
    }

    /**
     * Per-block payload hook, called after field-kind transformation and
     * before has_content is computed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveData(array $data): array
    {
        return $data;
    }
}
