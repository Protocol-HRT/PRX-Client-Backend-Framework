<?php

namespace App\Filament\Resources\Content\Faq\Pages;

use App\Filament\Resources\Content\Faq\FaqItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditFaqItem extends EditRecord
{
    protected static string $resource = FaqItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
