<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('ver proyectos');
    }

    /**
     * Determine whether the user can view any models.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('view proyecto') && $user->hasProjectAccess($project);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('crear proyecto');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('editar proyecto') || $user->hasPermissionTo('completar tarea') && $user->hasProjectAccess($project);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('archivar proyecto') && $user->hasProjectAccess($project);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('restaurar proyecto') && $user->hasProjectAccess($project);
    }

    public function reorder(User $user): bool
    {
        return $user->hasPermissionTo('reordenar proyecto');
    }

    /**
     * Determine whether the user can complete the model.
     */
    public function complete(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('completar proyecto') && $user->hasProjectAccess($project);
    }

    /**
     * Determine whether the user can edit the model user access.
     */
    public function editUserAccess(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('editar acceso usuario al proyecto') && $user->hasProjectAccess($project);
    }

    public function check(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('completar proyecto') && $user->hasProjectAccess($project);
    }

}
