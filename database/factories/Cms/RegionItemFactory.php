<?php

namespace Database\Factories\Cms;

use App\Enums\Cms\Region;
use App\Enums\Cms\RegionItemKind;
use App\Models\Cms\GlobalSection;
use App\Models\Cms\Menu;
use App\Models\Cms\RegionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegionItem>
 */
class RegionItemFactory extends Factory
{
    protected $model = RegionItem::class;

    public function definition(): array
    {
        return [
            'region' => Region::Header->value,
            'kind' => RegionItemKind::Section->value,
            'section_type' => 'cta-banner',
            'data' => ['heading' => fake()->sentence(3)],
            'enabled' => true,
        ];
    }

    public function inRegion(Region $region): static
    {
        return $this->state(['region' => $region->value]);
    }

    public function forMenu(Menu $menu): static
    {
        return $this->state([
            'kind' => RegionItemKind::Menu->value,
            'menu_id' => $menu->id,
            'section_type' => null,
            'data' => null,
        ]);
    }

    public function forGlobalSection(GlobalSection $global): static
    {
        return $this->state([
            'kind' => RegionItemKind::GlobalSection->value,
            'global_section_id' => $global->id,
            'section_type' => null,
            'data' => null,
        ]);
    }
}
