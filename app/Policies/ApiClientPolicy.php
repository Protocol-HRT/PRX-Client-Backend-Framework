<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiClient;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ApiClientPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ApiClient');
    }

    public function view(AuthUser $authUser, ApiClient $apiClient): bool
    {
        return $authUser->can('View:ApiClient');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ApiClient');
    }

    public function update(AuthUser $authUser, ApiClient $apiClient): bool
    {
        return $authUser->can('Update:ApiClient');
    }

    public function delete(AuthUser $authUser, ApiClient $apiClient): bool
    {
        return $authUser->can('Delete:ApiClient');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ApiClient');
    }

    public function restore(AuthUser $authUser, ApiClient $apiClient): bool
    {
        return $authUser->can('Restore:ApiClient');
    }

    public function forceDelete(AuthUser $authUser, ApiClient $apiClient): bool
    {
        return $authUser->can('ForceDelete:ApiClient');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ApiClient');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ApiClient');
    }

    public function replicate(AuthUser $authUser, ApiClient $apiClient): bool
    {
        return $authUser->can('Replicate:ApiClient');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ApiClient');
    }
}
