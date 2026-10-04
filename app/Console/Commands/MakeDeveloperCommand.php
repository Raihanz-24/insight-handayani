<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Membuat / mengubah user menjadi role developer.
 *
 * Dipakai untuk bootstrap akun developer tanpa tinker (berguna di server
 * yang mematikan `shell_exec`).
 */
class MakeDeveloperCommand extends Command
{
    protected $signature = 'analytics:make-developer
                            {email : Email user}
                            {--name= : Nama (dipakai bila user baru)}
                            {--password= : Password (dipakai bila user baru; minimal 8, huruf+angka)}';

    protected $description = 'Buat atau jadikan user sebagai developer (akses penuh).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $existing->forceFill(['role' => User::ROLE_DEVELOPER])->save();
            $this->info("User {$email} sekarang ber-role developer.");

            return self::SUCCESS;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Nama', 'Developer'));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::min(8)->letters()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_DEVELOPER,
        ]);

        $this->info("User developer {$email} dibuat.");

        return self::SUCCESS;
    }
}
