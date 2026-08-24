<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Models\Content\FaqCategory;
use App\Services\Cms\FaqInliner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Grouped FAQ section driven by the central Content → FAQ dataset.
 *
 * Unlike the `faq` blueprint (which carries its own hand-authored question
 * repeater for one-off marketing accordions), this type authors nothing:
 * questions are managed once in Content → FAQ and injected here. That makes
 * it reusable on any page built in the page builder — the FAQ page itself,
 * a product page, a landing page — without duplicating copy.
 */
class FaqCategoriesSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::FaqCategories;
    }

    public function label(): string
    {
        return 'FAQ categories (from FAQ dataset)';
    }

    public function icon(): string
    {
        return 'heroicon-o-rectangle-group';
    }

    public function description(): ?string
    {
        return 'Pulls questions from Content → FAQ and renders one panel per category, each with a badge and accordion rows, plus optional category filter pills. Questions are managed in Content → FAQ, never here.';
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'emphasis' => null,
            'description' => null,
            'mode' => 'all',
            'category_ids' => [],
            'limit' => 24,
            'show_filters' => true,
            'open_first' => true,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Header')
                ->description('All optional — leave empty to render the panels on their own, as the FAQ page design does.')
                ->components([
                    TextInput::make('eyebrow')->label('Eyebrow')->maxLength(120),
                    TextInput::make('heading')->maxLength(255),
                    TextInput::make('emphasis')
                        ->label('Heading accent')
                        ->maxLength(255)
                        ->helperText('Final word(s) rendered in the accent colour.'),
                    Textarea::make('description')->rows(2)->maxLength(500),
                ]),
            Section::make('Questions')
                ->description('Content comes from Content → FAQ. Hidden categories and unpublished questions are dropped automatically.')
                ->components([
                    Select::make('mode')
                        ->options([
                            'all' => 'All visible categories',
                            'manual' => 'Pick categories by hand',
                        ])
                        ->default('all')
                        ->required()
                        ->reactive()
                        ->native(false),
                    Select::make('category_ids')
                        ->label('Categories')
                        ->multiple()
                        ->searchable()
                        ->options(fn () => FaqCategory::query()->orderBy('position')->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('mode') === 'manual')
                        ->helperText('Shown in the order selected.'),
                    TextInput::make('limit')
                        ->numeric()
                        ->default(24)
                        ->minValue(1)
                        ->maxValue(50)
                        ->visible(fn (Get $get): bool => $get('mode') !== 'manual'),
                ]),
            Section::make('Display')
                ->components([
                    Toggle::make('show_filters')
                        ->label('Show category filter pills')
                        ->default(true)
                        ->helperText('Sticky "All + category" pill row above the panels. Hide it when embedding a single category.'),
                    Toggle::make('open_first')
                        ->label('Open the first question in each category')
                        ->default(true),
                ]),
        ];
    }

    public function resolveData(array $data): array
    {
        $inliner = app(FaqInliner::class);

        if (($data['mode'] ?? 'all') === 'manual') {
            $ids = array_map('intval', array_filter((array) ($data['category_ids'] ?? []), 'is_numeric'));

            $data['categories'] = $inliner->categoriesByIds($ids);

            return $data;
        }

        $data['categories'] = $inliner->allCategories((int) ($data['limit'] ?? 24));

        return $data;
    }
}
