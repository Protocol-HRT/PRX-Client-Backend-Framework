<?php

namespace App\Actions\Cms;

use App\Actions\Concerns\Transacts;
use App\Data\Cms\GlobalSectionData;
use App\Models\Cms\GlobalSection;
use App\Services\Cms\SectionRegistry;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CreateGlobalSectionAction
{
    use Transacts;

    public function __construct(private readonly SectionRegistry $registry) {}

    public function execute(GlobalSectionData $data): GlobalSection
    {
        return $this->tx(function () use ($data) {
            if (! $this->registry->exists($data->type)) {
                throw new InvalidArgumentException("Unknown section type \"{$data->type}\".");
            }

            $userId = Auth::id();

            return GlobalSection::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'type' => $data->type,
                'data' => $data->data,
                'enabled' => $data->enabled,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }
}
