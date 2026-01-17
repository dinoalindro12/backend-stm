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
        Schema::create('lowongan_kerja', function (Blueprint $table) {
            $table->id();
            $table->enum('posisi', ['Cleaning Service', 'Supir', 'Keamanan', 'Operator', 'Jasa']);
            $table->string('lokasi_kerja');
            $table->enum('jenis_kerja', ['Full Time', 'Part Time']);
            $table->text('catatan')->nullable();
            $table->string('range_gaji');
            $table->date('deadline_lowongan');
            $table->enum('status_lowongan', ['aktif', 'tidak_aktif'])->default('aktif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lowongan_kerja');
    }
};