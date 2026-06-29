<?php

namespace App\Actions\Patient;

use App\Models\Patient;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class LoginPatientAction
{
    /**
     * @return array{patient: Patient, token: string}
     *
     * @throws AuthenticationException
     */
    public function execute(string $email, string $password, string $deviceName = 'api'): array
    {
        $patient = Patient::where('email', $email)->first();

        if (! $patient || ! Hash::check($password, $patient->password)) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        $token = $patient->createToken($deviceName)->plainTextToken;

        return compact('patient', 'token');
    }
}
