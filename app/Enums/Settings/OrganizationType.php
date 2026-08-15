<?php

namespace App\Enums\Settings;

enum OrganizationType: string
{
    case MedicalOrganization = 'MedicalOrganization';
    case MedicalClinic = 'MedicalClinic';
    case Pharmacy = 'Pharmacy';
    case HealthAndBeautyBusiness = 'HealthAndBeautyBusiness';

    public function label(): string
    {
        return match ($this) {
            self::MedicalOrganization => 'Medical Organization',
            self::MedicalClinic => 'Medical Clinic',
            self::Pharmacy => 'Pharmacy',
            self::HealthAndBeautyBusiness => 'Health & Beauty Business',
        };
    }
}
