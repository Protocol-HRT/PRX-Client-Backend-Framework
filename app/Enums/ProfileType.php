<?php

namespace App\Enums;

enum ProfileType: string
{
    case Doctor = 'doctor';
    case Executive = 'executive';
    case SubjectMatterExpert = 'subject_matter_expert';
    case Owner = 'owner';
    case Patient = 'patient';
    case TeamMember = 'team_member';

    public function label(): string
    {
        return match ($this) {
            self::Doctor => 'Doctor',
            self::Executive => 'Executive',
            self::SubjectMatterExpert => 'Subject Matter Expert',
            self::Owner => 'Member / Owner',
            self::Patient => 'Patient',
            self::TeamMember => 'Team Member',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Doctor => 'info',
            self::Executive => 'warning',
            self::SubjectMatterExpert => 'success',
            self::Owner => 'primary',
            self::Patient => 'gray',
            self::TeamMember => 'gray',
        };
    }
}
