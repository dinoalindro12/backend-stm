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
            $table->unsignedBigInteger('karyawan_id')->nullable();
            $table->foreign('karyawan_id')
                ->references('id')
                ->on('karyawans')
                ->onDelete('set null')
                ->onUpdate('cascade');
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
            $table->date('tanggal_cetak')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->softDeletes();            
            $table->timestamps();
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
