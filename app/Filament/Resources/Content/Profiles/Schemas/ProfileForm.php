<?php

namespace App\Filament\Resources\Content\Profiles\Schemas;

use App\Enums\ProfileType;
use App\Models\Catalog\Tag;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Profile')
                    ->vertical()
                    ->persistTabInQueryString('profile-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Details ───────────────────────────────────
                        Tab::make('Details')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->hintIcon(Heroicon::InformationCircle, 'Full name as displayed publicly on the team or about page.')
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->alphaDash()
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'URL-friendly identifier for this profile page. Auto-generated from name.'),
                                Select::make('type')
                                    ->options(ProfileType::class)
                                    ->native(false)
                                    ->required()
                                    ->hintIcon(Heroicon::InformationCircle, 'Profile category. Controls where this profile appears on the site (e.g. physician, advisor).'),
                                TextInput::make('title')
                                    ->maxLength(255)
                                    ->placeholder('e.g. Chief Medical Officer')
                                    ->hintIcon(Heroicon::InformationCircle, 'Job title or role displayed under the person\'s name, e.g. "Medical Director".'),
                                TextInput::make('credentials')
                                    ->maxLength(100)
                                    ->hintIcon(Heroicon::InformationCircle, 'Professional credentials shown after the name, e.g. MD, PhD, FACP.')
                                    ->helperText('e.g. MD, PhD, FACP'),
                                Textarea::make('bio')
                                    ->rows(6)
                                    ->hintIcon(Heroicon::InformationCircle, 'Short biography displayed on the team or about page.')
                                    ->columnSpanFull(),
                            ]),

                        // ── Publishing ────────────────────────────────
                        Tab::make('Publishing')
                            ->icon(Heroicon::Eye)
                            ->columns(2)
                            ->schema([
                                Toggle::make('is_published')
                                    ->default(false),
                                Toggle::make('is_featured')
                                    ->default(false),
                                TextInput::make('position')
                                    ->numeric()
                                    ->default(0)
                                    ->hintIcon(Heroicon::InformationCircle, 'Controls display order on team/profile pages. Lower numbers appear first.'),
                            ]),

                        // ── Organization ──────────────────────────────
                        Tab::make('Organization')
                            ->icon(Heroicon::Tag)
                            ->schema([
                                Select::make('tag_ids')
                                    ->label('Tags')
                                    ->multiple()
                                    ->relationship('tags', 'name')
                                    ->options(fn () => Tag::query()->where('is_visible', true)->orderBy('name')->pluck('name', 'id'))
                                    ->preload()
                                    ->hintIcon(Heroicon::InformationCircle, 'Optional tags for filtering profiles by specialty or area of focus.')
                                    ->searchable(),
                            ]),

                        // ── Media ─────────────────────────────────────
                        Tab::make('Media')
                            ->icon(Heroicon::Photo)
                            ->schema([
                                FileUpload::make('image_path')
                                    ->label('Profile photo')
                                    ->image()
                                    ->imageEditor()
                                    ->disk('public')
                                    ->directory('profiles')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->hintIcon(Heroicon::InformationCircle, 'Profile photo displayed on the team page. Square crop recommended, max 5 MB.')
                                    ->columnSpanFull(),
                            ]),

                        // ── SEO ───────────────────────────────────────
                        Tab::make('SEO')
                            ->icon(Heroicon::MagnifyingGlass)
                            ->columns(2)
                            ->schema([
                                TextInput::make('meta_title')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'SEO title for this profile\'s page. Defaults to the person\'s name if blank.'),
                                Textarea::make('meta_description')
                                    ->maxLength(500)
                                    ->rows(3)
                                    ->hintIcon(Heroicon::InformationCircle, 'SEO meta description for this profile\'s page. 150–160 chars recommended.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
