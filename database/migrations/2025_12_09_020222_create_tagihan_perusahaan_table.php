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
            
            // Foreign key ke karyawan menggunakan nomor_induk
            $table->string('no_induk', 12);
            $table->foreign('no_induk')
                ->references('nomor_induk')
                ->on('karyawans')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            
            // Data dari karyawan 
            $table->string('nik', 20);
            $table->string('nama', 100);
            $table->string('no_rek_bri', 20)->nullable();
            $table->enum('posisi', ['jasa','supir','keamanan','cleaning_service','operator']);
            
            // Kolom perhitungan gaji - Input Manual
            $table->decimal('jumlah_hari_kerja')->default(0);
            $table->decimal('gaji_harian', 15, 2)->default(0);
            $table->decimal('lembur', 15, 2)->default(0);
            $table->decimal('thr', 15, 2)->default(0);
            
            // Kolom perhitungan gaji - Komponen Potongan/Biaya
            $table->decimal('bpjs_kesehatan', 15, 2)->default(0);
            $table->decimal('jkk', 15, 2)->default(0);
            $table->decimal('jkm', 15, 2)->default(0);
            $table->decimal('jht', 15, 2)->default(0);
            $table->decimal('jp', 15, 2)->default(0);
            $table->decimal('seragam_cs_dan_keamanan', 15, 2)->default(0);
            $table->decimal('fee_manajemen', 15, 2)->default(0);
            
            // Kolom perhitungan gaji - Hasil Kalkulasi (Computed)
            $table->decimal('jumlah_iuran_bpjs', 15, 2)->default(0);
            $table->decimal('upa_pekerja', 15, 2)->default(0);
            $table->decimal('upah_yang_diterima_pekerja', 15, 2)->default(0);
            $table->decimal('total_diterima', 15, 2)->default(0);
            
            // Periode tagihan
            $table->date('periode_awal');
            $table->date('periode_akhir');
            $table->date('tanggal_cetak')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index untuk performa query
            $table->index('no_induk');
            $table->index('periode_awal');
            $table->index('periode_akhir');
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