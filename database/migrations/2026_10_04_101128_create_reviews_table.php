<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Review individual dari Google Maps (via SerpApi google_maps_reviews).
        // Inti analisa: distribusi BINTANG per rentang tanggal review.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('place_id')
                ->constrained('places')
                ->cascadeOnDelete();

            // review_id dari Google (bila ada); dipakai sebagai kunci idempoten.
            $table->string('review_id')->nullable();
            // Kunci unik internal bila review_id kosong (hash dari author+rating+tanggal+snippet).
            $table->string('review_key');

            // Bintang (1..5). Disimpan integer (dibulatkan dari rating 1.0..5.0).
            $table->unsignedTinyInteger('rating');
            // Rating asli (mis. 4.0 / 4.5) bila ingin presisi.
            $table->decimal('rating_raw', 3, 2)->nullable();

            // Tanggal review di Google (dari iso_date) — dasar filter periode.
            $table->date('review_date');
            // Waktu lengkap review (opsional).
            $table->dateTime('reviewed_at')->nullable();

            $table->string('author_name')->nullable();
            $table->string('author_id')->nullable();
            $table->unsignedInteger('likes')->nullable();
            $table->text('snippet')->nullable();

            // Jejak waktu pengambilan & mentah ringkas.
            $table->dateTime('captured_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // Idempoten: satu review hanya disimpan sekali per tempat.
            $table->unique(['place_id', 'review_key'], 'reviews_place_key_unique');
            // Percepat filter periode + agregasi bintang.
            $table->index(['place_id', 'review_date']);
            $table->index(['place_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
