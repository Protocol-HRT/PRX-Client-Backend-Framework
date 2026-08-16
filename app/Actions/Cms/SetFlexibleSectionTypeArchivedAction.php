<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\SectionRegistry;

/**
 * Archive (stash) or restore a custom section type. Archived types drop out
 * of the section picker so nothing new is authored against them, while every
 * existing section keeps rendering — the soft counterpart to `enabled`,
 * which also stops rendering.
 */
class SetFlexibleSectionTypeArchivedAction
{
    use Transacts;

    public function execute(FlexibleSectionType $type, bool $archived): FlexibleSectionType
    {
        return $this->tx(function () use ($type, $archived) {
            $type->forceFill([
                'archived_at' => $archived ? now() : null,
                'updated_by' => auth()->id(),
            ])->save();

            app(SectionRegistry::class)->flush();

            return $type->fresh();
        });
    }
}
