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
        Schema::create('karyawans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_induk', 12)->unique()->required();
            $table->string('nik', 20)->unique()->required();
            $table->string('no_rek_bri', 20)->unique();
            $table->string('nama_lengkap', 100)->required();
            $table->string('email')->nullable();
            $table->enum('posisi', ['jasa','supir','keamanan','cleaning_service','operator']);
            $table->string('no_wa')->nullable();
            $table->string('alamat')->required();
            $table->string('image')->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('karyawans');

    }
};
