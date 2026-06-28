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
    { Schema::table('penggajian', function (Blueprint $table) {
            // Composite index untuk filter yang paling sering dipakai
            $table->index(['karyawan_id', 'gajian_bulan']);      // Untuk cek duplikasi & filter
            $table->index(['gajian_bulan', 'status_penggajian']); // Untuk filter bulan + status
            $table->index('status_penggajian');                  // Untuk filter status
            $table->index('tanggal_cetak');                      // Untuk filter sudah/belum cetak
            $table->index('deleted_at');                         // Untuk soft delete
            $table->index('created_at');                         // Untuk sorting
            $table->index('updated_at');                         // Untuk sorting
            
            // Index untuk kolom yang sering di-join
            $table->index('admin_id');
            $table->index('updated_by');
        });
        Schema::table('karyawans', function (Blueprint $table) {
            $table->index(['posisi', 'status_aktif']);           // Untuk filter posisi
            $table->index('deleted_at');
            $table->index('nama_lengkap');
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

        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropIndex(['posisi', 'status_aktif']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['nama_lengkap']);
        });
    }
};