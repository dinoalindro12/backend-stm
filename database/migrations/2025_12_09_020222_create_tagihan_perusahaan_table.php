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
        Schema::create('tagihan_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id');
            $table->unsignedBigInteger('potongan_bpjs_id');
            $table->unsignedBigInteger('thrdll_id');
            $table->decimal('jumlah_kehadiran', 15, 2);
            $table->decimal('gaji_harian', 15, 2);
            $table->decimal('upah_diterima', 15, 2);
            $table->decimal('total_tagihan', 15, 2);
            $table->date('tanggal_tagihan');
            $table->foreign('karyawan_id')->references('id')->on('karyawans')->onDelete('cascade');
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
        Schema::dropIfExists('tagihan_perusahaan');
    }
};
