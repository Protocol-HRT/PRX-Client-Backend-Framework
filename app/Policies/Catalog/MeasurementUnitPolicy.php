<?php

declare(strict_types=1);

namespace App\Policies\Catalog;

use App\Models\Catalog\MeasurementUnit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MeasurementUnitPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MeasurementUnit');
    }

    public function view(AuthUser $authUser, MeasurementUnit $measurementUnit): bool
    {
        return $authUser->can('View:MeasurementUnit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MeasurementUnit');
    }

    public function update(AuthUser $authUser, MeasurementUnit $measurementUnit): bool
    {
        return $authUser->can('Update:MeasurementUnit');
    }

    public function delete(AuthUser $authUser, MeasurementUnit $measurementUnit): bool
    {
        return $authUser->can('Delete:MeasurementUnit');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MeasurementUnit');
    }

    public function restore(AuthUser $authUser, MeasurementUnit $measurementUnit): bool
    {
        return $authUser->can('Restore:MeasurementUnit');
    }

    public function forceDelete(AuthUser $authUser, MeasurementUnit $measurementUnit): bool
    {
        return $authUser->can('ForceDelete:MeasurementUnit');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MeasurementUnit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MeasurementUnit');
    }

    public function replicate(AuthUser $authUser, MeasurementUnit $measurementUnit): bool
    {
        return $authUser->can('Replicate:MeasurementUnit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MeasurementUnit');
    }
}
