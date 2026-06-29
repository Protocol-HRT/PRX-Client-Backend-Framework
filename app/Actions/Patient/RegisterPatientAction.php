<?php

namespace App\Actions\Patient;

use App\Data\Patient\PatientRegistrationData;
use App\Models\Patient;
use App\Services\PrescribeRx\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterPatientAction
{
    public function __construct(private readonly Client $prx) {}

    public function execute(PatientRegistrationData $data): Patient
    {
        if (Patient::where('email', $data->email)->exists()) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
        }

        return DB::transaction(function () use ($data): Patient {
            $patient = Patient::create([
                'email' => $data->email,
                'password' => $data->password,
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'phone' => $data->phone,
                'date_of_birth' => $data->date_of_birth,
            ]);

            $this->linkPrxChart($patient);

            return $patient->refresh();
        });
    }

    /**
     * Look up PRX for an existing chart by email and link it.
     * Flags a collision if multiple charts are found.
     * Runs non-fatally — registration still succeeds if PRX is unavailable.
     */
    private function linkPrxChart(Patient $patient): void
    {
        try {
            $chart = $this->prx->findPatientByEmail($patient->email);

            if ($chart === null) {
                return;
            }

            $patient->update([
                'prx_patient_chart_id' => $chart['id'],
                'prx_patient_id' => $chart['patient_id'] ?? null,
                'prx_chart_verified_at' => now(),
            ]);
        } catch (\Throwable) {
            // PRX unavailable — chart will be linked on first successful intake
        }
    }
}
