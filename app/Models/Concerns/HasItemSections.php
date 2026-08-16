<?php

namespace App\Models\Concerns;

use App\Models\Catalog\CatalogItemSection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasItemSections
{
    /** Injectable detail-page sections, in display order. */
    public function sections(): MorphMany
    {
        return $this->morphMany(CatalogItemSection::class, 'sectionable')->orderBy('position');
    }
}
