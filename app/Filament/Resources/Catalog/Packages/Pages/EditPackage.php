<?php

namespace App\Filament\Resources\Catalog\Packages\Pages;

use App\Actions\Catalog\UpdatePackageAction;
use App\Data\Catalog\PackageData;
use App\Filament\Resources\Catalog\Packages\PackageResource;
use App\Models\Catalog\Package;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPackage extends EditRecord
{
    protected static string $resource = PackageResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Package $record */
        return app(UpdatePackageAction::class)->execute(
            $record,
            PackageData::validateAndCreate($data)
        );
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Package $record */
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
