<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Data\Cms\FlexibleSectionTypeData;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\FlexibleSchemaValidator;
use App\Services\Cms\SectionRegistry;
use App\Services\Cms\SectionResolverOps;
use Illuminate\Support\Facades\Auth;

class UpdateFlexibleSectionTypeAction
{
    use Transacts;

    public function execute(FlexibleSectionType $type, FlexibleSectionTypeData $data): FlexibleSectionType
    {
        return $this->tx(function () use ($type, $data) {
            // The admin form only round-trips schema.fields, so every OTHER
            // stored key survives by being carried forward unless the caller
            // explicitly supplies one. Written as a sweep rather than a list
            // of known keys (`resolvers`, `layout_defaults`, …) because the
            // failure is silent: a key nobody remembered to name here is
            // deleted by the next form save, and nothing errors.
            $schema = $data->schema;

            foreach ($type->schema ?? [] as $key => $value) {
                if ($key !== 'fields' && ! array_key_exists($key, $schema)) {
                    $schema[$key] = $value;
                }
            }

            FlexibleSchemaValidator::validate($schema['fields'] ?? []);
            SectionResolverOps::validate(array_values($schema['resolvers'] ?? []));

            // The slug is immutable — existing page_sections rows reference it
            // as their type string, so it is never updated here.
            $type->update([
                'name' => $data->name,
                'description' => $data->description,
                'icon' => $data->icon,
                'schema' => $schema,
                'enabled' => $data->enabled,
                'updated_by' => Auth::id(),
            ]);

            app(SectionRegistry::class)->flush();

            return $type->fresh();
        });
    }
}
