<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pencatatan pemakaian "search" SerpApi sebagai guard kuota/biaya.
        Schema::create('serpapi_usage', function (Blueprint $table) {
            $table->id();

            // Tanggal (lokal) pemakaian.
            $table->date('usage_date')->unique();
            // Jumlah search yang sudah dipakai pada tanggal tersebut.
            $table->unsignedInteger('searches')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serpapi_usage');
    }
};
