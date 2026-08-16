<?php

namespace App\Cms\Support;

use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Services\Cms\CatalogInliner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The canonical CTA field group: a call-to-action that either links out or
 * adds a catalog item to the cart. Single source of truth shared by code
 * blueprints (via the HasCatalogCta trait) and DB-defined section types
 * (via the `cta` field kind / `resolve_cta` resolver op), so both origins
 * ship byte-identical CTA payloads.
 *
 * Keys are flat (cta_label, cta_mode, …) in the surrounding state scope —
 * works at the top level of a section form and inside repeater items.
 */
class CtaFields
{
    /** @var list<string> */
    public const KEYS = [
        'cta_label',
        'cta_mode',
        'cta_url',
        'cta_item_type',
        'cta_product_id',
        'cta_package_id',
    ];

    /** Output keys written by resolve() — reserved alongside KEYS. */
    public const RESOLVED_KEYS = ['cta_product', 'cta_package'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
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
     * @return array<int, Component>
     */
    public static function components(): array
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
     * Inline the referenced catalog card (raw ids stay untouched) so the
     * frontend can render a live AddToCart action from the payload alone.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolve(array $data): array
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
