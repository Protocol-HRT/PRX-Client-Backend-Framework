<?php

namespace App\Models\Catalog;

use App\Models\User;
use Database\Factories\Catalog\ProductCoaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCoa extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'batch_number',
        'file_path',
        'file_type',
        'issued_at',
        'notes',
        'is_visible',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'is_visible' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductCoa $coa): void {
            $coa->file_type = self::deriveFileType($coa->file_path);
        });

        static::creating(function (ProductCoa $coa): void {
            $coa->created_by ??= auth()->id();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /** Derive the stored file type (pdf/image extension) from the uploaded path. */
    public static function deriveFileType(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: null;
    }

    protected static function newFactory(): ProductCoaFactory
    {
        return ProductCoaFactory::new();
    }
}
