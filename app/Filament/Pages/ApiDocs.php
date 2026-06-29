<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ApiDocs extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracketSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Developer';

    protected static ?string $navigationLabel = 'API Reference';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'API Reference';

    protected static ?string $slug = 'api-reference';

    protected string $view = 'filament.pages.api-docs';
}
