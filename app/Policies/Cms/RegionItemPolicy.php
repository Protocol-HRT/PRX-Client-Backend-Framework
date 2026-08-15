<?php

declare(strict_types=1);

namespace App\Policies\Cms;

use App\Models\Cms\RegionItem;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RegionItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RegionItem');
    }

    public function view(AuthUser $authUser, RegionItem $regionItem): bool
    {
        return $authUser->can('View:RegionItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RegionItem');
    }

    public function update(AuthUser $authUser, RegionItem $regionItem): bool
    {
        return $authUser->can('Update:RegionItem');
    }

    public function delete(AuthUser $authUser, RegionItem $regionItem): bool
    {
        return $authUser->can('Delete:RegionItem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RegionItem');
    }

    public function restore(AuthUser $authUser, RegionItem $regionItem): bool
    {
        return $authUser->can('Restore:RegionItem');
    }

    public function forceDelete(AuthUser $authUser, RegionItem $regionItem): bool
    {
        return $authUser->can('ForceDelete:RegionItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RegionItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RegionItem');
    }

    public function replicate(AuthUser $authUser, RegionItem $regionItem): bool
    {
        return $authUser->can('Replicate:RegionItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RegionItem');
    }
}
