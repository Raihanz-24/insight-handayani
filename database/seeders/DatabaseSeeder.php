<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed database aplikasi.
     *
     * Sengaja TIDAK membuat user admin dari sini agar tidak ada kredensial
     * default yang bocor. Buat admin dengan:
     *   php artisan make:filament-user
     */
    public function run(): void
    {
        $this->call([
            PlaceSeeder::class,
        ]);
    }
}
