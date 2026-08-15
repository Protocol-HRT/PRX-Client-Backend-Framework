<?php

declare(strict_types=1);

namespace App\Policies\Cms;

use App\Models\Cms\FlexibleSectionType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FlexibleSectionTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FlexibleSectionType');
    }

    public function view(AuthUser $authUser, FlexibleSectionType $flexibleSectionType): bool
    {
        return $authUser->can('View:FlexibleSectionType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FlexibleSectionType');
    }

    public function update(AuthUser $authUser, FlexibleSectionType $flexibleSectionType): bool
    {
        return $authUser->can('Update:FlexibleSectionType');
    }

    public function delete(AuthUser $authUser, FlexibleSectionType $flexibleSectionType): bool
    {
        return $authUser->can('Delete:FlexibleSectionType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FlexibleSectionType');
    }

    public function restore(AuthUser $authUser, FlexibleSectionType $flexibleSectionType): bool
    {
        return $authUser->can('Restore:FlexibleSectionType');
    }

    public function forceDelete(AuthUser $authUser, FlexibleSectionType $flexibleSectionType): bool
    {
        return $authUser->can('ForceDelete:FlexibleSectionType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FlexibleSectionType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FlexibleSectionType');
    }

    public function replicate(AuthUser $authUser, FlexibleSectionType $flexibleSectionType): bool
    {
        return $authUser->can('Replicate:FlexibleSectionType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FlexibleSectionType');
    }
}
