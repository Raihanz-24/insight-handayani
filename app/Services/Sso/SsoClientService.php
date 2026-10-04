<?php

declare(strict_types=1);

namespace App\Services\Sso;

use Illuminate\Support\Facades\Http;

/**
 * Klien HTTP ke Authorization Server Portal (Opsi B).
 *
 * - Membangun URL authorize (PKCE S256).
 * - Menukar code secara SERVER-TO-SERVER (tidak ada token ke browser).
 * - TIDAK mencatat/men-log secret, code, atau verifier.
 */
class SsoClientService
{
    /**
     * URL authorize di Portal.
     */
    public function buildAuthorizeUrl(string $state, string $codeChallenge): string
    {
        $this->assertSecureTransport();

        $query = http_build_query([
            'client_id' => (string) config('sso.client_id'),
            'redirect_uri' => (string) config('sso.redirect_uri'),
            'response_type' => 'code',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);

        return rtrim((string) config('sso.portal_base_url'), '/')
            .config('sso.authorize_path')
            .'?'.$query;
    }

    /**
     * Tukar authorization code di Portal.
     *
     * @return array{portal_uuid: string, email: ?string, status: string}
     *
     * @throws SsoException bila gagal (membawa `reason` aman untuk log & UI).
     */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $this->assertSecureTransport();

        $response = Http::asForm()
            ->timeout((int) config('sso.timeout', 10))
            ->acceptJson()
            ->post(rtrim((string) config('sso.portal_base_url'), '/').config('sso.token_path'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'client_id' => (string) config('sso.client_id'),
                'client_secret' => (string) config('sso.client_secret'),
                'redirect_uri' => (string) config('sso.redirect_uri'),
            ]);

        if (! $response->successful()) {
            // Ambil HANYA kode error Portal (mis. invalid_client, invalid_grant).
            // JANGAN log body mentah — bisa memuat detail/token.
            $error = $this->safeErrorCode($response->json());

            throw new SsoException(
                message: 'Penukaran kode SSO gagal ('.$error.').',
                reason: $error,
                httpStatus: $response->status(),
            );
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['portal_uuid'])) {
            // Respons sukses tapi tanpa identitas → anggap gagal tak terduga.
            throw new SsoException(
                message: 'Respons SSO tidak valid (tanpa portal_uuid).',
                reason: 'invalid_response',
                httpStatus: $response->status(),
            );
        }

        return [
            'portal_uuid' => (string) $data['portal_uuid'],
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'status' => (string) ($data['status'] ?? 'inactive'),
        ];
    }

    /**
     * Ambil kode error dari respons Portal dengan aman.
     *
     * Hanya menerima token pendek [a-z0-9_] (mis. invalid_client). Bila tidak
     * sesuai bentuknya, kembalikan kode generik + status HTTP.
     */
    private function safeErrorCode(mixed $json): string
    {
        $candidate = is_array($json)
            ? (string) ($json['error'] ?? '')
            : '';

        if ($candidate !== '' && preg_match('/^[a-z0-9_]{1,40}$/', $candidate) === 1) {
            return $candidate;
        }

        return 'unknown';
    }

    /**
     * Generator PKCE (RFC 7636) — dipakai controller /sso/login.
     *
     * @return array{verifier: string, challenge: string}
     */
    public function makePkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    /**
     * State acak (opaque, session-bound, one-time).
     */
    public function makeState(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    /**
     * Pastikan transport ke Portal memakai HTTPS (kecuali lingkungan lokal/
     * testing). Mencegah client_secret + code + verifier terkirim via cleartext.
     *
     * @throws SsoException
     */
    private function assertSecureTransport(): void
    {
        $baseUrl = (string) config('sso.portal_base_url');

        if (str_starts_with($baseUrl, 'https://')) {
            return;
        }

        // Boleh http:// HANYA di lokal/testing (mis. 127.0.0.1 untuk E2E).
        if (app()->environment('local', 'testing')) {
            return;
        }

        throw new SsoException(
            message: 'SSO memerlukan HTTPS pada SSO_PORTAL_BASE_URL di lingkungan non-lokal.',
            reason: 'insecure_transport',
        );
    }
}
