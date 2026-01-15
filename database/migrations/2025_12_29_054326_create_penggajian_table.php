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
            // Foreign key ke karyawan menggunakan nomor_induk
            $table->string('no_induk', 12);
            $table->foreign('no_induk')
                ->references('nomor_induk')
                ->on('karyawans')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->string('nik', 20);
            $table->string('nama', 100);
            $table->string('no_rek_bri', 20)->nullable();
            $table->enum('posisi', ['jasa','supir','keamanan','cleaning_service','operator']);
            $table->decimal('jumlah_penghasilan_kotor', 15, 2)->default(0);
            $table->decimal('bpjs_kesehatan', 15, 2)->default(0);
            $table->decimal('bpjs_jht', 15, 2)->default(0);
            $table->decimal('bpjs_jp', 15, 2)->default(0);
            $table->decimal('total_bpjs', 15, 2)->default(0);
            $table->decimal('uang_thr', 15, 2)->nullable()->default(0);
            $table->decimal('jumlah_hari_kerja')->default(0);
            $table->decimal('gaji_harian', 15, 2)->default(0);
            $table->decimal('jumlah_lembur', 15, 2)->default(0);
            $table->decimal('upah_kotor_karyawan', 15, places: 2)->default(0);
            $table->decimal('upah_diterima', 15, 2)->default(0);
            $table->date('gajian_bulan');
            $table->boolean('status_penggajian')->default(false);

            // periode penggajian
            $table->date('periode_awal');
            $table->date('periode_akhir');
            $table->date('tanggal_cetak')->nullable();

            $table->softDeletes();            
            $table->timestamps();
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
        Schema::dropIfExists('penggajian');
    }
};
