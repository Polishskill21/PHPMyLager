<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('fArtikelNr');
            $table->unsignedBigInteger('user_id')->nullable();
            
            $table->integer('old_bestand');
            $table->integer('new_bestand');
            $table->string('reason', 255);
            $table->timestamps();

            $table->foreign('fArtikelNr')->references('pArtikelNr')->on('artikel')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};