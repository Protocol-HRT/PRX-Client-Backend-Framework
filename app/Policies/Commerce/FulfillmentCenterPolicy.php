<?php

declare(strict_types=1);

namespace App\Policies\Commerce;

use App\Models\Commerce\FulfillmentCenter;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FulfillmentCenterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FulfillmentCenter');
    }

    public function view(AuthUser $authUser, FulfillmentCenter $fulfillmentCenter): bool
    {
        return $authUser->can('View:FulfillmentCenter');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FulfillmentCenter');
    }

    public function update(AuthUser $authUser, FulfillmentCenter $fulfillmentCenter): bool
    {
        return $authUser->can('Update:FulfillmentCenter');
    }

    public function delete(AuthUser $authUser, FulfillmentCenter $fulfillmentCenter): bool
    {
        return $authUser->can('Delete:FulfillmentCenter');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FulfillmentCenter');
    }

    public function restore(AuthUser $authUser, FulfillmentCenter $fulfillmentCenter): bool
    {
        return $authUser->can('Restore:FulfillmentCenter');
    }

    public function forceDelete(AuthUser $authUser, FulfillmentCenter $fulfillmentCenter): bool
    {
        return $authUser->can('ForceDelete:FulfillmentCenter');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FulfillmentCenter');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FulfillmentCenter');
    }

    public function replicate(AuthUser $authUser, FulfillmentCenter $fulfillmentCenter): bool
    {
        return $authUser->can('Replicate:FulfillmentCenter');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FulfillmentCenter');
    }
}
