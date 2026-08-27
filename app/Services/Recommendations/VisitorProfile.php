<?php

namespace App\Services\Recommendations;

use App\Models\Lead;

/**
 * What the intake quiz knows about the visitor when it resolves a protocol.
 *
 * Both fields are nullable and a null is PERMISSIVE — it means "not asked",
 * not "answered nothing". That distinction is the whole safety story of this
 * object: a visitor who skipped a step, or who is browsing the catalogue
 * without taking the quiz at all, must see the full shelf rather than a
 * silently narrowed one. Narrowing on an absent answer would hide products
 * from people who never told us anything, and nobody would ever notice.
 *
 * A value object rather than two loose arguments because the pair travels
 * together through resolver, API and PDF, and because `(?string, ?int)`
 * transposes silently at a call site while `VisitorProfile` does not.
 */
final readonly class VisitorProfile
{
    public function __construct(
        public ?string $sex = null,
        public ?int $age = null,
    ) {}

    /**
     * Build from whatever a lead actually holds.
     *
     * Prefers `date_of_birth` over `age` when both exist: a birth date is what
     * a completed clinical intake captured, the age is what the marketing quiz
     * captured, and the clinical answer wins. See the `leads.age` migration.
     */
    public static function fromLead(Lead $lead): self
    {
        return new self(
            sex: $lead->gender,
            age: $lead->effectiveAge(),
        );
    }

    /** True when nothing is known, so no filtering should be attempted. */
    public function isEmpty(): bool
    {
        return $this->sex === null && $this->age === null;
    }
}
