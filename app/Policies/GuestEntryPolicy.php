<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GuestEntry;
use App\Models\User;

/**
 * Input data kendaraan = developer. Role user: read-only.
 */
class GuestEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, User::roles(), true);
    }

    public function view(User $user, GuestEntry $entry): bool
    {
        return in_array($user->role, User::roles(), true);
    }

    public function create(User $user): bool
    {
        return $user->isDeveloper();
    }

    public function update(User $user, GuestEntry $entry): bool
    {
        return $user->isDeveloper();
    }

    public function delete(User $user, GuestEntry $entry): bool
    {
        return $user->isDeveloper();
    }
}
