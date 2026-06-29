<?php

namespace App\Actions\Patient;

use App\Models\Patient;
use App\Services\PrescribeRx\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class IssuePortalTokenAction
{
    /**
     * Least-privilege patient ability set, per TokenAbility::forUserType(PATIENT).
     * Explicit list so future PRX additions don't silently widen our tokens.
     */
    private const PATIENT_ABILITIES = [
        'patient:read',
        'patient:update',
        'patient:vitals',
        'order:read',
        'encounter:read',
        'prescription:read',
        'telehealth:read',
        'telehealth:submit',
        'product:read',
    ];

    /** Evict from cache this many seconds before PRX expires the token (clock-skew guard). */
    private const TTL_BUFFER_SECONDS = 60;

    public function __construct(private readonly Client $prx) {}

    /**
     * Return a valid PRX patient token, minting a new one only when the cache is cold.
     */
    public function execute(Patient $patient): string
    {
        if (! $patient->hasPrxChart()) {
            throw new \RuntimeException('Patient has no linked PRX chart. Complete intake first.');
        }

        return Cache::get($this->cacheKey($patient)) ?? $this->mint($patient);
    }

    /**
     * Force-evict the cache and mint a fresh token. Use after a PRX 401.
     */
    public function renew(Patient $patient): string
    {
        $this->forget($patient);

        return $this->mint($patient);
    }

    /**
     * Evict the cached token without minting a replacement.
     */
    public function forget(Patient $patient): void
    {
        Cache::forget($this->cacheKey($patient));
    }

    private function mint(Patient $patient): string
    {
        $response = $this->prx->issuePatientToken($patient->prx_patient_chart_id, self::PATIENT_ABILITIES);

        $ttl = $this->ttlFromExpiresAt($response['expires_at'] ?? null);

        Cache::put($this->cacheKey($patient), $response['token'], $ttl);

        return $response['token'];
    }

    private function cacheKey(Patient $patient): string
    {
        return 'prx_patient_token:'.$patient->prx_patient_chart_id;
    }

    private function ttlFromExpiresAt(?string $expiresAt): int
    {
        if ($expiresAt) {
            $remaining = (int) now()->diffInSeconds(Carbon::parse($expiresAt), absolute: false);

            return max(30, $remaining - self::TTL_BUFFER_SECONDS);
        }

        return 1500; // 25-min fallback for stub mode
    }
}
