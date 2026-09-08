<?php

namespace App\Actions\Patient;

use App\Data\Patient\PatientRegistrationData;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterPatientAction
{
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

            return $patient->refresh();
        });
    }
}
