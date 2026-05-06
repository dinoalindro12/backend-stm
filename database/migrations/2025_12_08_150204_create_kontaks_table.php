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
        Schema::create('kontaks', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email');
            $table->string('no_wa');
            $table->string('perusahaan')->nullable();
            $table->string('subjek');
            $table->text('isi');  // text lebih tepat untuk isi pesan panjang
            $table->enum('status_dibaca', ['pending', 'dibaca'])->default('pending');
            $table->timestamp('dibaca_pada')->nullable(); // kapan pesan dibaca
            $table->unsignedBigInteger('admin_id')->nullable(); // admin yang membaca
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kontaks');
    }
};
