<?php

namespace App\Models\Cms;

use App\Contracts\Cms\SectionDefinition;
use App\Models\PageSection;
use App\Models\User;
use App\Services\Cms\SectionRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable section: one type + data payload referenced by many page
 * sections (and later region items). Editing it updates every reference;
 * a reference can be "detached" into an independent copy.
 */
class GlobalSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'data',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function pageSections(): HasMany
    {
        return $this->hasMany(PageSection::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function definition(): ?SectionDefinition
    {
        return app(SectionRegistry::class)->resolve($this->type);
    }

    public function usageCount(): int
    {
        return $this->pageSections()->count();
    }
}
