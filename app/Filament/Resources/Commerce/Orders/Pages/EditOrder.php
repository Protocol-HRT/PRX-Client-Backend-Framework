<?php

namespace App\Filament\Resources\Commerce\Orders\Pages;

use App\Filament\Resources\Commerce\Orders\OrderResource;
use App\Filament\Support\RelationTabs;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

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
            DeleteAction::make(),
        ];
    }
}
