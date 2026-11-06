<?php

namespace App\Traits;

use App\Enums\UserRole;

trait HasRoles
{
    /**
     * Check if the user has any of the given roles.
     *
     * @param  UserRole|string|array<int, UserRole|string>  $roles
     */
    public function hasRole(UserRole|string|array $roles): bool
    {
        foreach ((array) $roles as $role) {
            $role = $role instanceof UserRole ? $role : UserRole::tryFrom($role);

            if ($role !== null && $this->role === $role) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isEngineering(): bool
    {
        return $this->role === UserRole::Engineering;
    }

    /**
     * Admins upload geodata through the Data panel.
     */
    public function canUploadData(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Admins manage published layers (rename, delete).
     */
    public function canManageData(): bool
    {
        return $this->isAdmin();
    }
}
