<?php

namespace Database\Factories\Cms;

use App\Enums\Cms\MenuLinkType;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\Catalog\Category;
use App\Models\Catalog\Package;
use App\Models\Catalog\Product;
use App\Models\Cms\Menu;
use App\Models\Cms\MenuItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'label' => ucfirst(fake()->words(2, true)),
            'link_type' => MenuLinkType::Url->value,
            'url' => '/'.fake()->slug(2),
            'enabled' => true,
        ];
    }

    public function linkedTo(Model $target): static
    {
        $type = match ($target::class) {
            Page::class => MenuLinkType::Page,
            Product::class => MenuLinkType::Product,
            Package::class => MenuLinkType::Package,
            Category::class => MenuLinkType::CatalogCategory,
            BlogPost::class => MenuLinkType::BlogPost,
            BlogCategory::class => MenuLinkType::BlogCategory,
            default => throw new \InvalidArgumentException('Unsupported linkable: '.$target::class),
        };

        return $this->state([
            'link_type' => $type->value,
            'linkable_type' => $target->getMorphClass(),
            'linkable_id' => $target->getKey(),
            'url' => null,
        ]);
    }

    public function anchor(string $fragment): static
    {
        return $this->state([
            'link_type' => MenuLinkType::Anchor->value,
            'url' => $fragment,
            'linkable_type' => null,
            'linkable_id' => null,
        ]);
    }
}
