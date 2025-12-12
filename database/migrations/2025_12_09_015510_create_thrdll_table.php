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
        Schema::create('thrdll', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('karyawan_id');
            $table->decimal('jumlah_thr', 15, 2)->nullable();
            $table->decimal('jumlah_jam_lembur', 3)->nullable();
            $table->decimal('lembur_dll', 15, 2)->nullable();
            $table->date('tanggal_pembayaran')->nullable();
            $table->foreign('karyawan_id')->references('id')->on('karyawans')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thrdll');
    }
};
