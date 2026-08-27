<?php

namespace App\Cms\Sections;

use App\Cms\Support\CopyFields;
use App\Enums\SectionType;
use App\Models\Kb\HealthGoal;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

/**
 * The intake quiz itself, mounted inside a page.
 *
 * Distinct from `quiz-cta`, which is the INGRESS — a band that links to the
 * quiz. This one *is* the quiz: an operator drops it on a page and the wizard
 * runs there, so a landing page can capture without a second hop. Both exist
 * because they answer different questions, and a funnel usually wants the
 * ingress on the home page and the quiz on one dedicated page.
 *
 * THIS IS A FUNCTIONAL SECTION, which is a category the CMS did not have
 * before. Its content is the wizard it mounts, not the copy above it — so it
 * declares `hasIntrinsicContent()` and renders even when every field is
 * blank. Without that, an operator who dropped it in and wrote no heading
 * would watch the section silently disappear, because `has_content` would
 * correctly report that they authored nothing. That flag is a claim about the
 * COMPONENT, not a way around an empty-payload bug; see the contract.
 *
 * `goals` narrows which goals this instance offers. Empty means "all the
 * goals marked for the quiz", which is the common case and the right default:
 * an operator who adds a goal later expects it to appear without editing
 * every page carrying a quiz. Naming goals explicitly is for a landing page
 * built around one concern, where offering the full list would be a
 * distraction from the ad that brought the visitor.
 *
 * There is no consent or contact-capture field here yet. Those belong to the
 * wizard's own step and are authored copy with policy links attached; adding
 * half of that surface to this blueprint would put the legal text in two
 * places at once.
 */
class QuizSection extends SectionBlueprint
{
    public function type(): SectionType
    {
        return SectionType::Quiz;
    }

    public function label(): string
    {
        return 'Intake quiz';
    }

    public function icon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public function description(): ?string
    {
        return 'The health-goal quiz itself, running inline on the page: asks who the visitor is, then what they want, then shows a protocol. Use quiz-cta instead to link to it from elsewhere.';
    }

    public function hasIntrinsicContent(): bool
    {
        return true;
    }

    public function defaults(): array
    {
        return [
            'eyebrow' => null,
            'heading' => null,
            'heading_level' => 'h2',
            'body' => null,
            'goals' => [],
        ];
    }

    public function formSchema(): array
    {
        return [
            Section::make('Copy')
                ->description('All optional — the quiz runs with or without it.')
                ->components([
                    CopyFields::inline('eyebrow')
                        ->helperText('Small line above the headline. "Two minutes, no payment".'),
                    CopyFields::inline('heading')
                        ->helperText('Wrap the words you want picked out in italics — they render in the accent colour, not as italic type.'),
                    Select::make('heading_level')
                        ->label('Headline level')
                        ->options([
                            'h1' => 'H1 — this section is the page heading',
                            'h2' => 'H2 — the page has its own heading',
                        ])
                        ->default('h2')
                        ->native(false)
                        ->helperText('Use H1 only when this is the top of the page. Two H1s read as two documents to a search engine.'),
                    CopyFields::prose('body')
                        ->label('Intro')
                        ->helperText('One or two sentences above the first question. Keep it short — every line here pushes the first question further down, and on a phone that is where the funnel loses people.'),
                ]),

            Section::make('Which goals to offer')
                ->description('Leave empty to offer every goal marked "show in quiz". Pick specific goals only for a landing page built around one concern.')
                ->components([
                    Select::make('goals')
                        ->label('Goals')
                        ->multiple()
                        ->options(fn (): array => HealthGoal::query()
                            ->orderBy('position')
                            ->orderBy('name')
                            ->pluck('name', 'slug')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->helperText('Stored by slug. A goal you later withdraw from the quiz stops being offered here too, without this list needing an edit.'),
                ]),
        ];
    }
}
