<?php

declare(strict_types=1);

namespace App\Policies\Kb;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Kb\Compound;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompoundPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Compound');
    }

    public function view(AuthUser $authUser, Compound $compound): bool
    {
        return $authUser->can('View:Compound');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Compound');
    }

    public function update(AuthUser $authUser, Compound $compound): bool
    {
        return $authUser->can('Update:Compound');
    }

    public function delete(AuthUser $authUser, Compound $compound): bool
    {
        return $authUser->can('Delete:Compound');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Compound');
    }

    public function restore(AuthUser $authUser, Compound $compound): bool
    {
        return $authUser->can('Restore:Compound');
    }

    public function forceDelete(AuthUser $authUser, Compound $compound): bool
    {
        return $authUser->can('ForceDelete:Compound');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Compound');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Compound');
    }

    public function replicate(AuthUser $authUser, Compound $compound): bool
    {
        return $authUser->can('Replicate:Compound');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Compound');
    }

}