<?php

declare(strict_types=1);

namespace App\Policies\Blog;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Blog\BlogTag;
use Illuminate\Auth\Access\HandlesAuthorization;

class BlogTagPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BlogTag');
    }

    public function view(AuthUser $authUser, BlogTag $blogTag): bool
    {
        return $authUser->can('View:BlogTag');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BlogTag');
    }

    public function update(AuthUser $authUser, BlogTag $blogTag): bool
    {
        return $authUser->can('Update:BlogTag');
    }

    public function delete(AuthUser $authUser, BlogTag $blogTag): bool
    {
        return $authUser->can('Delete:BlogTag');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BlogTag');
    }

    public function restore(AuthUser $authUser, BlogTag $blogTag): bool
    {
        return $authUser->can('Restore:BlogTag');
    }

    public function forceDelete(AuthUser $authUser, BlogTag $blogTag): bool
    {
        return $authUser->can('ForceDelete:BlogTag');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BlogTag');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BlogTag');
    }

    public function replicate(AuthUser $authUser, BlogTag $blogTag): bool
    {
        return $authUser->can('Replicate:BlogTag');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BlogTag');
    }

}