<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Tautkan user lokal ke identitas Portal Handayani (SSO Opsi B).
 *
 * - `--uuid=...` : tetapkan portal_uuid tertentu (dari Portal).
 * - tanpa opsi   : generate portal_uuid baru (bila belum ada) → tampilkan
 *                  nilainya untuk dimasukkan ke menu "Tautan Akun" di Portal.
 *
 * Berguna di server yang mematikan `tinker`/`shell_exec`.
 */
class AssignPortalUuidCommand extends Command
{
    protected $signature = 'sso:assign-uuid
                            {email : Email user lokal}
                            {--uuid= : portal_uuid dari Portal (opsional)}
                            {--show : Hanya tampilkan nilai saat ini (tanpa mengubah)}';

    protected $description = 'Tetapkan / tampilkan portal_uuid (tautan SSO) untuk seorang user.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User dengan email {$email} tidak ditemukan.");

            return self::FAILURE;
        }

        if ($this->option('show')) {
            $this->line($user->hasPortalLink()
                ? "portal_uuid: {$user->portal_uuid}"
                : 'portal_uuid: (belum ditautkan)');

            return self::SUCCESS;
        }

        $uuid = (string) ($this->option('uuid') ?: '');

        if ($uuid !== '') {
            if (User::query()->where('portal_uuid', $uuid)->whereKeyNot($user->getKey())->exists()) {
                $this->error('portal_uuid tersebut sudah dipakai user lain.');

                return self::FAILURE;
            }

            $user->forceFill(['portal_uuid' => $uuid])->save();
            $this->info("portal_uuid untuk {$email} diset ke: {$uuid}");

            return self::SUCCESS;
        }

        // Tanpa --uuid: gunakan yang ada, atau generate baru.
        if ($user->hasPortalLink()) {
            $this->info("portal_uuid untuk {$email} (sudah ada): {$user->portal_uuid}");

            return self::SUCCESS;
        }

        $newUuid = (string) Str::uuid();
        $user->forceFill(['portal_uuid' => $newUuid])->save();

        $this->info("portal_uuid baru untuk {$email} dibuat:");
        $this->line("  {$newUuid}");
        $this->newLine();
        $this->warn('Masukkan nilai di atas ke Portal → menu "Tautan Akun" untuk user ini.');

        return self::SUCCESS;
    }
}
