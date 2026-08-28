<?php

namespace App\Enums\Privacy;

/**
 * How sensitive one field's value is, and therefore where it may be sent.
 *
 * ─── Why this exists at the core and not in each driver ────────────────
 *
 * Destinations are not uniform. Twilio and AWS will sign a BAA; several
 * marketing platforms will not, and at least one bars health data in its
 * acceptable-use policy outright. The moment "push to a CRM" is a dropdown
 * beside a field mapper, nothing structural stops an operator mapping a quiz's
 * health answers into a destination that must never receive them — and the
 * mistake is invisible, because it looks exactly like every correct mapping.
 *
 * So the classification travels with the FIELD, in one vocabulary, rather than
 * each integration deciding for itself what it will accept. A driver can then
 * be wrong about a vendor's terms without that being a data-protection failure.
 *
 * ─── Why this is a setting and not a refusal ───────────────────────────
 *
 * Whether a given destination may receive PHI is a fact about the operator's
 * contracts, not about this code: an install with a signed BAA is entitled to
 * send health data to that vendor, and a hardcoded block would simply be wrong
 * for them. The classification says what a field IS; the instance says what it
 * is PERMITTED to receive; the mapper compares them. Neither half decides alone.
 *
 * ─── Only `Phi` gates anything, and that is worth stating plainly ──────
 *
 * `FieldMap` acts on `Phi`. `Sensitive` and `General` are informational: they
 * change how a field is labelled in the mapper, not whether it may be sent. So
 * `Sensitive` is not "a bit blocked" — it is not blocked at all, and reading it
 * as a mild refusal is how a health question ends up leaving the building. That
 * misreading already happened once here, in the quiz kind defaults.
 *
 * If a second gate is ever wanted — "warn on personal data to a new
 * destination" — add it explicitly. Do not give `Sensitive` a partial meaning
 * that only some call sites honour.
 */
enum DataClassification: string
{
    /** Ordinary business data — a UTM source, a cart subtotal, a country. */
    case General = 'general';

    /**
     * Personal data that is not clinical: a name, an email address, a phone
     * number. Most destinations accept it; it still should not be sprayed
     * around, and some jurisdictions treat it as regulated.
     */
    case Sensitive = 'sensitive';

    /**
     * Health information. Goals, symptoms, measurements, sex — anything from
     * which a clinical inference can be drawn.
     *
     * Note that an OUTCOME can disclose as much as an input: a list named
     * "TRT interest" or a recommended product name reveals health status just as
     * a symptom answer does. Classify by what the value discloses, not by which
     * table it came from.
     */
    case Phi = 'phi';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Sensitive => 'Personal',
            self::Phi => 'Health (PHI)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::General => 'Ordinary business data — attribution, totals, country. Safe to send anywhere.',
            self::Sensitive => 'Personal but not clinical — name, email, phone. Most destinations accept this.',
            self::Phi => 'Health information — goals, symptoms, measurements, sex. Only destinations you have marked as permitted may receive it.',
        };
    }

    /** @return array<string, string> value => label, for a Filament select. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
