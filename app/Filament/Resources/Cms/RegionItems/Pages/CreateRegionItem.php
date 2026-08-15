<?php

namespace App\Filament\Resources\Cms\RegionItems\Pages;

use App\Enums\Cms\RegionItemKind;
use App\Filament\Resources\Cms\RegionItems\RegionItemResource;
use App\Services\Cms\SectionRegistry;
use Filament\Resources\Pages\CreateRecord;

class CreateRegionItem extends CreateRecord
{
    protected static string $resource = RegionItemResource::class;

    /**
     * Keep the row's shape consistent with its kind: a section item owns a
     * type + data payload (seeded with the type's defaults), while a
     * global-block or menu item only holds its reference.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['kind'] ?? null) === RegionItemKind::Section->value) {
            $defaults = app(SectionRegistry::class)->resolve($data['section_type'])?->defaults() ?? [];
            $data['data'] = array_replace($defaults, $data['data'] ?? []);
            $data['global_section_id'] = null;
            $data['menu_id'] = null;

            return $data;
        }

        $data['section_type'] = null;
        $data['data'] = null;

        if (($data['kind'] ?? null) === RegionItemKind::GlobalSection->value) {
            $data['menu_id'] = null;
        } else {
            $data['global_section_id'] = null;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
