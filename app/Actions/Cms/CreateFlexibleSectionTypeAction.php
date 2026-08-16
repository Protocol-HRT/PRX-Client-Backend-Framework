<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Data\Cms\FlexibleSectionTypeData;
use App\Models\Cms\FlexibleSectionType;
use App\Services\Cms\FlexibleSchemaValidator;
use App\Services\Cms\SectionRegistry;
use App\Services\Cms\SectionResolverOps;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CreateFlexibleSectionTypeAction
{
    use Transacts;

    public function execute(FlexibleSectionTypeData $data): FlexibleSectionType
    {
        return $this->tx(function () use ($data) {
            if (in_array($data->slug, app(SectionRegistry::class)->reservedSlugs(), true)) {
                throw new InvalidArgumentException("The slug '{$data->slug}' is reserved by a built-in section type.");
            }

            FlexibleSchemaValidator::validate($data->schema['fields'] ?? []);
            SectionResolverOps::validate(array_values($data->schema['resolvers'] ?? []));

            $userId = Auth::id();

            $type = FlexibleSectionType::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'icon' => $data->icon,
                'schema' => $data->schema,
                'enabled' => $data->enabled,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            app(SectionRegistry::class)->flush();

            return $type;
        });
    }
}
