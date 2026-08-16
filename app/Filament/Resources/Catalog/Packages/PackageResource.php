<?php

namespace App\Filament\Resources\Catalog\Packages;

use App\Filament\Resources\Catalog\Packages\Pages\CreatePackage;
use App\Filament\Resources\Catalog\Packages\Pages\EditPackage;
use App\Filament\Resources\Catalog\Packages\Pages\ListPackages;
use App\Filament\Resources\Catalog\Packages\RelationManagers\PlansRelationManager;
use App\Filament\Resources\Catalog\Packages\Schemas\PackageForm;
use App\Filament\Resources\Catalog\Packages\Tables\PackagesTable;
use App\Filament\Resources\Catalog\Products\RelationManagers\CatalogRelationsRelationManager;
use App\Filament\Resources\Catalog\Products\RelationManagers\FaqsRelationManager;
use App\Filament\Resources\Catalog\Products\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\Catalog\Products\RelationManagers\SectionsRelationManager;
use App\Models\Catalog\Package;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PackagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PlansRelationManager::class,
            CatalogRelationsRelationManager::class,
            FaqsRelationManager::class,
            ReviewsRelationManager::class,
            SectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPackages::route('/'),
            'create' => CreatePackage::route('/create'),
            'edit' => EditPackage::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
