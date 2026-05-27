<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Physical storage location on every product ────────────────────
        Schema::table('artikel', function (Blueprint $table) {
            $table->string('lagerplatz', 10)->nullable()->after('meldeBest')
                  ->comment('Format: A12-03B  [Zone][Regal]-[Fach][Ebene]');
        });

        // ─── 2. Suppliers ─────────────────────────────────────────────────────
        Schema::create('lieferanten', function (Blueprint $table) {
            $table->increments('pLiefNr');
            $table->string('name', 100);
            $table->string('strasse', 50)->nullable();
            $table->integer('plz')->nullable();
            $table->string('ort', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('telefon', 30)->nullable();

            $table->softDeletes();
        });

        // ─── 3. Purchase-order headers ────────────────────────────────────────
        Schema::create('bestellkoepfe', function (Blueprint $table) {
            $table->increments('pBestNr');

            $table->unsignedInteger('fLiefNr');

            $table->dateTime('bestDat');                        // order date
            $table->dateTime('erwLieferDat')->nullable();       // expected delivery

            /**
             * Status flow:
             *   offen      – created, not yet sent
             *   bestellt   – sent to supplier
             *   geliefert  – goods received → stock will be incremented
             *   storniert  – cancelled
             */
            $table->enum('status', ['offen', 'bestellt', 'geliefert', 'storniert'])
                  ->default('offen');

            $table->foreign('fLiefNr')->references('pLiefNr')->on('lieferanten');
        });

        // ─── 4. Purchase-order line items ─────────────────────────────────────
        Schema::create('bestellpositionen', function (Blueprint $table) {
            $table->id('pBestPosNr');

            $table->unsignedInteger('fBestNr');
            $table->unsignedInteger('fArtikelNr');

            $table->integer('bestMenge');               // quantity ordered
            $table->integer('gelieferteMenge')->default(0); // actually delivered (partial delivery support)
            $table->decimal('ekPreis', 8, 2)->nullable();
            
            $table->foreign('fBestNr')->references('pBestNr')->on('bestellkoepfe');
            $table->foreign('fArtikelNr')->references('pArtikelNr')->on('artikel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bestellpositionen');
        Schema::dropIfExists('bestellkoepfe');
        Schema::dropIfExists('lieferanten');

        Schema::table('artikel', function (Blueprint $table) {
            $table->dropColumn('lagerplatz');
        });
    }
};