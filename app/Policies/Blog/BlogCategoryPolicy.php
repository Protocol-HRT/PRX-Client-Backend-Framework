<?php

declare(strict_types=1);

namespace App\Policies\Blog;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Blog\BlogCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class BlogCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BlogCategory');
    }

    public function view(AuthUser $authUser, BlogCategory $blogCategory): bool
    {
        return $authUser->can('View:BlogCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BlogCategory');
    }

    public function update(AuthUser $authUser, BlogCategory $blogCategory): bool
    {
        return $authUser->can('Update:BlogCategory');
    }

    public function delete(AuthUser $authUser, BlogCategory $blogCategory): bool
    {
        return $authUser->can('Delete:BlogCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BlogCategory');
    }

    public function restore(AuthUser $authUser, BlogCategory $blogCategory): bool
    {
        return $authUser->can('Restore:BlogCategory');
    }

    public function forceDelete(AuthUser $authUser, BlogCategory $blogCategory): bool
    {
        return $authUser->can('ForceDelete:BlogCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BlogCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BlogCategory');
    }

    public function replicate(AuthUser $authUser, BlogCategory $blogCategory): bool
    {
        return $authUser->can('Replicate:BlogCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BlogCategory');
    }

}