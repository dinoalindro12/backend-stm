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
            $table->string('nik')->unique();
            $table->string('nama')->required();
            $table->string('nama_lengkap')->required();
            $table->string('posisi_dilamar')->required();
            $table->string('no_wa')->required();
            $table->string('alamat')->nullable();
            $table->string('foto_ktp')->required();
            $table->string('foto_kk')->required();
            $table->string('foto_skck')->required();
            $table->string('pas_foto')->required();
            $table->string('surat_sehat')->required();
            $table->string('surat_anti_narkoba')->required();
            $table->string('surat_lamaran')->required();
            $table->string('cv')->required(); 
            $table->string('token_pendaftaran')->unique();
            $table->enum('status_terima', ['pending', 'diterima', 'ditolak'])->default('pending');
            $table->text('catatan')->nullable();
            $table->timestamps();
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