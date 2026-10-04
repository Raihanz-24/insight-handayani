<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `portal_uuid` = pemetaan identitas SSO (Portal Handayani → user).
     *
     * Opsi B: aplikasi client "pemilik" mapping. Tidak ada auto-create user;
     * UUID harus ditautkan oleh admin (lewat command / pengaturan).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('portal_uuid')->nullable()->unique()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['portal_uuid']);
            $table->dropColumn('portal_uuid');
        });
    }
};
