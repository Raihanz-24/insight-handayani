<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Validator;

/**
 * Halaman login panel Insight.
 *
 * Ketika SSO AKTIF (`sso.enabled=true`):
 *  - UI menampilkan overlay/popup yang mengarahkan user ke Portal.
 *  - `authenticate()` MENOLAK login manual di sisi server (pertahanan
 *    sebenarnya; overlay hanya lapisan UI yang bisa dilewati).
 *
 * Ketika SSO NONAKTIF (`sso.enabled=false`):
 *  - Login manual berjalan normal (tidak ada blokir).
 */
class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    protected static string $layout = 'filament.layouts.auth';

    /**
     * @var array{email: string, password: string, remember: bool}
     */
    public ?array $data = [
        'email' => '',
        'password' => '',
        'remember' => false,
    ];

    public ?string $loginErrorMessage = null;

    /**
     * Pesan error spesifik dari callback SSO (ditampilkan di dalam popup).
     */
    public ?string $ssoErrorMessage = null;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        // Pesan dari callback SSO (bila ada) — ditampilkan di dalam popup SSO
        // supaya penyebab kegagalan terlihat jelas (tidak tertutup overlay).
        $ssoError = session()->pull('sso_error');
        if (is_string($ssoError) && $ssoError !== '') {
            $this->ssoErrorMessage = $ssoError;

            // Bila SSO sedang OFF (mis. rollback), tetap tampilkan sebagai
            // pesan login biasa supaya tidak hilang.
            if (! config('sso.enabled')) {
                $this->loginErrorMessage = $ssoError;
            }
        }

        $this->data = [
            'email' => '',
            'password' => '',
            'remember' => false,
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Masuk - '.config('app.name');
    }

    public function getHeading(): string|Htmlable
    {
        return 'Selamat datang kembali';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Masuk untuk mengakses analitik Handayani.';
    }

    public function authenticate(): ?LoginResponse
    {
        $this->resetErrorBag();
        $this->loginErrorMessage = null;

        // SSO aktif → login langsung DIBLOKIR di sisi server.
        // (Overlay pop-up hanya lapisan UI; ini penjagaan sebenarnya agar
        //  tetap tak bisa ditembus walau CSS/UI dilewati.)
        // Bila SSO nonaktif (SSO_ENABLED=false), blok ini dilewati → login
        // langsung kembali normal (termasuk saat Portal bermasalah).
        if (config('sso.enabled')) {
            $this->showLoginFailure('Login langsung dinonaktifkan. Silakan masuk melalui Portal Handayani.');

            $this->data['password'] = '';

            return null;
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->showLoginFailure("Terlalu banyak percobaan masuk. Coba lagi dalam {$exception->secondsUntilAvailable} detik.");

            return null;
        }

        $validator = Validator::make($this->data ?? [], [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        if ($validator->fails()) {
            $this->showLoginFailure($validator->errors()->first());

            $this->data['password'] = '';

            return null;
        }

        $data = $validator->validated();

        if (! Filament::auth()->attempt([
            'email' => strtolower(trim($data['email'])),
            'password' => $data['password'],
        ], (bool) ($data['remember'] ?? false))) {
            $this->showLoginFailure('Email atau kata sandi tidak sesuai. Periksa kembali data login Anda.');
            $this->data['password'] = '';

            return null;
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->showLoginFailure('Akun Anda belum memiliki akses ke panel ini. Hubungi administrator.');
            $this->data['password'] = '';

            return null;
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function updated(string $property, mixed $value = null): void
    {
        $this->loginErrorMessage = null;
        $this->resetErrorBag();
    }

    private function showLoginFailure(string $message): void
    {
        $this->loginErrorMessage = $message;
    }
}
