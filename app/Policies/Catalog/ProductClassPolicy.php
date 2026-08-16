<?php

declare(strict_types=1);

namespace App\Policies\Catalog;

use App\Models\Catalog\ProductClass;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProductClassPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProductClass');
    }

    public function view(AuthUser $authUser, ProductClass $productClass): bool
    {
        return $authUser->can('View:ProductClass');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProductClass');
    }

    public function update(AuthUser $authUser, ProductClass $productClass): bool
    {
        return $authUser->can('Update:ProductClass');
    }

    public function delete(AuthUser $authUser, ProductClass $productClass): bool
    {
        return $authUser->can('Delete:ProductClass');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ProductClass');
    }

    public function restore(AuthUser $authUser, ProductClass $productClass): bool
    {
        return $authUser->can('Restore:ProductClass');
    }

    public function forceDelete(AuthUser $authUser, ProductClass $productClass): bool
    {
        return $authUser->can('ForceDelete:ProductClass');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProductClass');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProductClass');
    }

    public function replicate(AuthUser $authUser, ProductClass $productClass): bool
    {
        return $authUser->can('Replicate:ProductClass');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProductClass');
    }
}
