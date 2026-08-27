<?php

namespace App\Enums\Kb;

/**
 * How a compound stands with the FDA, as a single primary answer.
 *
 * This is a knowledge-base field before it is a commerce field. It is surfaced
 * on every public monograph and mapped into schema.org's `legalStatus`, so it
 * is the one place a visitor is told, without having to read the prose, that
 * what they are looking at is not an approved medicine. For a compounding
 * pharmacy that is not decoration — it is the claim that has to be right.
 *
 * Deliberately ONE value per compound rather than a set of flags. Several of
 * these overlap in the real world (semaglutide is approved *and* widely
 * compounded; retatrutide is investigational *and* sold as a research
 * chemical), and a set would let an operator publish a monograph that says
 * both "approved" and "not for human use". The rule for picking: describe the
 * compound **as this pharmacy supplies it**, not the most flattering status
 * that exists somewhere for some product containing it.
 *
 * `null` is not absence of a status, it is "nobody has decided yet", and the
 * review gate genuinely blocks on it: `Compound::published()` requires a
 * non-null status, and the admin's publish toggle stays disabled without one.
 * The reason is concrete rather than procedural — with no status the public
 * page renders no not-approved notice and the structured data carries no
 * `legalStatus`, so a research chemical reads exactly like an approved
 * medicine.
 */
enum RegulatoryStatus: string
{
    case FdaApproved = 'fda_approved';
    case Investigational = 'investigational';
    case ResearchOnly = 'research_only';
    case Compounded = 'compounded';
    case Supplement = 'supplement';
    case Unapproved = 'unapproved';

    public function label(): string
    {
        return match ($this) {
            self::FdaApproved => 'FDA approved',
            self::Investigational => 'Investigational',
            self::ResearchOnly => 'Research use only',
            self::Compounded => 'Compounded preparation',
            self::Supplement => 'Dietary supplement',
            self::Unapproved => 'Marketed without FDA approval',
        };
    }

    /** The operator-facing definition. Also the public page's tooltip copy. */
    public function description(): string
    {
        return match ($this) {
            self::FdaApproved => 'An FDA-approved drug product exists and this is it.',
            self::Investigational => 'In FDA-registered clinical trials. Not approved for sale.',
            self::ResearchOnly => 'Supplied for laboratory research. No approved human use.',
            self::Compounded => 'Dispensed as a compounded preparation by a 503A/503B pharmacy, not as an approved product.',
            self::Supplement => 'Regulated as a dietary supplement, not as a drug.',
            self::Unapproved => 'Marketed in the US without FDA approval.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FdaApproved => 'success',
            self::Supplement => 'info',
            self::Compounded => 'primary',
            self::Investigational => 'warning',
            self::ResearchOnly, self::Unapproved => 'danger',
        };
    }

    /**
     * Whether the compound has an approved human use in the US.
     *
     * The public page leads with a warning notice when this is false, which is
     * the whole reason the field is required before publication.
     */
    public function isApprovedForHumanUse(): bool
    {
        return $this === self::FdaApproved;
    }

    /** @return array<string, string> value => label, for a Filament select. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
