<?php

namespace App\Filament\Resources\ApiClients\Pages;

use App\Filament\Resources\ApiClients\ApiClientResource;
use App\Filament\Support\RelationTabs;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditApiClient extends EditRecord
{
    protected static string $resource = ApiClientResource::class;

    /**
     * Form tabs on top, relation managers as vertical tabs directly below
     * (instead of the default horizontal below-the-fold strip).
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            RelationTabs::make($this),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
