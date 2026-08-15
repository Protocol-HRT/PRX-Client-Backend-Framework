<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\SectionRegistry;
use RuntimeException;

class DeleteFlexibleSectionTypeAction
{
    use Transacts;

    public function execute(FlexibleSectionType $type): void
    {
        $this->tx(function () use ($type) {
            $usageCount = $type->usageCount();

            if ($usageCount > 0) {
                throw new RuntimeException("'{$type->name}' is in use by {$usageCount} section(s) and cannot be deleted. Disable it instead.");
            }

            $type->delete();

            app(SectionRegistry::class)->flush();
        });
    }
}
