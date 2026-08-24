<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmailSubscriber;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EmailSubscriberPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EmailSubscriber');
    }

    public function view(AuthUser $authUser, EmailSubscriber $emailSubscriber): bool
    {
        return $authUser->can('View:EmailSubscriber');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EmailSubscriber');
    }

    public function update(AuthUser $authUser, EmailSubscriber $emailSubscriber): bool
    {
        return $authUser->can('Update:EmailSubscriber');
    }

    public function delete(AuthUser $authUser, EmailSubscriber $emailSubscriber): bool
    {
        return $authUser->can('Delete:EmailSubscriber');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EmailSubscriber');
    }

    public function restore(AuthUser $authUser, EmailSubscriber $emailSubscriber): bool
    {
        return $authUser->can('Restore:EmailSubscriber');
    }

    public function forceDelete(AuthUser $authUser, EmailSubscriber $emailSubscriber): bool
    {
        return $authUser->can('ForceDelete:EmailSubscriber');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EmailSubscriber');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EmailSubscriber');
    }

    public function replicate(AuthUser $authUser, EmailSubscriber $emailSubscriber): bool
    {
        return $authUser->can('Replicate:EmailSubscriber');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EmailSubscriber');
    }
}
