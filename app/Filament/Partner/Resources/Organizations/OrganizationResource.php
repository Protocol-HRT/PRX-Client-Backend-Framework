<?php

namespace App\Filament\Partner\Resources\Organizations;

use App\Filament\Partner\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Partner\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Partner\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Partner\Resources\Organizations\RelationManagers\PartnerLinksRelationManager;
use App\Filament\Partner\Resources\Organizations\RelationManagers\PartnerRepsRelationManager;
use App\Filament\Partner\Resources\PartnerResource;
use App\Models\Referral\ReferralSource;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * A partner's own organisation and everything beneath it.
 *
 * This is what lets a group "open a referral organisation and add sub referral
 * orgs as well as reps for their org and their children" — the list is scoped to
 * the viewer's downline by PartnerResource, and each row opens onto the same
 * Codes and People panels the staff admin uses.
 *
 * `scopeColumn()` is `id`, because for THIS model the referral source is the
 * record itself. Every other partner resource will scope on
 * `referral_source_id`.
 */
class OrganizationResource extends PartnerResource
{
    protected static ?string $model = ReferralSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'organisation';

    protected static ?string $recordTitleAttribute = 'name';

    public static function scopeColumn(): string
    {
        return 'id';
    }

    public static function form(Schema $schema): Schema
    {
        return Schemas\OrganizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\OrganizationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Thin partner subclasses of the staff panels. The FORMS and TABLES are
        // inherited, so minting, code normalisation and password handling are
        // literally the same code — only the authorisation rule differs, because
        // Filament gates a relation manager on the related model policy and
        // partner roles hold no permissions. See the subclasses.
        return [PartnerLinksRelationManager::class, PartnerRepsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
