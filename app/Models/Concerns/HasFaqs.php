<?php

namespace App\Models\Concerns;

use App\Models\Content\FaqItem;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasFaqs
{
    /** FAQ items attached to this record, in per-record pivot order. */
    public function faqs(): MorphToMany
    {
        return $this->morphToMany(FaqItem::class, 'faqable')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /** Published FAQ items only — the storefront-facing subset. */
    public function publishedFaqs(): MorphToMany
    {
        return $this->faqs()->where('is_published', true);
    }
}
