<?php

namespace App\Enums\Catalog;

/**
 * Who a substance may be recommended to, as a single primary answer.
 *
 * This is a SAFETY field before it is a merchandising one. It exists because
 * the recommendation chain would otherwise offer testosterone to a woman and
 * estradiol to a man — not a ranking mistake that a weight could soften, but a
 * wrong answer. `relevance_weight` orders things that are all acceptable;
 * this decides what is acceptable at all, and it is applied BEFORE ranking.
 *
 * It records **physiological applicability**, not gender identity. Those come
 * apart in exactly the population this pharmacy serves — a trans woman on HRT
 * wants estradiol — and conflating them would encode a clinical claim in a
 * field named after an identity. What the visitor is ASKED, and how that
 * question is worded, is admin-authored copy on the quiz step; this enum only
 * says which bucket an answer lands in. Keeping the two apart means the
 * operator can rewrite the question, or add a separate one, without a
 * migration.
 *
 * `Any` is the default and the common case, deliberately: most of this
 * catalog (peptides, NAD+, B12, carnitine) is unisex, and a field that
 * defaulted to a restriction would quietly hide two thirds of the shelf the
 * first time someone forgot to set it. The failure direction matters — an
 * unset field should over-offer a safe substance, never under-offer, because
 * an operator notices a missing product and never notices an absent one.
 *
 * There is deliberately NO product-level column mirroring this. A substance's
 * applicability is a fact about the substance, and the same ingredient backs
 * several SKUs; stating it per product means restating it per product, and
 * drifting the first time a new testosterone item ships with the flag
 * forgotten. Products derive — see Ingredient::eligibilityFor().
 */
enum SexEligibility: string
{
    case Any = 'any';
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Anyone',
            self::Male => 'Male only',
            self::Female => 'Female only',
        };
    }

    /** The operator-facing definition. Also the admin hint copy. */
    public function description(): string
    {
        return match ($this) {
            self::Any => 'No sex-based restriction. The default, and correct for most peptides and supplements.',
            self::Male => 'Only recommend to male visitors — e.g. testosterone, ED medication.',
            self::Female => 'Only recommend to female visitors — e.g. estradiol, estriol, progesterone.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Any => 'gray',
            self::Male => 'info',
            self::Female => 'danger',
        };
    }

    /**
     * Whether this substance may be offered to a visitor who answered `$sex`.
     *
     * A null answer permits everything. That is the honest reading of "not
     * asked yet" — the quiz filters once an answer exists, and a visitor
     * browsing the catalogue directly has not been asked at all. It is also
     * why the quiz must not treat a skipped step as a filter.
     */
    public function permits(?string $sex): bool
    {
        if ($this === self::Any || $sex === null || $sex === '') {
            return true;
        }

        return $this->value === self::normalize($sex)?->value;
    }

    /**
     * Map a free-form answer onto a bucket, or null when it does not land in
     * one. `leads.gender` is varchar(32) by contract (it mirrors PRX's
     * prefill, which accepts "male"/"1"/self-described text), so this has to
     * cope with more than the three cases above rather than cast.
     *
     * Anything unrecognised returns null, and null permits everything. A
     * self-described answer must not silently narrow someone's options to a
     * bucket a string comparison guessed for them.
     */
    public static function normalize(?string $sex): ?self
    {
        return match (strtolower(trim((string) $sex))) {
            'male', 'm', '1', 'man' => self::Male,
            'female', 'f', '2', 'woman' => self::Female,
            default => null,
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
