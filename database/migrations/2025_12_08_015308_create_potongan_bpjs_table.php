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
        Schema::create('potongan_bpjs', function (Blueprint $table) {
            $table->id();
            $table->decimal('bpjs_kesehatan', 15, 2);
            $table->decimal('bpjs_jkk', 15, 2);
            $table->decimal('bpjs_jkm', 15, 2);
            $table->decimal('bpjs_jht', 15, 2);
            $table->decimal('bpjs_jp', 15, 2);
            $table->decimal('seragam_keamanan', 15, 2);
            $table->decimal('fee_manajemen', 15, 2);
            $table->decimal('total_potongan', 15, 2);
            $table->boolean('status_potongan');  
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('potongan_bpjs');
    }
};
