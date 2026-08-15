<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Data\Cms\GlobalSectionData;
use App\Models\Cms\GlobalSection;
use Illuminate\Support\Facades\Auth;

class UpdateGlobalSectionAction
{
    use Transacts;

    public function execute(GlobalSection $globalSection, GlobalSectionData $data): GlobalSection
    {
        return $this->tx(function () use ($globalSection, $data) {
            // Slug and type are immutable after creation: the slug is a stable
            // frontend CSS hook and the type defines the data shape every
            // referencing section renders.
            $globalSection->update([
                'name' => $data->name,
                'data' => $data->data,
                'enabled' => $data->enabled,
                'updated_by' => Auth::id(),
            ]);

            return $globalSection->fresh();
        });
    }
}
