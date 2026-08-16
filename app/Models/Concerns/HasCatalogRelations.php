<?php

namespace App\Models\Concerns;

use App\Enums\CatalogRelationType;
use App\Models\Catalog\CatalogRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Gives a catalog model (Product, Package) typed outgoing relations to
 * other catalog items via the double-polymorphic catalog_relations table.
 */
trait HasCatalogRelations
{
    public function catalogRelations(): MorphMany
    {
        return $this->morphMany(CatalogRelation::class, 'source')->orderBy('position');
    }

    /** @return Collection<int, Model> Related catalog items (products/packages), published only. */
    public function relatedItems(): Collection
    {
        return $this->resolveRelationTargets(CatalogRelationType::Related);
    }

    /** @return Collection<int, Model> "Pairs well with" catalog items, published only. */
    public function pairsWithItems(): Collection
    {
        return $this->resolveRelationTargets(CatalogRelationType::PairsWith);
    }

    /** @return Collection<int, Model> */
    private function resolveRelationTargets(CatalogRelationType $type): Collection
    {
        return $this->catalogRelations()
            ->where('relation_type', $type->value)
            ->with('related')
            ->get()
            ->pluck('related')
            ->filter(fn ($item) => $item !== null && $item->isPublished())
            ->values();
    }
}
