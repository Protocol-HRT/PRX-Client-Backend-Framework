<?php

namespace App\Filament\Resources\Referrals\RelationManagers;

use App\Models\Referral\ReferralSource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The people who can log in for this organisation.
 *
 * A rep is a `User` with `referral_source_id` set, exactly as prescribe-rx models
 * one — not a separate contact table, because the whole point of a rep is that
 * they sign in and see their org's numbers, and `users` already carries auth,
 * roles and invitations.
 *
 * SETTING `referral_source_id` IS WHAT MAKES SOMEONE NOT-STAFF.
 * `User::canAccessPanel()` refuses the staff admin to anyone carrying one, so
 * creating a rep here can never accidentally mint an admin.
 */
class RepsRelationManager extends RelationManager
{
    protected static string $relationship = 'reps';

    protected static ?string $title = 'People';

    protected static ?string $modelLabel = 'person';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignoreRecord: true)
                    ->maxLength(255)
                    ->hintIcon(Heroicon::InformationCircle, 'They sign in with this.'),

                Select::make('roles')
                    ->relationship('roles', 'name', fn ($query) => $query->whereIn('name', ['affiliate', 'sales_group']))
                    ->multiple()
                    ->preload()
                    ->required()
                    ->hintIcon(Heroicon::InformationCircle, 'Only partner roles are offered here. A person attached to an organisation can never reach the staff admin, whatever role they hold.'),

                Toggle::make('is_active')
                    ->default(true)
                    ->hintIcon(Heroicon::InformationCircle, 'Switch off to revoke their access without deleting the person.'),
            ]);
    }

    /**
     * Whether the current viewer may add rows here.
     *
     * A hook rather than an inline policy check: Filament authorises a relation
     * manager's create action against the RELATED model's policy, which is keyed
     * on the model and therefore shared with the staff resource. The partner
     * panel needs a different rule for the same screen — see the subclass in
     * app/Filament/Partner/.../RelationManagers.
     */
    public static function canCreateRecords(): bool
    {
        return auth()->user()?->can('create', User::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('last_login_at')
                    ->label('Last seen')
                    ->since()
                    ->placeholder('Never signed in'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add person')
                    // Overridable, because Filament authorises this against the
                    // RELATED model's policy and partner roles hold none — see
                    // the partner subclass.
                    ->authorize(fn (): bool => static::canCreateRecords())
                    ->mutateDataUsing(function (array $data): array {
                        /** @var ReferralSource $source */
                        $source = $this->getOwnerRecord();

                        // Belt and braces: the relation manager sets this via the
                        // relationship, but an explicit assignment means a rep can
                        // never be created unattached — and unattached would mean
                        // STAFF, which is the one mistake that must be impossible.
                        $data['referral_source_id'] = $source->id;

                        // A random password nobody is told. They arrive through
                        // the password-reset flow, so no credential is ever
                        // transmitted or sitting in an admin's clipboard.
                        $data['password'] = Hash::make(Str::random(40));

                        return $data;
                    }),
            ])
            ->recordActions([EditAction::make()])
            // No delete: a person who has been credited with referrals is
            // referenced by that history. Deactivate instead.
            ->toolbarActions([]);
    }
}
