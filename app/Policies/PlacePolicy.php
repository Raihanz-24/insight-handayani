<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Place;
use App\Models\User;

/**
 * Hanya developer yang boleh mengelola (kelola tempat, tautan Maps, jadwal).
 * Role user: read-only.
 */
class PlacePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, User::roles(), true);
    }

    public function view(User $user, Place $place): bool
    {
        return in_array($user->role, User::roles(), true);
    }

    public function create(User $user): bool
    {
        return $user->isDeveloper();
    }

    public function update(User $user, Place $place): bool
    {
        return $user->isDeveloper();
    }

    public function delete(User $user, Place $place): bool
    {
        return $user->isDeveloper();
    }
}
