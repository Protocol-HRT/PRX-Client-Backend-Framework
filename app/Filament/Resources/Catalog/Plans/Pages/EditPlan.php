<?php

namespace App\Filament\Resources\Catalog\Plans\Pages;

use App\Actions\Catalog\UpdatePlanAction;
use App\Data\Catalog\PlanData;
use App\Filament\Resources\Catalog\Plans\PlanResource;
use App\Models\Catalog\Plan;
use Filament\Actions\Action;
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Plan $record */
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
                ->url(fn (Plan $record) => route('shop.plan', ['plan' => $record->slug]))
                ->openUrlInNewTab()
                ->visible(fn (Plan $record) => $record->isPublished()),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
