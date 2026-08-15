<?php

namespace App\Filament\Resources\ApiClients\RelationManagers;

use App\Actions\ApiClients\IssueApiClientTokenAction;
use App\Enums\TokenAbility;
use App\Models\ApiClient;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'Issued tokens';

    // Sanctum's PersonalAccessToken has no Shield policy, so the default
    // related-model gate can't apply — gate on the owning ApiClient instead.
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('abilities')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->abilities ?? ['*']),
                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->since()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->label('Issued')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('issue_token')
                    ->label('Issue token')
                    ->icon('heroicon-o-key')
                    ->visible(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->form([
                        TextInput::make('name')
                            ->label('Token name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Descriptive label — e.g. "Production Next.js frontend" or "Staging build".'),
                        CheckboxList::make('abilities')
                            ->options(TokenAbility::options())
                            ->default(fn () => $this->ownerRecord->default_abilities ?? [TokenAbility::PublicRead->value])
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var ApiClient $client */
                        $client = $this->ownerRecord;

                        $plainText = app(IssueApiClientTokenAction::class)
                            ->execute($client, $data['name'], $data['abilities']);

                        Notification::make()
                            ->title('Token issued — copy it now')
                            ->body("This token will not be shown again:\n\n{$plainText}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Revoke')
                    ->visible(fn (): bool => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->modalHeading('Revoke token?')
                    ->modalDescription('The token will be permanently deleted and any client using it will lose access immediately.')
                    ->modalSubmitActionLabel('Revoke'),
            ]);
    }
}
