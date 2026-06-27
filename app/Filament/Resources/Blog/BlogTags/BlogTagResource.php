<?php

namespace App\Filament\Resources\Blog\BlogTags;

use App\Filament\Resources\Blog\BlogTags\Pages\CreateBlogTag;
use App\Filament\Resources\Blog\BlogTags\Pages\EditBlogTag;
use App\Filament\Resources\Blog\BlogTags\Pages\ListBlogTags;
use App\Filament\Resources\Blog\BlogTags\Schemas\BlogTagForm;
use App\Filament\Resources\Blog\BlogTags\Tables\BlogTagsTable;
use App\Models\Blog\BlogTag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BlogTagResource extends Resource
{
    protected static ?string $model = BlogTag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static ?string $navigationLabel = 'Tags';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BlogTagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlogTagsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogTags::route('/'),
            'create' => CreateBlogTag::route('/create'),
            'edit' => EditBlogTag::route('/{record}/edit'),
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
