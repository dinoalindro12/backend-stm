<?php
// File: 2025_12_09_020222_create_tagihan_perusahaan_table.php

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
            // Foreign key ke karyawan menggunakan id karyawan sebagai string dengan panjang 12 karakter
            $table->unsignedBigInteger('karyawan_id')->nullable();
            $table->foreign('karyawan_id')
                ->references('id')
                ->on('karyawans')
                ->onDelete('set null')
                ->onUpdate('cascade');
            // Kolom perhitungan gaji - Input Manual
            $table->decimal('jumlah_penghasilan_kotor', 15, 2)->default(0);
            $table->decimal('bpjs_kesehatan', 15, 2)->default(0);
            $table->decimal('jkk', 15, 2)->default(0);
            $table->decimal('jkm', 15, 2)->default(0);
            $table->decimal('jht', 15, 2)->default(0);
            $table->decimal('jp', 15, 2)->default(0);
            $table->decimal('seragam_cs_dan_keamanan', 15, 2)->default(0);
            $table->decimal('fee_manajemen', 15, 2)->default(0);
            $table->decimal('thr', 15, 2)->default(0);
            $table->decimal('jumlah_hari_kerja')->default(0);
            $table->decimal('gaji_harian', 15, 2)->default(0);
            $table->decimal('jlh_lembur', 15, 2)->default(0);
            $table->decimal('upah_diterima_pekerja', 15, 2)->default(0);
            $table->decimal('upah_total', 15, 2)->default(0);
            $table->date('tagihan_bulan');
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
        Schema::dropIfExists('tagihan_perusahaan');
    }
};