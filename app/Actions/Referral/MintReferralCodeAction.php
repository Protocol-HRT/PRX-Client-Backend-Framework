<?php

namespace App\Actions\Referral;

use App\Models\Referral\ReferralLink;
use App\Models\Referral\ReferralSource;
use Illuminate\Support\Str;

/**
 * Generate a referral code for a source.
 *
 * Shape is `{prefix}-{random}`, e.g. `acme-7k2f9x`. The prefix makes a code
 * legible to a human reading a spreadsheet of them — you can tell whose it is
 * without a lookup — and the random tail is what stops one affiliate guessing
 * another's codes and, more practically, what allows the same partner several
 * campaigns.
 *
 * THE ALPHABET EXCLUDES `0`, `o`, `1`, `l` and `i`. Codes get read aloud, typed
 * off printed flyers and dictated down a phone; a commission lost to an `l` that
 * was really a `1` is an argument nobody can settle. Ambiguity costs more here
 * than the ~1 bit of entropy it saves.
 */
class MintReferralCodeAction
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const TAIL_LENGTH = 6;

    /** Enough attempts that exhausting them means something is wrong, not unlucky. */
    private const MAX_ATTEMPTS = 12;

    public function execute(ReferralSource $source): string
    {
        // Fall back to the slug when no prefix is set, so a code is always
        // traceable to a source by eye even for a hastily created row.
        $prefix = Str::lower(trim((string) ($source->code_prefix ?: $source->slug)));
        $prefix = Str::limit(preg_replace('/[^a-z0-9]+/', '', $prefix) ?: 'ref', 12, '');

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $prefix.'-'.$this->tail();

            if (! $this->codeIsTaken($code)) {
                return $code;
            }
        }

        // Collision after a dozen tries means the alphabet or the space is
        // wrong, not that this call was unlucky. Fail loudly rather than
        // returning a code that is about to violate the unique index.
        throw new \RuntimeException("Could not mint a unique referral code for source {$source->id}.");
    }

    /**
     * Is this code already spoken for?
     *
     * **`withTrashed()` is load-bearing.** `referral_links.code` is UNIQUE at the
     * database level and a soft delete leaves the row — and therefore the code —
     * in place. Without this, the minter would happily hand back a retired
     * partner's code, the insert would then throw a unique violation, and worse,
     * a code that had been printed on real material would be pointing at a
     * different partner if it ever were reinstated.
     *
     * Public so the behaviour can be asserted directly. Testing it through
     * `execute()` alone is theatre: the tail space is 31^6, so a random collision
     * never actually occurs in a test run and the assertion passes whether the
     * check is here or not.
     */
    public function codeIsTaken(string $code): bool
    {
        return ReferralLink::withTrashed()->where('code', $code)->exists();
    }

    private function tail(): string
    {
        $alphabet = self::ALPHABET;
        $tail = '';

        for ($i = 0; $i < self::TAIL_LENGTH; $i++) {
            $tail .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $tail;
    }
}
