<?php

namespace App\Filament\Resources\Catalog\Products\RelationManagers;

use App\Models\Catalog\MeasurementUnit;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';

    protected static ?string $title = 'Ingredients';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::potencyFields());
    }

    /** @return array<int, Field> */
    private static function potencyFields(): array
    {
        $unitOptions = fn () => MeasurementUnit::query()
            ->active()
            ->orderBy('position')
            ->pluck('abbreviation', 'id');

        return [
            TextInput::make('concentration')
                ->numeric()
                ->minValue(0)
                ->step(0.0001)
                ->hintIcon(Heroicon::InformationCircle, 'Amount of this ingredient, e.g. 50 for "50 mg".'),
            Select::make('concentration_unit_id')
                ->label('Unit')
                ->options($unitOptions)
                ->native(false)
                ->hintIcon(Heroicon::InformationCircle, 'Unit for the concentration amount, e.g. mg.'),
            TextInput::make('per_volume')
                ->label('Per volume')
                ->numeric()
                ->minValue(0)
                ->step(0.0001)
                ->hintIcon(Heroicon::InformationCircle, 'Optional denominator volume for liquids, e.g. 3 for "10 mg / 3 ml". Leave blank for lyophilized/dry forms.'),
            Select::make('per_volume_unit_id')
                ->label('Per-volume unit')
                ->options($unitOptions)
                ->native(false)
                ->hintIcon(Heroicon::InformationCircle, 'Unit for the denominator volume, e.g. ml.'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('potency')
                    ->label('Potency')
                    ->state(fn (Model $record): string => self::formatPotency($record))
                    ->placeholder('—'),
                TextColumn::make('provider_ingredient_id')
                    ->label('Provider ID')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...self::potencyFields(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    /** Renders "50 mg" or "10 mg / 3 ml" from the pivot columns. */
    private static function formatPotency(Model $record): string
    {
        $units = MeasurementUnit::withTrashed()->pluck('abbreviation', 'id');

        $part = function (?string $amount, ?int $unitId) use ($units): ?string {
            if (blank($amount)) {
                return null;
            }

            $formatted = rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.');

            return trim($formatted.' '.($units[$unitId] ?? ''));
        };

        $concentration = $part($record->pivot->concentration, $record->pivot->concentration_unit_id);
        $perVolume = $part($record->pivot->per_volume, $record->pivot->per_volume_unit_id);

        return match (true) {
            $concentration && $perVolume => "{$concentration} / {$perVolume}",
            (bool) $concentration => $concentration,
            default => $record->pivot->provider_quantity_label ?? '',
        };
    }
}
