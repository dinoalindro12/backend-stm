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
            $table->string('nomor_induk', 16)->unique();
            $table->string('nik', 20)->unique();
            $table->string('no_rek_bri', 20)->unique()->nullable();
            $table->string('nama_lengkap', 100);
            $table->string('email')->nullable();
            $table->enum('posisi', ['jasa','supir','keamanan','cleaning_service','operator']);
            $table->string('no_wa')->nullable();
            $table->text('alamat');
            $table->string('image')->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_keluar')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
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
