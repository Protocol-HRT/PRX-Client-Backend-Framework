<?php

namespace App\Actions\Catalog;

use App\Actions\Concerns\Transacts;
use App\Data\Catalog\IngredientData;
use App\Models\Catalog\Ingredient;

class CreateIngredientAction
{
    use Transacts;

    public function execute(IngredientData $data): Ingredient
    {
        return $this->tx(function () use ($data) {
            return Ingredient::create([
                'name' => $data->name,
                'slug' => $data->slug,
                'short_name' => $data->short_name,
                'description' => $data->description,
                'sex_eligibility' => $data->sex_eligibility,
                'min_age' => $data->min_age,
                'max_age' => $data->max_age,
                'eligibility_note' => $data->eligibility_note,
                'is_active' => $data->is_active,
                'position' => $data->position,
                'provider_ingredient_id' => $data->provider_ingredient_id,
            ]);
        });
    }
}
