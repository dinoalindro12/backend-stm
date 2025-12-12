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
        Schema::create('penggajian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id');
            $table->unsignedBigInteger('tagihan_perusahaan_id');
            $table->unsignedBigInteger('potongan_bpjs_id');
            $table->unsignedBigInteger('thrdll_id');
            $table->decimal('upah_kotor_karyawan', 15, 2);
            $table->decimal('upah_yang_diterima_karyawan', 15, 2);
            $table->date('tanggal_penggajian');
            $table->foreign('karyawan_id')->references('id')->on('karyawans')->onDelete('cascade');
            $table->foreign('tagihan_perusahaan_id')->references('id')->on('tagihan_perusahaan')->onDelete('cascade');
            $table->foreign('potongan_bpjs_id')->references('id')->on('potongan_bpjs')->onDelete('cascade');
            $table->foreign('thrdll_id')->references('id')->on('thrdll')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penggajian');
    }
};
