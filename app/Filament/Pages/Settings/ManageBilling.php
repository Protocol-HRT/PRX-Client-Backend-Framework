<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateBillingSettingsAction;
use App\Data\Settings\BillingSettingsData;
use App\Settings\BillingSettings;
use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @property-read Schema $form
 */
class ManageBilling extends BaseSettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Billing';

    protected static ?string $title = 'Billing settings';

    protected static ?string $slug = 'settings/billing';

    public function mount(): void
    {
        $this->form->fill(app(BillingSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Checkout path')
                    ->description('Controls how orders are submitted at checkout. Changing this affects all new orders immediately.')
                    ->schema([
                        Radio::make('checkout_path')
                            ->label('Default checkout provider')
                            ->options([
                                'prx' => 'Prescribe-Rx (embed handoff)',
                                'local' => 'Local gateway (NMI / Authorize.Net / Stripe / Square)',
                            ])
                            ->descriptions([
                                'prx' => 'Cart is submitted to Prescribe-Rx. PRX collects payment and clinical intake inside its hosted embed. No local card processing.',
                                'local' => 'Payment is captured locally through the configured merchant account. The frontend tokenizes the card via the gateway SDK and sends the token to /api/v1/checkout.',
                            ])
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = BillingSettingsData::validateAndCreate($this->form->getState());
            app(UpdateBillingSettingsAction::class)->execute($data);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not save billing settings')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Billing settings saved')
            ->success()
            ->send();
    }
}
