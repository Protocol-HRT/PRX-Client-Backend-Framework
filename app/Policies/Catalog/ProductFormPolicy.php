<?php

declare(strict_types=1);

namespace App\Policies\Catalog;

use App\Models\Catalog\ProductForm;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ProductFormPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProductForm');
    }

    public function view(AuthUser $authUser, ProductForm $productForm): bool
    {
        return $authUser->can('View:ProductForm');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProductForm');
    }

    public function update(AuthUser $authUser, ProductForm $productForm): bool
    {
        return $authUser->can('Update:ProductForm');
    }

    public function delete(AuthUser $authUser, ProductForm $productForm): bool
    {
        return $authUser->can('Delete:ProductForm');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ProductForm');
    }

    public function restore(AuthUser $authUser, ProductForm $productForm): bool
    {
        return $authUser->can('Restore:ProductForm');
    }

    public function forceDelete(AuthUser $authUser, ProductForm $productForm): bool
    {
        return $authUser->can('ForceDelete:ProductForm');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProductForm');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProductForm');
    }

    public function replicate(AuthUser $authUser, ProductForm $productForm): bool
    {
        return $authUser->can('Replicate:ProductForm');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProductForm');
    }
}
