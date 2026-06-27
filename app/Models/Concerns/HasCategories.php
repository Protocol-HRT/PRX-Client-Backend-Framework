<?php

namespace App\Models\Concerns;

use App\Models\Catalog\Category;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasCategories
{
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
            ->withPivot('position')
            ->orderBy('categorizables.position');
    }
}
