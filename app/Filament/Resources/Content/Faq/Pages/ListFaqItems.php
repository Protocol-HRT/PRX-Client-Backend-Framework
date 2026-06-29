<?php

namespace App\Filament\Resources\Content\Faq\Pages;

use App\Filament\Resources\Content\Faq\FaqItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFaqItems extends ListRecords
{
    protected static string $resource = FaqItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
