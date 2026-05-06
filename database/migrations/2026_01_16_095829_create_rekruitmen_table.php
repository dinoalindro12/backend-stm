<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rekruitmen', function (Blueprint $table) {
            $table->foreignId('lowongan_kerja_id')->nullable()->after('id')->constrained('lowongan_kerja')->onDelete('set null');
            // Satu NIK hanya bisa daftar sekali per lowongan
            $table->unique(['nik', 'lowongan_kerja_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rekruitmen', function (Blueprint $table) {
            $table->dropUnique(['nik', 'lowongan_kerja_id']);
            $table->dropForeign(['lowongan_kerja_id']);
            $table->dropColumn('lowongan_kerja_id');
        });
    }
};
