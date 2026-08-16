<?php

namespace App\Filament\Resources\Catalog\Products\Pages;

use App\Actions\Catalog\UpdateProductAction;
use App\Data\Catalog\ProductData;
use App\Filament\Resources\Catalog\Products\ProductResource;
use App\Filament\Support\RelationTabs;
use App\Models\Catalog\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Product $record */
        return app(UpdateProductAction::class)->execute(
            $record,
            ProductData::validateAndCreate($data)
        );
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $record */
        $record = $this->getRecord();
        $data['category_ids'] = $record->categories->pluck('id')->toArray();
        $data['tag_ids'] = $record->tags->pluck('id')->toArray();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
