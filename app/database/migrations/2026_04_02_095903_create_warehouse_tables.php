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
        // ─── 1. Product groups ──────────────────────────────────────────────────
        Schema::create('warengruppe', function (Blueprint $table) {
            $table->increments('pWgNr');
            $table->string('warengruppe', 50)->nullable();
        });

        // ─── 2. Products ──────────────────────────────────────────────────
        Schema::create('artikel', function (Blueprint $table) {
            $table->increments('pArtikelNr');
            $table->string('bezeichnung', 35)->nullable();
            
            $table->unsignedInteger('fWgNr'); 
            
            $table->decimal('ekPreis', 8, 2)->nullable();
            $table->decimal('vkPreis', 8, 2)->nullable();
            $table->integer('bestand')->default(0);
            $table->integer('meldeBest')->default(0);

            $table->softDeletes();
            
            $table->foreign('fWgNr')->references('pWgNr')->on('warengruppe');
        });

        // ─── 3. Customers ──────────────────────────────────────────────────
        Schema::create('kunden', function (Blueprint $table) {
            $table->increments('pKdNr');
            $table->string('name', 50)->nullable();
            $table->string('strasse', 50)->nullable();
            $table->char('plz', 5)->nullable();
            $table->string('ort', 50)->nullable();
            $table->string('email', 50)->nullable();

            $table->softDeletes();
        });

        // ─── 4. Customer-order headers ─────────────────────────────────────
        Schema::create('auftragskoepfe', function (Blueprint $table) {
            $table->increments('pAufNr');
            $table->dateTime('aufDat')->nullable();
            
            $table->unsignedInteger('fKdNr'); 
            
            $table->dateTime('aufTermin')->nullable();

            $table->foreign('fKdNr')->references('pKdNr')->on('kunden');
        });

        // ─── 5. Customer-order line items ───────────────────────────────────
        Schema::create('auftragspositionen', function (Blueprint $table) {
            $table->id('pAufPosNr');
            
            $table->unsignedInteger('fAufNr');
            $table->unsignedInteger('fArtikelNr');
            
            $table->integer('aufMenge');
            $table->decimal('kaufPreis', 8, 2)->nullable();

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
