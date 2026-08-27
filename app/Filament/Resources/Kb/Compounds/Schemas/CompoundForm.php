<?php

namespace App\Filament\Resources\Kb\Compounds\Schemas;

use App\Cms\Support\CopyFields;
use App\Enums\Kb\RegulatoryStatus;
use App\Enums\ProfileType;
use App\Models\Catalog\Ingredient;
use App\Models\Content\Profile;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The monograph edit form.
 *
 * Two things here are not cosmetic:
 *
 * - **The publish toggle is disabled until a regulatory status is set**, and
 *   nothing else blocks it. The API enforces the same one
 *   (`Compound::published()`), but an operator discovering the rule by
 *   publishing a page that then does not appear is a bad way to learn it.
 *   A reviewer is optional by design — see the model for why requiring one
 *   would manufacture bylines rather than reviews.
 * - **The prose fields use the `table` tool.** 82 of the imported monographs
 *   carry dosing-titration tables, and Filament's editor drops markup it has
 *   no extension registered for — so a toolbar without `table` would silently
 *   strip a titration schedule the first time anyone opened and saved the row.
 * - **They offer h3, not h2.** The public page wraps each field in its own
 *   <h2>; a heading authored inside the field sits below that.
 */
class CompoundForm
{
    /**
     * The prose toolbar, plus tables, minus h2 — see the class docblock.
     *
     * No h2: the public page supplies each field's own <h2> heading, so the
     * top level available INSIDE a field is h3. Offering h2 here would let an
     * operator author a heading that outranks the section containing it.
     */
    private const MONOGRAPH_TOOLBAR = [
        'bold', 'italic', 'link',
        'h3',
        'bulletList', 'orderedList', 'blockquote',
        'table',
        'undo', 'redo',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Compound')
                    ->vertical()
                    ->persistTabInQueryString('compound-tab')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Identity ──────────────────────────────────
                        Tab::make('Identity')
                            ->icon(Heroicon::DocumentText)
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->hintIcon(Heroicon::InformationCircle, 'Display name, as a reader should see it — "Pyridoxine (Vitamin B6)", not "b6".')
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true)
                                    ->hintIcon(Heroicon::InformationCircle, 'The public URL of this monograph. Changing it breaks every existing link and any search ranking the page has earned.')
                                    ->helperText('Lowercase letters, numbers, hyphens.'),
                                TextInput::make('tagline')
                                    ->maxLength(255)
                                    ->columnSpanFull()
                                    ->hintIcon(Heroicon::InformationCircle, 'One line under the name, in plain language — what this compound is for, not what it is.')
                                    ->placeholder('Appetite, energy and fat, addressed at once.'),
                                TagsInput::make('brand_names')
                                    ->hintIcon(Heroicon::InformationCircle, 'Trade names this is sold under. Shown on the page and searched by the index.'),
                                TagsInput::make('synonyms')
                                    ->hintIcon(Heroicon::InformationCircle, 'Other names a reader might search for, including the spellings you do NOT use.'),
                                FileUpload::make('hero_image_path')
                                    ->image()
                                    ->disk('public')
                                    ->directory('kb/compounds')
                                    ->columnSpanFull()
                                    ->hintIcon(Heroicon::InformationCircle, 'Optional image at the head of the monograph.'),
                            ]),

                        // ── Classification ────────────────────────────
                        Tab::make('Classification')
                            ->icon(Heroicon::Tag)
                            ->columns(2)
                            ->schema([
                                Toggle::make('is_peptide')
                                    ->label('This compound is a peptide')
                                    ->hintIcon(Heroicon::InformationCircle, 'The knowledge base shows peptides by default. Leave this off for antibiotics, vitamins, topicals and hormones — they stay in the library but out of the peptide index.')
                                    ->helperText('Single amino acids (arginine, methionine) are not peptides. Tripeptides (glutathione, KPV) are.'),
                                Select::make('regulatory_status')
                                    ->options(RegulatoryStatus::options())
                                    ->native(false)
                                    ->hintIcon(Heroicon::InformationCircle, 'How this compound stands with the FDA, AS THIS PHARMACY SUPPLIES IT — not the most favourable status that exists for some product somewhere. It is printed on the public page.')
                                    ->helperText(fn (?string $state): string => filled($state)
                                        ? (RegulatoryStatus::tryFrom((string) $state)?->description() ?? '')
                                        : 'Required before the monograph can be published — with no status the public page shows no not-approved notice at all.')
                                    // Both panels read this, and they are in
                                    // different tabs, so the publish toggle
                                    // needs the value live rather than on save.
                                    ->live(),
                                TextInput::make('route_of_administration')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'Subcutaneous injection, oral, topical…')
                                    ->placeholder('Subcutaneous injection'),
                                TextInput::make('compound_class')
                                    ->maxLength(255)
                                    ->hintIcon(Heroicon::InformationCircle, 'Free text carried over from the source data. Not a browse category — health goals are the taxonomy.')
                                    ->disabled()
                                    ->dehydrated(),
                                Select::make('ingredient_id')
                                    ->label('Catalog ingredient')
                                    ->options(fn (): array => Ingredient::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull()
                                    ->hintIcon(Heroicon::InformationCircle, 'Links this monograph to what the shop sells. The public page then lists the products containing it — the one thing a generic health wiki cannot do.'),
                                Section::make('Evidence ranking')
                                    ->description('Both blank on every imported row. They rank compounds against a health goal, which arrives with the protocol builder.')
                                    ->columns(2)
                                    ->collapsed()
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('evidence_tier')->maxLength(32),
                                        TextInput::make('evidence_score')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(9.99)
                                            ->step(0.01),
                                    ]),
                            ]),

                        // ── Monograph ─────────────────────────────────
                        Tab::make('Monograph')
                            ->icon(Heroicon::BookOpen)
                            ->columns(1)
                            ->schema([
                                CopyFields::prose('description')
                                    ->label('Summary')
                                    ->helperText('Two or three sentences. Used on the index card, and as the fallback meta description.'),
                                self::monograph('overview', 'What it is and where it came from.'),
                                self::monograph('mechanism_of_action', 'How it works.'),
                                self::monograph('pharmacology', 'Absorption, half-life, metabolism. The densest clinical section — written for prescribers.'),
                                self::monograph('clinical_evidence', 'What the studies actually found.'),
                                self::monograph('dosing_guidelines', 'Titration schedules live here. Most of these carry a table — keep it.'),
                                self::monograph('safety_profile', 'Adverse effects, contraindications, interactions.'),
                                self::monograph('patient_summary', 'The one section already written for the reader rather than the prescriber. If you only rewrite one, rewrite the others to match this.'),
                                TagsInput::make('clinical_references')
                                    ->label('References')
                                    ->columnSpanFull()
                                    ->hintIcon(Heroicon::InformationCircle, 'Citations to primary literature. These are a large part of why a health page is trusted — do not thin them out.'),
                            ]),

                        // ── Review ────────────────────────────────────
                        Tab::make('Review')
                            ->icon(Heroicon::CheckBadge)
                            ->columns(2)
                            ->schema([
                                Section::make('Clinical review')
                                    ->description('Optional. This content is summarised from your clinical literature, not authored by a provider — so a byline is only worth adding when someone has genuinely read the page. Attaching a name to a review that did not happen is worse than no name.')
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->schema([
                                        Select::make('reviewed_by_profile_id')
                                            ->label('Reviewed by')
                                            ->options(fn (): array => Profile::query()
                                                // Doctors and subject-matter experts. A reviewer
                                                // has to be someone whose name and credentials
                                                // can stand on a public health page.
                                                ->whereIn('type', [ProfileType::Doctor, ProfileType::SubjectMatterExpert])
                                                ->orderBy('name')
                                                ->get()
                                                ->mapWithKeys(fn (Profile $p): array => [
                                                    $p->id => trim($p->name.($p->credentials ? ', '.$p->credentials : '')),
                                                ])
                                                ->all())
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->hintIcon(Heroicon::InformationCircle, 'A published clinician profile. Their name and credentials appear on the public monograph as "Reviewed by".')
                                            ->helperText('Leave blank unless a clinician has actually read this page. Manage the list under Content → Profiles.'),
                                        DateTimePicker::make('reviewed_at')
                                            ->label('Reviewed on')
                                            ->seconds(false)
                                            ->hintIcon(Heroicon::InformationCircle, 'The date shown as "last reviewed" on the page and in its structured data.'),
                                        Textarea::make('review_notes')
                                            ->rows(3)
                                            ->columnSpanFull()
                                            ->hintIcon(Heroicon::InformationCircle, 'Internal. What was checked, what was changed, what is still open. Never published.'),
                                    ]),
                                Section::make('Publication')
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->schema([
                                        Toggle::make('is_published')
                                            ->label('Published')
                                            ->disabled(fn (callable $get): bool => self::blockers($get) !== [])
                                            ->helperText(function (callable $get): string {
                                                $blockers = self::blockers($get);

                                                return $blockers === []
                                                    ? 'Live on the public knowledge base.'
                                                    : 'Not publishable yet — '.implode(' and ', $blockers)
                                                        .'. The public API hides a monograph missing either, even when this is on.';
                                            }),
                                    ]),
                                Section::make('Provenance')
                                    ->description('Where this text came from — summarised from your clinical literature corpus by the source pipeline. The document count is shown on the public page. Read-only.')
                                    ->columns(3)
                                    ->collapsed()
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('source_system')->disabled(),
                                        TextInput::make('content_model')
                                            ->label('Drafted by model')
                                            ->disabled(),
                                        DateTimePicker::make('content_generated_at')
                                            ->label('Drafted on')
                                            ->disabled(),
                                        TextInput::make('source_document_count')
                                            ->label('Clinical sources retrieved')
                                            ->disabled(),
                                        TextInput::make('source_dosing_count')
                                            ->label('Dosing sources retrieved')
                                            ->disabled(),
                                    ]),
                            ]),

                        // ── SEO ───────────────────────────────────────
                        Tab::make('SEO')
                            ->icon(Heroicon::MagnifyingGlass)
                            ->columns(1)
                            ->schema([
                                TextInput::make('meta_title')
                                    ->maxLength(60)
                                    ->hintIcon(Heroicon::InformationCircle, 'Falls back to the compound name when blank.'),
                                Textarea::make('meta_description')
                                    ->rows(3)
                                    ->maxLength(160)
                                    ->hintIcon(Heroicon::InformationCircle, 'Falls back to the summary when blank.'),
                                FileUpload::make('og_image_path')
                                    ->image()
                                    ->disk('public')
                                    ->directory('kb/compounds/og')
                                    ->hintIcon(Heroicon::InformationCircle, 'Image used when the page is shared on social media.'),
                            ]),
                    ]),
            ]);
    }

    /**
     * What is still missing before this monograph may be published.
     *
     * Mirrors `Compound::published()`. Returning the reasons rather than a
     * boolean is the whole point: a greyed-out toggle that does not say why is
     * the version of this control an operator files a bug about.
     *
     * @return list<string>
     */
    private static function blockers(callable $get): array
    {
        return array_values(array_filter([
            blank($get('regulatory_status')) ? 'it needs a regulatory status' : null,
        ]));
    }

    private static function monograph(string $name, string $help): \Filament\Forms\Components\RichEditor
    {
        return CopyFields::prose($name)
            ->toolbarButtons(self::MONOGRAPH_TOOLBAR)
            ->helperText($help);
    }
}
