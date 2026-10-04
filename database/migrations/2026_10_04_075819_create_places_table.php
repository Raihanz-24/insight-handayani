<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            // Jenis lokasi: restoran / cottage (dipakai untuk pengelompokan & ikon).
            $table->string('type')->default('restaurant');

            // Penanda lokasi di Google Maps untuk SerpApi.
            // Salah satu dari data_id / place_id wajib ada agar bisa diambil otomatis.
            $table->string('serpapi_data_id')->nullable();
            $table->string('serpapi_place_id')->nullable();
            // Teks pencarian cadangan (bila data_id belum ada).
            $table->string('query')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
