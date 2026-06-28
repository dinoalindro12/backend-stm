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
        
        Schema::table('tagihan_perusahaan', function (Blueprint $table) {
            // Composite index untuk filter yang paling sering dipakai
            $table->index(['karyawan_id', 'tagihan_bulan']);      // Untuk cek duplikasi & filter
            $table->index('tagihan_bulan');                       // Untuk filter bulan
            $table->index('deleted_at');                          // Untuk soft delete
            $table->index('created_at');                          // Untuk sorting
            $table->index('updated_at');                          // Untuk sorting
            
            // Index untuk kolom yang sering di-join
            $table->index('admin_id');
            $table->index('updated_by');
        });

        // ============================================
        // INDEX UNTUK TABEL KARYAWANS (Tambahan)
        // ============================================
        Schema::table('karyawans', function (Blueprint $table) {
            // Composite index untuk filter posisi + status aktif
            $table->index(['posisi', 'status_aktif']);
            
            // Index untuk soft delete
            $table->index('deleted_at');
            
            // Index untuk pencarian
            $table->index('nama_lengkap');
            $table->index('nomor_induk');
            $table->index('nik');
            
            // Index untuk tanggal
            $table->index('tanggal_masuk');
            $table->index('tanggal_keluar');
            
            // Index untuk kolom yang sering di-join
            $table->index('admin_id');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tagihan_perusahaan', function (Blueprint $table) {
            $table->dropIndex(['karyawan_id', 'tagihan_bulan']);
            $table->dropIndex(['tagihan_bulan']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['updated_by']);
        });

        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropIndex(['posisi', 'status_aktif']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['nama_lengkap']);
            $table->dropIndex(['nomor_induk']);
            $table->dropIndex(['nik']);
            $table->dropIndex(['tanggal_masuk']);
            $table->dropIndex(['tanggal_keluar']);
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['updated_by']);
        });
    }
};