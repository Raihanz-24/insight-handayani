<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            // Link Google Maps apa adanya (short link / URL panjang) — untuk referensi
            // & di-resolve ulang bila perlu.
            $table->string('maps_url', 2048)->nullable()->after('name');
            // Koordinat (opsional; hasil parse link).
            $table->decimal('latitude', 10, 7)->nullable()->after('maps_url');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            // Mode analisis per tempat: 'off' | 'manual' | 'scheduled'.
            $table->string('analysis_mode')->default('manual')->after('is_active');
            // Interval jadwal (hari): 1 = harian, 2 = tiap 2 hari, 7 = mingguan, dst.
            $table->unsignedSmallInteger('schedule_interval_days')->nullable()->after('analysis_mode');
            // Jam eksekusi (0-23) dalam zona ANALYTICS_TIMEZONE.
            $table->unsignedTinyInteger('schedule_hour')->nullable()->after('schedule_interval_days');
            // Kapan terakhir kali sukses diambil (diperbarui otomatis).
            $table->dateTime('last_synced_at')->nullable()->after('schedule_hour');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn([
                'maps_url',
                'latitude',
                'longitude',
                'analysis_mode',
                'schedule_interval_days',
                'schedule_hour',
                'last_synced_at',
            ]);
        });
    }
};
