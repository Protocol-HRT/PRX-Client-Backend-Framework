<?php

namespace App\Filament\Resources\Catalog\Plans\Pages;

use App\Actions\Catalog\UpdatePlanAction;
use App\Data\Catalog\PlanData;
use App\Filament\Resources\Catalog\Plans\PlanResource;
use App\Models\Catalog\Plan;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Plan $record */
        return app(UpdatePlanAction::class)->execute(
            $record,
            PlanData::validateAndCreate($data)
        );
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
