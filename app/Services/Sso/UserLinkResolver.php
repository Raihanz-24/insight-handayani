<?php

declare(strict_types=1);

namespace App\Services\Sso;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Resolusi identitas SSO: `portal_uuid` → user Insight.
 *
 * ATURAN KEAMANAN (Opsi B):
 * - TIDAK ADA auto-create user. Bila UUID tak dikenal → kembalikan null.
 * - UUID di-generate di sisi Insight (Insight "pemilik" mapping).
 */
class UserLinkResolver
{
    /**
     * Cari user Insight berdasarkan portal_uuid. Null bila tak ada.
     */
    public function resolve(string $portalUuid): ?User
    {
        $portalUuid = trim($portalUuid);

        if ($portalUuid === '') {
            return null;
        }

        return User::query()->where('portal_uuid', $portalUuid)->first();
    }

    /**
     * Pastikan user punya portal_uuid; buat bila belum ada (idempotent).
     */
    public function ensureUuid(User $user): string
    {
        if (filled($user->portal_uuid)) {
            return (string) $user->portal_uuid;
        }

        return DB::transaction(function () use ($user): string {
            $fresh = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (filled($fresh->portal_uuid)) {
                return (string) $fresh->portal_uuid;
            }

            $fresh->portal_uuid = (string) Str::uuid();
            $fresh->save();

            return (string) $fresh->portal_uuid;
        });
    }
}
