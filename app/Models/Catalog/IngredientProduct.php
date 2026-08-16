<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IngredientProduct extends Pivot
{
    protected $table = 'ingredient_product';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'concentration' => 'decimal:4',
            'per_volume' => 'decimal:4',
        ];
    }

    /** @var array<int, string>|null Request-lifetime cache of unit abbreviations. */
    private static ?array $unitAbbreviations = null;

    /**
     * Human label for the potency: "50 mg" for dry/lyophilized rows,
     * "10 mg / 3 ml" when a per-volume denominator is set. Falls back to
     * the raw provider quantity string for synced rows without parsed values.
     */
    public function potencyLabel(): ?string
    {
        $concentration = $this->formatAmount($this->concentration, $this->concentration_unit_id);
        $perVolume = $this->formatAmount($this->per_volume, $this->per_volume_unit_id);

        return match (true) {
            $concentration !== null && $perVolume !== null => "{$concentration} / {$perVolume}",
            $concentration !== null => $concentration,
            default => $this->provider_quantity_label,
        };
    }

    private function formatAmount(mixed $amount, ?int $unitId): ?string
    {
        if (blank($amount)) {
            return null;
        }

        self::$unitAbbreviations ??= MeasurementUnit::withTrashed()
            ->pluck('abbreviation', 'id')
            ->all();

        $formatted = rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.');

        return trim($formatted.' '.(self::$unitAbbreviations[$unitId] ?? ''));
    }
}
