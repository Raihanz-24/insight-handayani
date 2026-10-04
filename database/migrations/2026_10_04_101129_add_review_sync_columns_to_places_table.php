<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            // Total review yang pernah diambil (distinct) untuk memantau kelengkapan.
            $table->unsignedInteger('reviews_synced')->default(0)->after('last_synced_at');
            // Tanggal review tertua yang tersimpan (untuk tahu cakupan histori).
            $table->date('oldest_review_date')->nullable()->after('reviews_synced');
            // Tanggal review terbaru yang tersimpan.
            $table->date('newest_review_date')->nullable()->after('oldest_review_date');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['reviews_synced', 'oldest_review_date', 'newest_review_date']);
        });
    }
};
