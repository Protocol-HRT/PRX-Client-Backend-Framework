<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\IngredientData;
use App\Models\Catalog\Ingredient;

class UpdateIngredientAction
{
    use Transacts;

    public function execute(Ingredient $ingredient, IngredientData $data): Ingredient
    {
        return $this->tx(function () use ($ingredient, $data) {
            $ingredient->update([
                'name' => $data->name,
                'slug' => $data->slug ?: $ingredient->slug,
                'short_name' => $data->short_name,
                'description' => $data->description,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_ingredient_id' => $data->provider_ingredient_id,
            ]);

            return $ingredient->fresh();
        });
    }
}
