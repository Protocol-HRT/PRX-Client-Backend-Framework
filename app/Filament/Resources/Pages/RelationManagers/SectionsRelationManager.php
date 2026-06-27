<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Enums\SectionType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Sections';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Section')
                    ->columns(2)
                    ->components([
                        Select::make('type')
                            ->options(SectionType::options())
                            ->required()
                            ->reactive()
                            ->disabledOn('edit')
                            ->native(false)
                            ->helperText('The section type cannot be changed after creation. Delete and re-add to swap types.'),
                        TextInput::make('anchor_id')
                            ->label('Anchor ID')
                            ->maxLength(64)
                            ->helperText('Optional. Used as the section\'s HTML id for in-page links (e.g. "pricing").'),
                        Toggle::make('enabled')
                            ->default(true)
                            ->helperText('Disabled sections are still saved but skipped on render.'),
                    ]),
                ...static::dynamicSchema(),
            ])
            ->statePath('data');
    }

    /**
     * Build a Filament Group per SectionType, visible only when that type is selected.
     * Each Group's components are spread under the JSON `data` column.
     *
     * @return array<int, Group>
     */
    protected static function dynamicSchema(): array
    {
        $groups = [];

        foreach (SectionType::cases() as $case) {
            $blueprint = $case->blueprint();

            $groups[] = Group::make($blueprint->formSchema())
                ->statePath('data')
                ->visible(fn (Get $get) => $get('../type') === $case->value);
        }

        return $groups;
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (SectionType $state): string => $state->blueprint()->label()),
                TextColumn::make('anchor_id')
                    ->label('Anchor')
                    ->placeholder('—')
                    ->copyable(),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(SectionType::options()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // Merge in defaults for unset keys when creating a new section
                        $type = SectionType::from($data['type']);
                        $data['data'] = array_replace(
                            $type->blueprint()->defaults(),
                            $data['data'] ?? [],
                        );

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('position'));
    }
}
