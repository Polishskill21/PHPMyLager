<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * To force run migration: docker exec -it phpmylager_app php artisan migrate:fresh --seed 
    */
    public function up(): void
    {
        Schema::create('warengruppe', function (Blueprint $table) {
            $table->increments('pWgNr');
            $table->string('warengruppe', 50)->nullable();
        });

        Schema::create('artikel', function (Blueprint $table) {
            $table->increments('pArtikelNr');
            $table->string('bezeichnung', 35)->nullable();
            
            $table->unsignedInteger('fWgNr'); 
            
            $table->decimal('ekPreis', 8, 2)->nullable();
            $table->decimal('vkPreis', 8, 2)->nullable();
            $table->integer('bestand')->nullable();
            $table->integer('meldeBest')->nullable();
            
            $table->foreign('fWgNr')->references('pWgNr')->on('warengruppe');
        });

        Schema::create('kunden', function (Blueprint $table) {
            $table->increments('pKdNr');
            $table->string('name', 50)->nullable();
            $table->string('strasse', 50)->nullable();
            $table->integer('plz')->nullable();
            $table->string('ort', 50)->nullable();
            $table->string('email', 50)->nullable();
        });

        Schema::create('auftragskoepfe', function (Blueprint $table) {
            $table->increments('pAufNr');
            $table->dateTime('aufDat')->nullable();
            
            $table->unsignedInteger('fKdNr'); 
            
            $table->dateTime('aufTermin')->nullable();

            $table->foreign('fKdNr')->references('pKdNr')->on('kunden');
        });

        Schema::create('auftragspositionen', function (Blueprint $table) {
            $table->id('pAufPosNr');
            
            $table->unsignedInteger('fAufNr');
            $table->unsignedInteger('fArtikelNr');
            
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
