<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('penggajian', function (Blueprint $table) {
            $table->index(['karyawan_id', 'gajian_bulan']);      // Cek duplikasi & filter
            $table->index(['gajian_bulan', 'status_penggajian']); // Filter bulan + status
            $table->index('status_penggajian');                  // Filter status
            $table->index('tanggal_cetak');                      // Filter sudah/belum cetak
            $table->index('deleted_at');                         // Soft delete
            $table->index('created_at');                         // Sorting
            $table->index('updated_at');                         // Sorting
            $table->index('admin_id');                           // Join
            $table->index('updated_by');                         // Join
        });

        
        Schema::table('tagihan_perusahaan', function (Blueprint $table) {
            $table->index(['karyawan_id', 'tagihan_bulan']);     // Cek duplikasi & filter
            $table->index('tagihan_bulan');                      // Filter bulan
            $table->index('deleted_at');                         // Soft delete
            $table->index('created_at');                         // Sorting
            $table->index('updated_at');                         // Sorting
            $table->index('admin_id');                           // Join
            $table->index('updated_by');                         // Join
        });

        
        Schema::table('karyawans', function (Blueprint $table) {
            // Composite index untuk filter
            $table->index(['posisi', 'status_aktif']);
            
            // Soft delete
            $table->index('deleted_at');
            
            // Pencarian
            $table->index('nama_lengkap');
            $table->index('nomor_induk');
            $table->index('nik');
            
            // Filter tanggal
            $table->index('tanggal_masuk');
            $table->index('tanggal_keluar');
            
            // Join
            $table->index('admin_id');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penggajian', function (Blueprint $table) {
            $table->dropIndex(['karyawan_id', 'gajian_bulan']);
            $table->dropIndex(['gajian_bulan', 'status_penggajian']);
            $table->dropIndex(['status_penggajian']);
            $table->dropIndex(['tanggal_cetak']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['admin_id']);
            $table->dropIndex(['updated_by']);
        });
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