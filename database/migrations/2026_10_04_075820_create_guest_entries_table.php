<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_entries', function (Blueprint $table) {
            $table->id();

            // Awal minggu (SENIN) — dinormalkan dari input admin.
            $table->date('week_start');
            // Akhir minggu (MINGGU) — diturunkan dari week_start (week_start + 6 hari).
            $table->date('week_end');

            // Jumlah kendaraan yang masuk pada minggu tersebut (input manual).
            $table->unsignedInteger('vehicles')->default(0);

            $table->text('note')->nullable();

            // Siapa yang menginput (nullable bila dihapus).
            $table->foreignId('entered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Satu baris per minggu.
            $table->unique('week_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_entries');
    }
};
