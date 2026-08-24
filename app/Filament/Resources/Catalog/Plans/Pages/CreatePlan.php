<?php

namespace App\Filament\Resources\Catalog\Plans\Pages;

use App\Actions\Catalog\CreatePlanAction;
use App\Data\Catalog\PlanData;
use App\Filament\Resources\Catalog\Plans\PlanResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreatePlanAction::class)->execute(
            PlanData::validateAndCreate($data)
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
