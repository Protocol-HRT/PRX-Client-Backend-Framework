<?php

namespace App\Filament\Resources\Catalog\Products\Pages;

use App\Actions\Catalog\UpdateProductAction;
use App\Data\Catalog\ProductData;
use App\Filament\Resources\Catalog\Products\ProductResource;
use App\Models\Catalog\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

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
            Action::make('view')
                ->label('View public page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (Product $record) => route('shop.product', ['product' => $record->slug]))
                ->openUrlInNewTab()
                ->visible(fn (Product $record) => $record->isPublished()),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
