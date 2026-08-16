<?php

declare(strict_types=1);

namespace App\Policies\Catalog;

use App\Models\Catalog\AdministrationMethod;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AdministrationMethodPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AdministrationMethod');
    }

    public function view(AuthUser $authUser, AdministrationMethod $administrationMethod): bool
    {
        return $authUser->can('View:AdministrationMethod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AdministrationMethod');
    }

    public function update(AuthUser $authUser, AdministrationMethod $administrationMethod): bool
    {
        return $authUser->can('Update:AdministrationMethod');
    }

    public function delete(AuthUser $authUser, AdministrationMethod $administrationMethod): bool
    {
        return $authUser->can('Delete:AdministrationMethod');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:AdministrationMethod');
    }

    public function restore(AuthUser $authUser, AdministrationMethod $administrationMethod): bool
    {
        return $authUser->can('Restore:AdministrationMethod');
    }

    public function forceDelete(AuthUser $authUser, AdministrationMethod $administrationMethod): bool
    {
        return $authUser->can('ForceDelete:AdministrationMethod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AdministrationMethod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AdministrationMethod');
    }

    public function replicate(AuthUser $authUser, AdministrationMethod $administrationMethod): bool
    {
        return $authUser->can('Replicate:AdministrationMethod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AdministrationMethod');
    }
}
