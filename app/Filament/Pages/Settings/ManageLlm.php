<?php

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\UpdateLlmSettingsAction;
use App\Data\Settings\LlmSettingsData;
use App\Enums\Llm\LlmProvider;
use App\Settings\LlmSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @property-read Schema $form
 */
class ManageLlm extends BaseSettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'AI / LLM';

    protected static ?string $title = 'AI & LLM settings';

    protected static ?string $slug = 'settings/llm';

    public function mount(): void
    {
        $settings = app(LlmSettings::class);

        $this->form->fill([
            'active_provider' => $settings->active_provider,
            'claude_api_key' => null,
            'claude_model' => $settings->claude_model,
            'openai_api_key' => null,
            'openai_model' => $settings->openai_model,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('AI Provider')
                    ->description('Select the language model provider used for AI-powered features such as SEO metadata generation.')
                    ->components([
                        Select::make('active_provider')
                            ->label('Active provider')
                            ->hintIcon(Heroicon::InformationCircle, 'The provider whose API key and model are used when AI features run.')
                            ->options(LlmProvider::class)
                            ->placeholder('Not configured')
                            ->native(false),
                    ]),

                Section::make('Anthropic Claude')
                    ->description('Settings for the Anthropic Claude API.')
                    ->columns(2)
                    ->components([
                        TextInput::make('claude_api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->hintIcon(Heroicon::InformationCircle, 'Stored encrypted. Leave blank to keep the existing value.')
                            ->helperText('Stored encrypted. Leave blank to keep the existing value.')
                            ->columnSpanFull(),
                        TextInput::make('claude_model')
                            ->label('Model ID')
                            ->hintIcon(Heroicon::InformationCircle, 'Anthropic model identifier, e.g. claude-sonnet-4-6.')
                            ->maxLength(200)
                            ->required(),
                    ]),

                Section::make('OpenAI')
                    ->description('Settings for the OpenAI API.')
                    ->columns(2)
                    ->components([
                        TextInput::make('openai_api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->hintIcon(Heroicon::InformationCircle, 'Stored encrypted. Leave blank to keep the existing value.')
                            ->helperText('Stored encrypted. Leave blank to keep the existing value.')
                            ->columnSpanFull(),
                        TextInput::make('openai_model')
                            ->label('Model ID')
                            ->hintIcon(Heroicon::InformationCircle, 'OpenAI model identifier, e.g. gpt-4o-mini.')
                            ->maxLength(200)
                            ->required(),
                    ]),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = LlmSettingsData::validateAndCreate($this->form->getState());

            $existing = app(LlmSettings::class);

            if (blank($data->claude_api_key)) {
                $data->claude_api_key = $existing->claude_api_key;
            }

            if (blank($data->openai_api_key)) {
                $data->openai_api_key = $existing->openai_api_key;
            }

            app(UpdateLlmSettingsAction::class)->execute($data);
        } catch (ValidationException $e) {
            throw $e;
        } catch (ValidationException $e) {
            // Rethrow ahead of the catch-all: a ValidationException knows which
            // field it belongs to, and converting it into a page-level
            // notification throws that away, leaving the operator hunting for
            // the control at fault. Every settings page had this; found via the
            // palette rule on ManageTheme, which looked inert because its
            // message was being swallowed here.
            throw $e;
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not save AI / LLM settings')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('AI / LLM settings saved')
            ->success()
            ->send();
    }
}
