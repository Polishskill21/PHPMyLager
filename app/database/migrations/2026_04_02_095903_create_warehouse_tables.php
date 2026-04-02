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
        Schema::create('warengruppe', function (Blueprint $table) {
            $table->integer('pWgNr')->primary();
            $table->string('warengruppe', 50)->nullable();
        });

        Schema::create('artikel', function (Blueprint $table) {
            $table->integer('pArtikelNr')->primary();
            $table->string('bezeichnung', 35)->nullable();
            $table->integer('fWgNr');
            $table->decimal('ekPreis', 8, 2)->nullable();
            $table->decimal('vkPreis', 8, 2)->nullable();
            $table->integer('bestand')->nullable();
            $table->integer('meldeBest')->nullable();
            
            $table->foreign('fWgNr')->references('pWgNr')->on('warengruppe');
        });

        Schema::create('kunden', function (Blueprint $table) {
            $table->integer('pKdNr')->primary();
            $table->string('name', 50)->nullable();
            $table->string('strasse', 50)->nullable();
            $table->integer('plz')->nullable();
            $table->string('ort', 50)->nullable();
            $table->string('email', 50)->nullable();
        });

        Schema::create('auftragskoepfe', function (Blueprint $table) {
            $table->integer('pAufNr')->primary();
            $table->dateTime('aufDat')->nullable();
            $table->integer('fKdNr');
            $table->dateTime('aufTermin')->nullable();

            $table->foreign('fKdNr')->references('pKdNr')->on('kunden');
        });

        Schema::create('auftragspositionen', function (Blueprint $table) {
            $table->id('pAufPosNr');
            $table->integer('fAufNr');
            $table->integer('fArtikelNr');
            $table->integer('aufMenge');

            $table->foreign('fAufNr')->references('pAufNr')->on('auftragskoepfe');
            $table->foreign('fArtikelNr')->references('pArtikelNr')->on('artikel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auftragspositionen');
        Schema::dropIfExists('auftragskoepfe');
        Schema::dropIfExists('kunden');
        Schema::dropIfExists('artikel');
        Schema::dropIfExists('warengruppe');
    }
};
