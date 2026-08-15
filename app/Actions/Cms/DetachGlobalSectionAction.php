<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Models\PageSection;
use RuntimeException;

/**
 * Turns a global-block reference into an independent copy: the section keeps
 * rendering the same content but stops following future edits to the global.
 */
class DetachGlobalSectionAction
{
    use Transacts;

    public function execute(PageSection $section): PageSection
    {
        return $this->tx(function () use ($section) {
            $global = $section->globalSection;

            if ($global === null) {
                throw new RuntimeException('This section does not reference a global block.');
            }

            $section->update([
                'type' => $global->type,
                'data' => $global->data,
                'global_section_id' => null,
            ]);

            return $section->fresh();
        });
    }
}
