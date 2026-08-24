<?php

namespace App\Filament\Resources\Catalog\Packages\Pages;

use App\Actions\Catalog\CreatePackageAction;
use App\Data\Catalog\PackageData;
use App\Filament\Resources\Catalog\Packages\PackageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePackage extends CreateRecord
{
    protected static string $resource = PackageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePackageAction::class)->execute(
            PackageData::validateAndCreate($data)
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
