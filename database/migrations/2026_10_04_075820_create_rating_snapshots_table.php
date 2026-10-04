<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('place_id')
                ->constrained('places')
                ->cascadeOnDelete();

            // Waktu pengambilan (dengan jam).
            $table->dateTime('captured_at');
            // Tanggal pengambilan (untuk agregasi harian/mingguan/bulanan).
            $table->date('captured_date');

            // Rating rata-rata & total ulasan kumulatif saat snapshot.
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('reviews_count')->nullable();

            // asal data: serpapi (otomatis) atau manual (input admin).
            $table->string('source')->default('serpapi');
            // status: ok / error.
            $table->string('status')->default('ok');
            $table->text('error_message')->nullable();
            // jejak mentah ringkas (mis. page terpanggil) — opsional.
            $table->json('meta')->nullable();

            $table->timestamps();

            // Hindari duplikat: satu snapshot per tempat per hari per sumber.
            $table->unique(['place_id', 'captured_date', 'source'], 'rating_snapshots_unique');
            $table->index('captured_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_snapshots');
    }
};
