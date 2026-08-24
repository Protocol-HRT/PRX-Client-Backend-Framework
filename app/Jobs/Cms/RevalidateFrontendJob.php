<?php

namespace App\Jobs\Cms;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tells the decoupled frontend(s) to purge the cache tags a CMS write
 * touched, so admin edits appear immediately instead of waiting out the
 * frontend's ISR window.
 *
 * Queued on purpose: an admin's save must never block on — or fail because
 * of — an HTTP call to a separate application. A frontend that is down,
 * slow, or mid-deploy costs the operator nothing; the content is already
 * saved and the frontend's own TTL remains the backstop.
 */
class RevalidateFrontendJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    /**
     * @param  list<string>  $tags
     */
    public function __construct(private readonly array $tags) {}

    public function handle(): void
    {
        $secret = (string) config('cms.frontend.revalidate_secret');
        $timeout = (int) config('cms.frontend.revalidate_timeout', 5);

        foreach ($this->endpoints() as $url) {
            $response = Http::timeout($timeout)
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->acceptJson()
                ->post($url, ['tags' => $this->tags]);

            if ($response->failed()) {
                // Log and throw so the queue retries; a permanently
                // unreachable frontend simply exhausts its attempts.
                Log::warning('Frontend revalidation failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'tags' => $this->tags,
                ]);

                $response->throw();
            }
        }
    }

    /**
     * @return list<string>
     */
    private function endpoints(): array
    {
        $configured = (string) config('cms.frontend.revalidate_url');

        return collect(explode(',', $configured))
            ->map(fn (string $url): string => trim($url))
            ->filter()
            ->values()
            ->all();
    }
}
