<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Enums\Cms\SectionTypeMode;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\SectionRegistry;
use Illuminate\Support\Facades\Auth;

/**
 * Promote a seeded shadow definition to active (the DB row starts winning
 * over its code blueprint) or demote it back to shadow. Promotion should
 * only follow a green golden-parity check for the slug.
 */
class SetFlexibleSectionTypeModeAction
{
    use Transacts;

    public function execute(FlexibleSectionType $type, SectionTypeMode $mode): FlexibleSectionType
    {
        return $this->tx(function () use ($type, $mode) {
            $type->update([
                'mode' => $mode,
                'updated_by' => Auth::id(),
            ]);

            app(SectionRegistry::class)->flush();

            return $type->fresh();
        });
    }
}
