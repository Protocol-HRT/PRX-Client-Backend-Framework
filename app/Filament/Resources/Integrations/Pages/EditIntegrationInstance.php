<?php

namespace App\Filament\Resources\Integrations\Pages;

use App\Filament\Resources\Integrations\IntegrationInstanceResource;
use App\Integrations\IntegrationRegistry;
use App\Models\Integrations\IntegrationInstance;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditIntegrationInstance extends EditRecord
{
    protected static string $resource = IntegrationInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->testConnectionAction(),
            $this->attestPhiAction(),
        ];
    }

    /**
     * Prove the credentials work now, rather than finding out from a failed run.
     */
    private function testConnectionAction(): Action
    {
        return Action::make('test')
            ->label('Test connection')
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->action(function (IntegrationInstance $record): void {
                try {
                    app(IntegrationRegistry::class)->driverFor($record)->test($record);

                    Notification::make()->success()->title('Connection works.')->send();
                } catch (Throwable $e) {
                    // The provider's own words. A generic "failed" would send the
                    // operator to a log they cannot read.
                    Notification::make()
                        ->danger()
                        ->title('Could not connect')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();
                }
            });
    }

    /**
     * Health-data permission, as a deliberate act with a record behind it.
     *
     * ─── Why this is not a toggle on the form ──────────────────────────
     *
     * Because it is not a preference, it is a claim. Nothing here can verify
     * that an agreement covering health data exists — only that a named person
     * said it does, on a date, giving a reason. That makes the WHO and the WHEN
     * the entire value of the record, and a checkbox saved alongside a display
     * name would capture neither.
     *
     * It is also the reason `phi_permitted` is absent from the edit form: a
     * field an operator can flip while editing something else is a permission
     * nobody is recorded as having granted. Every change comes through here and
     * appends to an audit trail that cannot be edited afterwards.
     */
    private function attestPhiAction(): Action
    {
        return Action::make('attestPhi')
            ->label(fn (IntegrationInstance $record): string => $record->phi_permitted
                ? 'Withdraw health-data permission'
                : 'Permit health data')
            ->icon('heroicon-o-shield-check')
            ->color(fn (IntegrationInstance $record): string => $record->phi_permitted ? 'danger' : 'warning')
            ->modalHeading('Health data permission')
            ->modalDescription(
                'This records YOUR declaration, not a check we can make. Only tick this if you have an '
                .'agreement with this provider that covers health information. Your name and the date '
                .'are recorded, and this cannot be edited afterwards — a change is a new entry.'
            )
            ->schema([
                Toggle::make('permitted')
                    ->label('This provider may receive health data')
                    ->default(fn (IntegrationInstance $record): bool => ! $record->phi_permitted),

                Textarea::make('note')
                    ->label('What are you relying on?')
                    ->placeholder('e.g. BAA signed 2026-08-01, reference #1234')
                    ->helperText('Write down what makes this permitted. This is the part that matters later.')
                    ->rows(3)
                    ->required(),
            ])
            ->action(function (IntegrationInstance $record, array $data): void {
                $record->attestPhi((bool) $data['permitted'], $data['note'], auth()->user());

                Notification::make()
                    ->success()
                    ->title($data['permitted'] ? 'Health data permitted.' : 'Health data permission withdrawn.')
                    ->body('Recorded against your account. Existing workflows follow this immediately.')
                    ->send();

                $this->refreshFormData(['phi_permitted']);
            });
    }
}
