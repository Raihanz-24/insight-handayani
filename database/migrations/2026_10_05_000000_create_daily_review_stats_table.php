<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ringkasan review HARIAN per tempat.
 *
 * Satu baris = satu hari (tanggal capture). Menyimpan:
 *  - jumlah review BARU yang tersimpan hari itu,
 *  - pecahan per bintang (berapa orang ★1..★5) dari review baru tsb,
 *  - total ulasan (snapshot) & selisih dari hari sebelumnya (validasi).
 *
 * Dipakai untuk menghitung "berapa orang memberi bintang X pada hari itu".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_review_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();

            // Tanggal (hari) ringkasan ini, dalam zona waktu analitik.
            $table->date('stat_date');

            // Jumlah review BARU yang tersimpan pada hari ini.
            $table->unsignedInteger('new_reviews')->default(0);

            // Pecahan per bintang dari review baru hari ini.
            $table->unsignedInteger('star_1')->default(0);
            $table->unsignedInteger('star_2')->default(0);
            $table->unsignedInteger('star_3')->default(0);
            $table->unsignedInteger('star_4')->default(0);
            $table->unsignedInteger('star_5')->default(0);

            // Total ulasan menurut snapshot Google pada hari ini (jika ada).
            $table->unsignedInteger('total_reviews')->nullable();

            // Selisih total ulasan vs hari sebelumnya (jumlah review baru menurut Google).
            $table->integer('reviews_delta')->nullable();

            // Rating rata-rata snapshot pada hari ini.
            $table->decimal('average_rating', 3, 2)->nullable();

            // Cakupan data (jumlah review yang tersimpan kumulatif).
            $table->unsignedInteger('synced_total')->default(0);

            $table->timestamps();

            $table->unique(['place_id', 'stat_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_review_stats');
    }
};
