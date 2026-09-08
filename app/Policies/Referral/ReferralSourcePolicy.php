<?php

declare(strict_types=1);

namespace App\Policies\Referral;

use App\Models\Referral\ReferralSource;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReferralSourcePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReferralSource');
    }

    public function view(AuthUser $authUser, ReferralSource $referralSource): bool
    {
        return $authUser->can('View:ReferralSource');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReferralSource');
    }

    public function update(AuthUser $authUser, ReferralSource $referralSource): bool
    {
        return $authUser->can('Update:ReferralSource');
    }

    public function delete(AuthUser $authUser, ReferralSource $referralSource): bool
    {
        return $authUser->can('Delete:ReferralSource');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReferralSource');
    }

    public function restore(AuthUser $authUser, ReferralSource $referralSource): bool
    {
        return $authUser->can('Restore:ReferralSource');
    }

    public function forceDelete(AuthUser $authUser, ReferralSource $referralSource): bool
    {
        return $authUser->can('ForceDelete:ReferralSource');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReferralSource');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReferralSource');
    }

    public function replicate(AuthUser $authUser, ReferralSource $referralSource): bool
    {
        return $authUser->can('Replicate:ReferralSource');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReferralSource');
    }
}
