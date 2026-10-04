<?php

declare(strict_types=1);

namespace App\Services\Sso;

use RuntimeException;

/**
 * Kegagalan pada alur SSO yang AMAN untuk dicatat & ditampilkan.
 *
 * `reason` hanya berisi kode pendek yang tidak sensitif (mis. `invalid_client`,
 * `invalid_grant`, `http_401`) — TIDAK pernah memuat secret/token/body mentah.
 */
class SsoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'unknown',
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Pesan ramah untuk user, berdasarkan kode penyebab.
     */
    public function userMessage(): string
    {
        return match ($this->reason) {
            'invalid_client' => 'Kredensial aplikasi (client_id/client_secret) tidak dikenali Portal. Hubungi administrator.',
            'invalid_grant' => 'Kode otorisasi tidak valid atau sudah kedaluwarsa. Silakan coba masuk lagi.',
            'redirect_uri_mismatch', 'invalid_redirect_uri' => 'Redirect URI tidak cocok dengan yang terdaftar di Portal. Hubungi administrator.',
            'unauthorized_client' => 'Aplikasi ini tidak diizinkan memakai alur ini. Hubungi administrator.',
            'access_denied' => 'Anda tidak memiliki akses ke aplikasi ini melalui Portal.',
            default => 'Gagal memverifikasi SSO ke Portal. Silakan coba lagi atau masuk manual.',
        };
    }
}
