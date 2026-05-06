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
        Schema::create('rekruitmen', function (Blueprint $table) {
            $table->id();
            $table->string('nik');
            $table->string('nama');
            $table->string('nama_lengkap');
            $table->string('posisi_dilamar');
            $table->string('no_wa');
            $table->string('alamat')->nullable();
            $table->string('foto_ktp');
            $table->string('foto_kk');
            $table->string('foto_skck');
            $table->string('pas_foto');
            $table->string('surat_sehat');
            $table->string('surat_anti_narkoba');
            $table->string('surat_lamaran');
            $table->string('cv');
            $table->string('token_pendaftaran')->unique();
            $table->enum('status_terima', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            // unique per lowongan ditambahkan di migrasi kedua setelah kolom lowongan_kerja_id ada
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rekruitmen');
    }
};