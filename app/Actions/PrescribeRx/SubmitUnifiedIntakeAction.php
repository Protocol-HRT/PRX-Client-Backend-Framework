<?php

namespace App\Actions\PrescribeRx;

use App\Actions\Concerns\Transacts;
use App\Contracts\Telehealth\TelehealthProviderInterface;
use App\Data\PrescribeRx\UnifiedIntakeRequestData;
use App\Data\PrescribeRx\UnifiedIntakeResponseData;

/**
 * Submits a patient + encounter + intake answers to the active telehealth
 * provider in one call. Wraps the provider call in a DB::transaction so any
 * local `Lead` / `Order` / `Patient` rows we create alongside the remote
 * encounter roll back together if the provider rejects the payload.
 *
 * Future: persist a local `intake_submissions` row before the remote call so
 * we have an audit trail even on transient failures; mark it `submitted` after
 * the provider returns success.
 */
class SubmitUnifiedIntakeAction
{
    use Transacts;

    public function __construct(
        protected readonly TelehealthProviderInterface $telehealth,
    ) {}

    public function execute(UnifiedIntakeRequestData $data): UnifiedIntakeResponseData
    {
        return $this->tx(function () use ($data) {
            $result = $this->telehealth->submitIntake($data->toArray());

            return UnifiedIntakeResponseData::from($result);
        });
    }
}
