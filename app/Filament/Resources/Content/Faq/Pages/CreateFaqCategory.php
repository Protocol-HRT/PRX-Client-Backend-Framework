<?php

namespace App\Filament\Resources\Content\Faq\Pages;

use App\Filament\Resources\Content\Faq\FaqCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaqCategory extends CreateRecord
{
    protected static string $resource = FaqCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
