<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case InStock = 'in_stock';
    case BackOrdered = 'back_ordered';
    case OutOfStock = 'out_of_stock';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::BackOrdered => 'Back Ordered',
            self::OutOfStock => 'Out of Stock',
            self::Discontinued => 'Discontinued',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::BackOrdered => 'warning',
            self::OutOfStock => 'danger',
            self::Discontinued => 'gray',
        };
    }

    /** Whether products with this status are purchasable on the storefront. */
    public function isPurchasable(): bool
    {
        return $this === self::InStock || $this === self::BackOrdered;
    }

    /** PRX ProductInventoryStatus integer backing value. */
    public function providerValue(): int
    {
        return match ($this) {
            self::InStock => 1,
            self::BackOrdered => 2,
            self::OutOfStock => 3,
            self::Discontinued => 4,
        };
    }

    public static function fromProviderValue(?int $value): ?self
    {
        return match ($value) {
            1 => self::InStock,
            2 => self::BackOrdered,
            3 => self::OutOfStock,
            4 => self::Discontinued,
            default => null,
        };
    }
}
