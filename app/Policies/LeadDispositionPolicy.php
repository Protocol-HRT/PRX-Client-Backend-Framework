<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeadDisposition;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LeadDispositionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LeadDisposition');
    }

    public function view(AuthUser $authUser, LeadDisposition $leadDisposition): bool
    {
        return $authUser->can('View:LeadDisposition');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LeadDisposition');
    }

    public function update(AuthUser $authUser, LeadDisposition $leadDisposition): bool
    {
        return $authUser->can('Update:LeadDisposition');
    }

    public function delete(AuthUser $authUser, LeadDisposition $leadDisposition): bool
    {
        return $authUser->can('Delete:LeadDisposition');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LeadDisposition');
    }

    public function restore(AuthUser $authUser, LeadDisposition $leadDisposition): bool
    {
        return $authUser->can('Restore:LeadDisposition');
    }

    public function forceDelete(AuthUser $authUser, LeadDisposition $leadDisposition): bool
    {
        return $authUser->can('ForceDelete:LeadDisposition');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LeadDisposition');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LeadDisposition');
    }

    public function replicate(AuthUser $authUser, LeadDisposition $leadDisposition): bool
    {
        return $authUser->can('Replicate:LeadDisposition');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LeadDisposition');
    }
}
