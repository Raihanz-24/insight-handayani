<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_DEVELOPER = 'developer';

    public const ROLE_USER = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'portal_uuid',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Apakah user ber-role developer (akses penuh).
     */
    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER;
    }

    /**
     * Siapa saja yang boleh masuk panel admin (kedua role).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, self::roles(), true);
    }

    /**
     * Apakah user hanya viewer (role 'user').
     */
    public function isViewer(): bool
    {
        return ! $this->isDeveloper();
    }

    /**
     * Daftar role yang valid (untuk form & validasi).
     *
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [self::ROLE_DEVELOPER, self::ROLE_USER];
    }

    /**
     * Label manusiawi untuk role.
     */
    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_DEVELOPER => 'Developer',
            default => 'User',
        };
    }

    /**
     * Apakah akun ini sudah ditautkan ke Portal (punya portal_uuid)?
     */
    public function hasPortalLink(): bool
    {
        return filled($this->portal_uuid);
    }
}
