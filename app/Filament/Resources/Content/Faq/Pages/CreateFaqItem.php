<?php

namespace App\Filament\Resources\Content\Faq\Pages;

use App\Filament\Resources\Content\Faq\FaqItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaqItem extends CreateRecord
{
    protected static string $resource = FaqItemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
