<?php

namespace App\Enums;

enum CatalogRelationType: string
{
    case Related = 'related';
    case PairsWith = 'pairs_with';

    public function label(): string
    {
        return match ($this) {
            self::Related => 'Related',
            self::PairsWith => 'Pairs With',
        };
    }
}
