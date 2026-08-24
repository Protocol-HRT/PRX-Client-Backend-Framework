<?php

namespace App\Filament\Resources\EmailSubscribers;

use App\Filament\Resources\EmailSubscribers\Pages\EditEmailSubscriber;
use App\Filament\Resources\EmailSubscribers\Pages\ListEmailSubscribers;
use App\Filament\Resources\EmailSubscribers\Schemas\EmailSubscriberForm;
use App\Filament\Resources\EmailSubscribers\Tables\EmailSubscribersTable;
use App\Models\EmailSubscriber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EmailSubscriberResource extends Resource
{
    protected static ?string $model = EmailSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?string $modelLabel = 'Email subscriber';

    protected static ?string $pluralModelLabel = 'Email subscribers';

    public static function form(Schema $schema): Schema
    {
        return EmailSubscriberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailSubscribersTable::configure($table);
    }

    /**
     * Subscribers are created from the public site, not in admin.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEmailSubscribers::route('/'),
            'edit' => EditEmailSubscriber::route('/{record}/edit'),
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
