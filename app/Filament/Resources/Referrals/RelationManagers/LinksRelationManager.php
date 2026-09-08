<?php

namespace App\Filament\Resources\Referrals\RelationManagers;

use App\Actions\Referral\MintReferralCodeAction;
use App\Models\Referral\ReferralClick;
use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * The trackable codes belonging to one referral source.
 *
 * Lives here rather than as its own resource because a code with no source to
 * credit is meaningless — a top-level list would make orphans easy to create.
 *
 * There is no delete action. A code that has been printed on anything cannot be
 * un-printed, and its clicks are the evidence behind a commission; switching it
 * off stops it earning while leaving the trail intact.
 */
class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    protected static ?string $title = 'Tracking codes';

    protected static ?string $modelLabel = 'code';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->placeholder('Generated automatically')
                    ->helperText('Leave blank to generate one. Codes are not case-sensitive.')
                    ->hintIcon(Heroicon::InformationCircle, 'This is what goes after ?ref= in the link. Changing it on a live code breaks every flyer already carrying it.'),

                TextInput::make('campaign_name')
                    ->maxLength(255)
                    ->placeholder('Spring mailshot')
                    ->hintIcon(Heroicon::InformationCircle, 'For your own reporting — one source can run several campaigns and compare them.'),

                TextInput::make('destination_path')
                    ->label('Lands on')
                    ->maxLength(512)
                    ->placeholder('/  (the home page)')
                    ->prefix('/')
                    ->formatStateUsing(fn (?string $state): string => ltrim((string) $state, '/'))
                    ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : '/'.ltrim(trim($state), '/'))
                    ->hintIcon(Heroicon::InformationCircle, 'A page on the storefront, e.g. stacks/metabolic-reset. Leave blank for the home page.'),

                DateTimePicker::make('expires_at')
                    ->label('Expires')
                    ->placeholder('Never')
                    ->hintIcon(Heroicon::InformationCircle, 'After this, arrivals are still recorded but no longer credited.'),

                Toggle::make('is_active')
                    ->default(true)
                    ->hintIcon(Heroicon::InformationCircle, 'Switch off instead of deleting — the click history stays intact.'),
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
        return auth()->user()?->can('create', ReferralLink::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->badge()
                    ->copyable()
                    ->copyMessage('Code copied'),

                TextColumn::make('campaign_name')
                    ->label('Campaign')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('url')
                    ->label('Link')
                    ->state(fn (ReferralLink $r): ?string => $r->url())
                    ->copyable()
                    ->copyMessage('Link copied')
                    ->limit(44)
                    ->tooltip(fn (ReferralLink $r): ?string => $r->url()),

                // Derived from the ledger, never a counter — see the migration.
                TextColumn::make('clicks')
                    ->label('Clicks')
                    ->alignRight()
                    ->state(fn (ReferralLink $r): int => ReferralClick::where('referral_link_id', $r->id)->count())
                    ->description(fn (ReferralLink $r): string => ReferralClick::where('referral_link_id', $r->id)
                        ->distinct('visitor_id')->count('visitor_id').' unique'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New code')
                    ->authorize(fn (): bool => static::canCreateRecords())
                    // Mint here rather than in a model hook: generating a code is
                    // a decision ("I want a new one"), not a property of saving,
                    // and an operator who typed their own must keep it.
                    ->mutateDataUsing(function (array $data): array {
                        if (blank($data['code'] ?? null)) {
                            /** @var ReferralSource $source */
                            $source = $this->getOwnerRecord();
                            $data['code'] = app(MintReferralCodeAction::class)->execute($source);
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('qr')
                    ->label('QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->modalHeading(fn (ReferralLink $r): string => "QR code — {$r->code}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (ReferralLink $r): HtmlString => new HtmlString(
                        '<div style="text-align:center">'
                        .'<img src="'.e((string) $r->qrCodeSvg()).'" alt="QR code" style="width:260px;height:260px;margin:0 auto">'
                        .'<p style="margin-top:.75rem;font-family:monospace;font-size:.8rem;word-break:break-all">'
                        .e((string) $r->url()).'</p>'
                        .'<p style="margin-top:.5rem;font-size:.8rem;opacity:.7">Right-click the image to save it, or use Download for a print-ready file.</p>'
                        .'</div>'
                    ))
                    ->extraModalFooterActions(fn (ReferralLink $r): array => [
                        Action::make('download')
                            ->label('Download SVG')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->action(fn () => response()->streamDownload(
                                function () use ($r): void {
                                    // Decode the data URI back to raw SVG: a
                                    // vector file is what a printer wants, and
                                    // it scales to any size without blurring.
                                    $svg = (string) $r->qrCodeSvg();
                                    echo base64_decode((string) preg_replace('#^data:image/svg\+xml;base64,#', '', $svg));
                                },
                                "referral-{$r->code}.svg",
                                ['Content-Type' => 'image/svg+xml'],
                            )),
                    ]),

                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
