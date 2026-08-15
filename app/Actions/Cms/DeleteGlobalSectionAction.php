<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Models\Cms\GlobalSection;
use RuntimeException;

class DeleteGlobalSectionAction
{
    use Transacts;

    public function execute(GlobalSection $globalSection): void
    {
        $this->tx(function () use ($globalSection): void {
            $usage = $globalSection->usageCount();

            if ($usage > 0) {
                throw new RuntimeException(
                    "\"{$globalSection->name}\" is referenced by {$usage} section(s) and cannot be deleted. Detach those sections or disable this block instead.",
                );
            }

            $globalSection->delete();
        });
    }
}
