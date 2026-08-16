<?php

namespace App\Cms\Sections\Concerns;

use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Services\Cms\CatalogInliner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Shared CTA field group for section blueprints whose call-to-action can
 * either link out or add a catalog item to the cart. resolveCta() inlines
 * the referenced catalog card (ProductCalloutSection convention: full card
 * under its own key, raw id untouched) so the frontend renders a live
 * AddToCart action from the section payload alone.
 */
trait HasCatalogCta
{
    /**
     * @return array<string, mixed>
     */
    protected function ctaDefaults(): array
    {
        return [
            'cta_label' => null,
            'cta_mode' => 'link',
            'cta_url' => null,
            'cta_item_type' => 'product',
            'cta_product_id' => null,
            'cta_package_id' => null,
        ];
    }

    /**
     * Works at the top level of a section form and inside repeater items —
     * every Get closure reads a sibling within the same state scope.
     *
     * @return array<int, Component>
     */
    protected function ctaFields(): array
    {
        return [
            TextInput::make('cta_label')->label('CTA label')->maxLength(80),
            Select::make('cta_mode')
                ->label('CTA action')
                ->options(['link' => 'Link to a URL', 'add_to_cart' => 'Add to cart'])
                ->default('link')
                ->reactive()
                ->native(false),
            TextInput::make('cta_url')
                ->label('CTA URL')
                ->maxLength(2048)
                ->visible(fn (Get $get): bool => $get('cta_mode') !== 'add_to_cart'),
            Select::make('cta_item_type')
                ->label('Cart item type')
                ->options(['product' => 'Product', 'package' => 'Package'])
                ->default('product')
                ->reactive()
                ->native(false)
                ->visible(fn (Get $get): bool => $get('cta_mode') === 'add_to_cart'),
            Select::make('cta_product_id')
                ->label('Product to add')
                ->searchable()
                ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id')->all())
                ->visible(fn (Get $get): bool => $get('cta_mode') === 'add_to_cart' && $get('cta_item_type') !== 'package'),
            Select::make('cta_package_id')
                ->label('Package to add')
                ->searchable()
                ->options(fn () => Package::published()->orderBy('name')->pluck('name', 'id')->all())
                ->visible(fn (Get $get): bool => $get('cta_mode') === 'add_to_cart' && $get('cta_item_type') === 'package'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveCta(array $data): array
    {
        $inliner = app(CatalogInliner::class);
        $addToCart = ($data['cta_mode'] ?? null) === 'add_to_cart';

        $data['cta_product'] = $addToCart
            && ($data['cta_item_type'] ?? 'product') !== 'package'
            && is_numeric($data['cta_product_id'] ?? null)
            ? $inliner->product((int) $data['cta_product_id'])
            : null;

        $data['cta_package'] = $addToCart
            && ($data['cta_item_type'] ?? null) === 'package'
            && is_numeric($data['cta_package_id'] ?? null)
            ? $inliner->package((int) $data['cta_package_id'])
            : null;

        return $data;
    }
}
