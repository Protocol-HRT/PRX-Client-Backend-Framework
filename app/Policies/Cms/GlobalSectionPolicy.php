<?php

declare(strict_types=1);

namespace App\Policies\Cms;

use App\Models\Cms\GlobalSection;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GlobalSectionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:GlobalSection');
    }

    public function view(AuthUser $authUser, GlobalSection $globalSection): bool
    {
        return $authUser->can('View:GlobalSection');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:GlobalSection');
    }

    public function update(AuthUser $authUser, GlobalSection $globalSection): bool
    {
        return $authUser->can('Update:GlobalSection');
    }

    public function delete(AuthUser $authUser, GlobalSection $globalSection): bool
    {
        return $authUser->can('Delete:GlobalSection');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:GlobalSection');
    }

    public function restore(AuthUser $authUser, GlobalSection $globalSection): bool
    {
        return $authUser->can('Restore:GlobalSection');
    }

    public function forceDelete(AuthUser $authUser, GlobalSection $globalSection): bool
    {
        return $authUser->can('ForceDelete:GlobalSection');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:GlobalSection');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:GlobalSection');
    }

    public function replicate(AuthUser $authUser, GlobalSection $globalSection): bool
    {
        return $authUser->can('Replicate:GlobalSection');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:GlobalSection');
    }
}
