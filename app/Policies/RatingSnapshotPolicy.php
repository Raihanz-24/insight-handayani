<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RatingSnapshot;
use App\Models\User;

/**
 * Snapshot rating dikelola developer (termasuk tombol "Ambil Sekarang").
 * Role user: read-only.
 */
class RatingSnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, User::roles(), true);
    }

    public function view(User $user, RatingSnapshot $snapshot): bool
    {
        return in_array($user->role, User::roles(), true);
    }

    public function create(User $user): bool
    {
        return $user->isDeveloper();
    }

    public function update(User $user, RatingSnapshot $snapshot): bool
    {
        return $user->isDeveloper();
    }

    public function delete(User $user, RatingSnapshot $snapshot): bool
    {
        return $user->isDeveloper();
    }
}
