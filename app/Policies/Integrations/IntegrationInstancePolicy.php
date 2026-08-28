<?php

declare(strict_types=1);

namespace App\Policies\Integrations;

use App\Models\Integrations\IntegrationInstance;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class IntegrationInstancePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IntegrationInstance');
    }

    public function view(AuthUser $authUser, IntegrationInstance $integrationInstance): bool
    {
        return $authUser->can('View:IntegrationInstance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IntegrationInstance');
    }

    public function update(AuthUser $authUser, IntegrationInstance $integrationInstance): bool
    {
        return $authUser->can('Update:IntegrationInstance');
    }

    public function delete(AuthUser $authUser, IntegrationInstance $integrationInstance): bool
    {
        return $authUser->can('Delete:IntegrationInstance');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:IntegrationInstance');
    }

    public function restore(AuthUser $authUser, IntegrationInstance $integrationInstance): bool
    {
        return $authUser->can('Restore:IntegrationInstance');
    }

    public function forceDelete(AuthUser $authUser, IntegrationInstance $integrationInstance): bool
    {
        return $authUser->can('ForceDelete:IntegrationInstance');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IntegrationInstance');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IntegrationInstance');
    }

    public function replicate(AuthUser $authUser, IntegrationInstance $integrationInstance): bool
    {
        return $authUser->can('Replicate:IntegrationInstance');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IntegrationInstance');
    }
}
