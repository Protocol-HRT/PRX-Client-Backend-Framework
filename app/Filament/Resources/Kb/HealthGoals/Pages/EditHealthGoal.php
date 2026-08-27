<?php

namespace App\Filament\Resources\Kb\HealthGoals\Pages;

use App\Filament\Resources\Kb\HealthGoals\HealthGoalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditHealthGoal extends EditRecord
{
    protected static string $resource = HealthGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
