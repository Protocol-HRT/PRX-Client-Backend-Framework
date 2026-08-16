<?php

namespace App\Filament\Pages;

use App\Cms\FlexibleDefinition;
use App\Contracts\Cms\SectionDefinition;
use App\Filament\Resources\Cms\FlexibleSectionTypes\FlexibleSectionTypeResource;
use App\Services\Cms\SectionRegistry;
use App\Services\Cms\SectionTypeInspector;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Read-only catalog of every section type the page builder offers — code
 * blueprints and admin-defined custom types side by side. Surfaces what
 * each type consists of (field inventory), where it is used, an example
 * API payload exactly as /api/v1 ships it, and a generalized wireframe of
 * the section's structure. Custom types link through to their editor.
 */
class SectionTypes extends Page implements HasTable
{
    use HasPageShield, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Section Types';

    protected static ?int $navigationSort = 14;

    protected static ?string $title = 'Section Types';

    protected static ?string $slug = 'section-types';

    protected string $view = 'filament.pages.section-types';

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator {
                $rows = $this->rows($filters);

                if (filled($search)) {
                    $needle = mb_strtolower($search);
                    $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['label'].' '.$row['slug']), $needle));
                }

                return new LengthAwarePaginator(
                    $rows->forPage($page, $recordsPerPage)->keyBy('slug'),
                    total: $rows->count(),
                    perPage: $recordsPerPage,
                    currentPage: $page,
                );
            })
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('label')
                    ->label('Type')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('slug')
                    ->badge()
                    ->color('gray')
                    ->copyable(),
                TextColumn::make('origin')
                    ->badge()
                    ->color(fn (string $state, array $record): string => match (true) {
                        $record['archived'] => 'gray',
                        $state === 'code' => 'info',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state, array $record): string => match (true) {
                        $record['archived'] => 'Custom · archived',
                        $state === 'code' => 'Code',
                        default => 'Custom',
                    }),
                TextColumn::make('description')
                    ->limit(70)
                    ->tooltip(fn (array $record): ?string => $record['description'])
                    ->placeholder('—'),
                TextColumn::make('fields_count')
                    ->label('Fields')
                    ->alignCenter(),
                TextColumn::make('usage_total')
                    ->label('Used by')
                    ->alignCenter()
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? '—' : (string) $state)
                    ->tooltip(fn (array $record): string => sprintf(
                        'Pages: %d · Catalog: %d · Global blocks: %d · Layout regions: %d',
                        $record['usage']['pages'],
                        $record['usage']['catalog'],
                        $record['usage']['globals'],
                        $record['usage']['regions'],
                    )),
            ])
            ->filters([
                SelectFilter::make('origin')
                    ->options(['code' => 'Code', 'flexible' => 'Custom']),
            ])
            ->recordActions([
                Action::make('fields')
                    ->label('Fields')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->modalHeading(fn (array $record): string => $record['label'].' — fields')
                    ->modalDescription('The inputs an editor fills in for this section type. Field names are the keys the frontend receives in the payload.')
                    ->modalContent(fn (array $record): View => view('filament.pages.section-types.fields', [
                        'fields' => app(SectionTypeInspector::class)->fields($this->definition($record['slug'])),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('payload')
                    ->label('Payload')
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->modalHeading(fn (array $record): string => $record['label'].' — example API payload')
                    ->modalContent(function (array $record): View {
                        $example = app(SectionTypeInspector::class)->examplePayload($this->definition($record['slug']));

                        return view('filament.pages.section-types.payload', $example);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('wireframe')
                    ->label('Wireframe')
                    ->icon(Heroicon::OutlinedRectangleGroup)
                    ->modalHeading(fn (array $record): string => $record['label'].' — structure')
                    ->modalDescription('Generalized wireframe of the fields this section ships — structure only, not the storefront design.')
                    ->modalContent(fn (array $record): View => view('filament.pages.section-types.wireframe', [
                        'fields' => app(SectionTypeInspector::class)->fields($this->definition($record['slug'])),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('edit')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (array $record): bool => $record['flexible_id'] !== null)
                    ->url(fn (array $record): string => FlexibleSectionTypeResource::getUrl('edit', ['record' => $record['flexible_id']])),
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(array $filters): Collection
    {
        $inspector = app(SectionTypeInspector::class);
        $originFilter = $filters['origin']['value'] ?? null;

        return collect(app(SectionRegistry::class)->all())
            ->map(function (SectionDefinition $definition, string $slug) use ($inspector): array {
                $usage = $inspector->usage($slug);

                return [
                    'slug' => $slug,
                    'label' => $definition->label(),
                    'origin' => $definition instanceof FlexibleDefinition ? 'flexible' : 'code',
                    'description' => $definition->description(),
                    'fields_count' => count($inspector->fields($definition)),
                    'usage' => $usage,
                    'usage_total' => $usage['total'],
                    'flexible_id' => $definition instanceof FlexibleDefinition ? $definition->model()->id : null,
                    'archived' => $definition instanceof FlexibleDefinition && $definition->model()->isArchived(),
                ];
            })
            ->when($originFilter !== null, fn (Collection $rows) => $rows->filter(
                fn (array $row): bool => $row['origin'] === $originFilter
            ))
            ->sortBy('label')
            ->values();
    }

    private function definition(string $slug): SectionDefinition
    {
        return app(SectionRegistry::class)->resolve($slug)
            ?? abort(404);
    }
}
