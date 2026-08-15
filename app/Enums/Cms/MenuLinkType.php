<?php

namespace App\Enums\Cms;

use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;

enum MenuLinkType: string
{
    case Page = 'page';
    case Product = 'product';
    case Package = 'package';
    case CatalogCategory = 'catalog_category';
    case BlogPost = 'blog_post';
    case BlogCategory = 'blog_category';
    case Url = 'url';
    case Anchor = 'anchor';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Page',
            self::Product => 'Product',
            self::Package => 'Package',
            self::CatalogCategory => 'Catalog category',
            self::BlogPost => 'Blog post',
            self::BlogCategory => 'Blog category',
            self::Url => 'Custom URL',
            self::Anchor => 'In-page anchor',
        };
    }

    /**
     * @return class-string<Model>|null
     */
    public function modelClass(): ?string
    {
        return match ($this) {
            self::Page => Page::class,
            self::Product => Product::class,
            self::Package => Package::class,
            self::CatalogCategory => Category::class,
            self::BlogPost => BlogPost::class,
            self::BlogCategory => BlogCategory::class,
            self::Url, self::Anchor => null,
        };
    }

    public function isEntity(): bool
    {
        return $this->modelClass() !== null;
    }

    /**
     * Whether the linked entity is currently publicly visible — dead targets
     * are dropped from API menu payloads.
     */
    public function targetIsAvailable(Model $target): bool
    {
        return match ($this) {
            self::Page, self::Product, self::Package, self::BlogPost => $target->isPublished(),
            self::CatalogCategory, self::BlogCategory => (bool) $target->is_visible,
            self::Url, self::Anchor => true,
        };
    }
}
