<?php

namespace App\Http\Controllers\Api\V1\Patient;

use App\Actions\Patient\LoginPatientAction;
use App\Actions\Patient\RegisterPatientAction;
use App\Data\Patient\PatientRegistrationData;
use App\Data\Patient\PatientResource;
use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Patient authentication — register, login, logout, me.
 *
 * Separate guard from the admin User auth. Patients authenticate
 * against the `patients` table; tokens carry `patient:*` abilities.
 */
class AuthController extends ApiController
{
    /**
     * Register a new patient account.
     *
     * Creates a local patient record and attempts to link an existing PRX chart
     * if one is found for the supplied email. Returns the patient resource and
     * a Sanctum bearer token.
     *
     * @tags PatientAuth
     *
     * @unauthenticated
     */
    public function register(Request $request, RegisterPatientAction $action): JsonResponse
    {
        $data = PatientRegistrationData::from($request);

        $patient = $action->execute($data);

        $token = $patient->createToken($request->input('device_name', 'api'), ['patient:*'])->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'patient' => PatientResource::fromModel($patient)->toArray(),
        ], status: 201);
    }

    /**
     * Authenticate a patient and issue a Sanctum token.
     *
     * Returns a `patient:*`-scoped bearer token. The token is shown only once.
     *
     * @tags PatientAuth
     *
     * @unauthenticated
     */
    public function login(Request $request, LoginPatientAction $action): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        try {
            ['patient' => $patient, 'token' => $token] = $action->execute(
                $validated['email'],
                $validated['password'],
                $validated['device_name'] ?? ($request->userAgent() ?? 'api'),
            );
        } catch (AuthenticationException $e) {
            throw ValidationException::withMessages(['email' => [$e->getMessage()]]);
        }

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'patient' => PatientResource::fromModel($patient)->toArray(),
        ]);
    }

    /**
     * Revoke the current patient token.
     *
     * @tags PatientAuth
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(['message' => 'Token revoked.']);
    }

    /**
     * Return the authenticated patient's profile.
     *
     * @tags PatientAuth
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(PatientResource::fromModel($request->user())->toArray());
    }
}
