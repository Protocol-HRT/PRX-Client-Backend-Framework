<?php

declare(strict_types=1);

namespace App\Policies\Payments;

use App\Models\Payments\MerchantAccount;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MerchantAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MerchantAccount');
    }

    public function view(AuthUser $authUser, MerchantAccount $merchantAccount): bool
    {
        return $authUser->can('View:MerchantAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MerchantAccount');
    }

    public function update(AuthUser $authUser, MerchantAccount $merchantAccount): bool
    {
        return $authUser->can('Update:MerchantAccount');
    }

    public function delete(AuthUser $authUser, MerchantAccount $merchantAccount): bool
    {
        return $authUser->can('Delete:MerchantAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MerchantAccount');
    }

    public function restore(AuthUser $authUser, MerchantAccount $merchantAccount): bool
    {
        return $authUser->can('Restore:MerchantAccount');
    }

    public function forceDelete(AuthUser $authUser, MerchantAccount $merchantAccount): bool
    {
        return $authUser->can('ForceDelete:MerchantAccount');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MerchantAccount');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MerchantAccount');
    }

    public function replicate(AuthUser $authUser, MerchantAccount $merchantAccount): bool
    {
        return $authUser->can('Replicate:MerchantAccount');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MerchantAccount');
    }
}
