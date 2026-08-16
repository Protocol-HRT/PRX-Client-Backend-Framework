<?php

namespace App\Models\Cms;

use App\Enums\Cms\SectionTypeMode;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-defined section type: a named field schema whose instances live in
 * page_sections rows exactly like code-blueprint sections (type = slug).
 */
class FlexibleSectionType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'schema',
        'enabled',
        'mode',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'enabled' => 'boolean',
            'mode' => SectionTypeMode::class,
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Shadow rows are seeded mirrors of code blueprints: inert in the
     * registry (the code definition keeps winning) until promoted to
     * active after passing golden-parity checks.
     */
    public function isShadow(): bool
    {
        return $this->mode === SectionTypeMode::Shadow;
    }

    /**
     * Archived = stashed out of the section picker so new sections cannot
     * use it, while existing sections KEEP rendering. Contrast with
     * `enabled` = false, which also stops rendering existing sections.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class, 'type', 'slug');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(): array
    {
        return array_values($this->schema['fields'] ?? []);
    }

    public function usageCount(): int
    {
        return $this->sections()->count();
    }
}
