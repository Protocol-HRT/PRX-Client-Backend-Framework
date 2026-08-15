<?php

namespace App\Cms\Sections;

use App\Enums\SectionType;
use App\Filament\Support\SectionImagePicker;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Services\Cms\CatalogInliner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class ProductCalloutSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::ProductCallout;
    }

    public function label(): string
    {
        return 'Product callout';
    }

    public function icon(): string
    {
        return 'heroicon-o-megaphone';
    }

    public function description(): ?string
    {
        return 'Single featured product or package promo with custom headline and copy overriding the catalog text.';
    }

    public function defaults(): array
    {
        return [
            'item_type' => 'product',
            'product_id' => null,
            'package_id' => null,
            'eyebrow' => null,
            'headline' => null,
            'body' => null,
            'cta_label' => null,
            'cta_url' => null,
            'image' => null,
            'image_alt' => null,
            'image_right' => false,
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Featured item')
                ->columns(2)
                ->components([
                    Select::make('item_type')
                        ->options(['product' => 'Product', 'package' => 'Package'])
                        ->default('product')
                        ->required()
                        ->reactive()
                        ->native(false),
                    Select::make('product_id')
                        ->label('Product')
                        ->searchable()
                        ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('item_type') === 'product'),
                    Select::make('package_id')
                        ->label('Package')
                        ->searchable()
                        ->options(fn () => Package::published()->orderBy('name')->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('item_type') === 'package'),
                ]),
            Section::make('Copy overrides')
                ->description('Optional. Falls back to the catalog name/description when left blank.')
                ->components([
                    TextInput::make('eyebrow')->maxLength(120),
                    TextInput::make('headline')->maxLength(255),
                    Textarea::make('body')->rows(3)->maxLength(1000),
                    TextInput::make('cta_label')->maxLength(80),
                    TextInput::make('cta_url')->maxLength(2048),
                ]),
            Section::make('Image')
                ->description('Optional custom image. Falls back to the catalog hero image when left blank.')
                ->columns(2)
                ->components([
                    SectionImagePicker::make('image')->label('Image'),
                    TextInput::make('image_alt')->maxLength(255),
                    Toggle::make('image_right')->label('Image on the right')->helperText('Default is image on the left.'),
                ]),
        ];
    }

    public function fieldKinds(): array
    {
        return ['image' => 'image'];
    }

    public function resolveData(array $data): array
    {
        $inliner = app(CatalogInliner::class);

        $data['product'] = ($data['item_type'] ?? 'product') === 'product' && is_numeric($data['product_id'] ?? null)
            ? $inliner->product((int) $data['product_id'])
            : null;

        $data['package'] = ($data['item_type'] ?? null) === 'package' && is_numeric($data['package_id'] ?? null)
            ? $inliner->package((int) $data['package_id'])
            : null;

        return $data;
    }
}
