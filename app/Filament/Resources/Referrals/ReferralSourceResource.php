<?php

namespace App\Filament\Resources\Referrals;

use App\Filament\Resources\Referrals\Pages\CreateReferralSource;
use App\Filament\Resources\Referrals\Pages\EditReferralSource;
use App\Filament\Resources\Referrals\Pages\ListReferralSources;
use App\Filament\Resources\Referrals\RelationManagers\LinksRelationManager;
use App\Filament\Resources\Referrals\RelationManagers\RepsRelationManager;
use App\Filament\Resources\Referrals\Schemas\ReferralSourceForm;
use App\Filament\Resources\Referrals\Tables\ReferralSourcesTable;
use App\Models\Referral\ReferralSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Affiliates, sales groups and partners — everyone who can be credited.
 *
 * A source is created here; its trackable CODES are managed on the source's own
 * edit page (the Links relation manager), because a code without a source to
 * credit is meaningless and a top-level "links" list would invite exactly that.
 *
 * NOTHING HERE DELETES ATTRIBUTION. Deleting a source is a soft delete, and the
 * database additionally REFUSES to hard-delete one that holds codes. That is
 * deliberate: a commission is argued from these rows months later. Deactivate
 * instead — it stops every code earning immediately without touching history.
 */
class ReferralSourceResource extends Resource
{
    protected static ?string $model = ReferralSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'referral source';

    protected static ?string $pluralModelLabel = 'referral sources';

    public static function form(Schema $schema): Schema
    {
        return ReferralSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferralSourcesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [LinksRelationManager::class, RepsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferralSources::route('/'),
            'create' => CreateReferralSource::route('/create'),
            'edit' => EditReferralSource::route('/{record}/edit'),
        ];
    }
}
