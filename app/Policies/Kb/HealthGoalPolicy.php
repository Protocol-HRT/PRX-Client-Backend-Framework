<?php

declare(strict_types=1);

namespace App\Policies\Kb;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Kb\HealthGoal;
use Illuminate\Auth\Access\HandlesAuthorization;

class HealthGoalPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HealthGoal');
    }

    public function view(AuthUser $authUser, HealthGoal $healthGoal): bool
    {
        return $authUser->can('View:HealthGoal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HealthGoal');
    }

    public function update(AuthUser $authUser, HealthGoal $healthGoal): bool
    {
        return $authUser->can('Update:HealthGoal');
    }

    public function delete(AuthUser $authUser, HealthGoal $healthGoal): bool
    {
        return $authUser->can('Delete:HealthGoal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:HealthGoal');
    }

    public function restore(AuthUser $authUser, HealthGoal $healthGoal): bool
    {
        return $authUser->can('Restore:HealthGoal');
    }

    public function forceDelete(AuthUser $authUser, HealthGoal $healthGoal): bool
    {
        return $authUser->can('ForceDelete:HealthGoal');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HealthGoal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HealthGoal');
    }

    public function replicate(AuthUser $authUser, HealthGoal $healthGoal): bool
    {
        return $authUser->can('Replicate:HealthGoal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HealthGoal');
    }

}