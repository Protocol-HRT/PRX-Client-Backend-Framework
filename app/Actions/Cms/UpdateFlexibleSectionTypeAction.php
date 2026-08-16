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
            // The admin form only round-trips schema.fields — carry any
            // stored resolver pipeline forward unless the caller explicitly
            // provides one, or a form save would silently strip it.
            $schema = $data->schema;

            if (! array_key_exists('resolvers', $schema) && isset($type->schema['resolvers'])) {
                $schema['resolvers'] = $type->schema['resolvers'];
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
