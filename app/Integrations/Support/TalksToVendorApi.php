<?php

namespace App\Integrations\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The HTTP manners every vendor driver shares.
 *
 * ─── Why retries are narrow ────────────────────────────────────────────
 *
 * Only 429 and 503 are retried, and only those. A 4xx means the request was
 * wrong and will be wrong again; retrying it burns rate limit and delays the
 * error the operator needs to see. A 500 from a vendor may or may not have taken
 * effect — and these calls are not idempotent, so a blind retry can enrol
 * somebody twice or send a second message. When in doubt, fail and let the run
 * log say so; the workflow engine deliberately does not retry chains for the
 * same reason.
 *
 * ─── Secrets are scrubbed from anything that escapes ───────────────────
 *
 * The vendor's own message is the most useful thing to show an operator, and it
 * is also written verbatim to `workflow_action_runs.error` — an UNENCRYPTED
 * table. Some APIs echo the offending credential back ("the key pk_live_... is
 * not valid"), which would quietly persist a live secret into a table any admin
 * with run-log access can read. So every credential this trait has handed out is
 * redacted from the message before it leaves. Cheap, and the alternative is a
 * leak nobody would ever notice.
 *
 * ─── Why errors are re-thrown with the vendor's own words ──────────────
 *
 * The message lands in `workflow_action_runs.error` and in the operator's
 * "Test connection" dialog, and those are the only two places anybody will look.
 * "Request failed" sends them to a log they cannot read; "invalid API key" does
 * not. The body is truncated because some vendors answer errors with an entire
 * HTML page.
 */
trait TalksToVendorApi
{
    /**
     * Every secret read through `credential()` on this instance, so it can be
     * scrubbed from anything that escapes. Drivers are resolved per call, so
     * this never outlives the request that populated it.
     *
     * @var list<string>
     */
    private array $secrets = [];

    protected function http(): PendingRequest
    {
        return Http::timeout(15)
            ->connectTimeout(5)
            // 429 and 503 only — see the trait doc. `throw: false` so the caller
            // decides; retryable statuses are handled here, everything else by
            // ok() below.
            ->retry(2, 500, function (\Throwable $e, PendingRequest $request): bool {
                return $e instanceof RequestException
                    && in_array($e->response->status(), [429, 503], true);
            }, throw: false);
    }

    /**
     * Return the response, or throw with something an operator can act on.
     */
    protected function ok(Response $response, string $what): Response
    {
        if ($response->successful()) {
            return $response;
        }

        throw new RuntimeException($this->scrub(sprintf(
            '%s failed (HTTP %d): %s',
            $what,
            $response->status(),
            $this->explain($response),
        )));
    }

    /**
     * Pull something human out of a vendor error body.
     *
     * Each vendor buries the useful sentence somewhere different, so this looks
     * in the three shapes they actually use before giving up and truncating the
     * raw body.
     */
    private function explain(Response $response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            // JSON:API (Klaviyo), and the flat shapes GoHighLevel and Twilio use.
            $detail = $body['errors'][0]['detail']
                ?? $body['errors'][0]['message']
                ?? $body['message']
                ?? $body['error']
                ?? null;

            if (is_string($detail) && $detail !== '') {
                return $detail;
            }

            if (is_array($body['message'] ?? null)) {
                return implode('; ', array_map('strval', $body['message']));
            }
        }

        // SCRUB BEFORE TRUNCATING. The other order can cut a secret in half and
        // leave the first 30 characters of it in the message, which the outer
        // scrub can no longer match.
        return Str::limit($this->scrub(trim($response->body())), 200) ?: 'no response body';
    }

    /**
     * Remove this integration's own credentials from a message.
     *
     * Longest first, so a token that contains a shorter one is not left
     * half-redacted. Values under 8 characters are skipped — they are more
     * likely to be a placeholder than a real secret, and redacting a short
     * string would mangle unrelated words in the vendor's sentence.
     */
    private function scrub(string $message): string
    {
        $secrets = array_filter(array_unique($this->secrets), fn (string $s): bool => strlen($s) >= 8);

        usort($secrets, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace($secrets, '[redacted]', $message);
    }

    /**
     * A required credential, or a sentence naming what is missing.
     *
     * Credentials live in an encrypted blob that an operator half-filled once, so
     * "undefined array key" is a real possible outcome and a useless one.
     */
    protected function credential(array $credentials, string $key, string $label): string
    {
        $value = $credentials[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("This integration is missing its {$label}. Add it and save.");
        }

        // Remembered so it can be scrubbed from any error that escapes. This is
        // the single place a driver reads a secret, which is what makes the
        // scrubbing reliable rather than something each driver must remember.
        $this->secrets[] = trim($value);

        return trim($value);
    }
}
