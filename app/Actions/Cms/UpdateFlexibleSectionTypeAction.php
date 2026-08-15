<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Data\Cms\FlexibleSectionTypeData;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\FlexibleSchemaValidator;
use App\Services\Cms\SectionRegistry;
use Illuminate\Support\Facades\Auth;

class UpdateFlexibleSectionTypeAction
{
    use Transacts;

    public function execute(FlexibleSectionType $type, FlexibleSectionTypeData $data): FlexibleSectionType
    {
        return $this->tx(function () use ($type, $data) {
            FlexibleSchemaValidator::validate($data->schema['fields'] ?? []);

            // The slug is immutable — existing page_sections rows reference it
            // as their type string, so it is never updated here.
            $type->update([
                'name' => $data->name,
                'description' => $data->description,
                'icon' => $data->icon,
                'schema' => $data->schema,
                'enabled' => $data->enabled,
                'updated_by' => Auth::id(),
            ]);

            app(SectionRegistry::class)->flush();

            return $type->fresh();
        });
    }
}
