<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if (!\App\Models\User::where('email', 'test@example.com')->exists()) {
            \App\Models\User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // DB::table('users')->insert([
        //     ['id' => 1, 'username' => 'root', 'password' => Hash::make('superLager123'), 'role' => 'admin'],
        //     ['id' => 2, 'username' => 'user_viewer', 'password' => Hash::make('lager1'), 'role' => 'viewer'],
        //     ['id' => 3, 'username' => 'user_editor', 'password' => Hash::make('lager1'), 'role' => 'editor'],
        // ]);

        // 2. Seed Warengruppe
        DB::table('warengruppe')->insertOrIgnore([
            ['pWgNr' => 1, 'warengruppe' => 'Zangen'],
            ['pWgNr' => 2, 'warengruppe' => 'Schraubendreher'],
            ['pWgNr' => 3, 'warengruppe' => 'Saegen'],
            ['pWgNr' => 4, 'warengruppe' => 'Sonstige Artikel'],
        ]);

        // 3. Seed Artikel
        DB::table('artikel')->insertOrIgnore([
            ['pArtikelNr' => 10004, 'bezeichnung' => 'Handlupe 90mm', 'fWgNr' => 4, 'ekPreis' => 10.00, 'vkPreis' => 18.00, 'bestand' => 300, 'meldeBest' => 100],
            ['pArtikelNr' => 10005, 'bezeichnung' => 'Lupe 90mm', 'fWgNr' => 4, 'ekPreis' => 5.00, 'vkPreis' => 9.00, 'bestand' => 1010, 'meldeBest' => 400],
            ['pArtikelNr' => 10028, 'bezeichnung' => 'Pruefschraubendreher-Set', 'fWgNr' => 2, 'ekPreis' => 13.00, 'vkPreis' => 25.00, 'bestand' => 680, 'meldeBest' => 210],
            ['pArtikelNr' => 10030, 'bezeichnung' => 'Schraubendreher 1.5mm', 'fWgNr' => 2, 'ekPreis' => 1.00, 'vkPreis' => 2.00, 'bestand' => 290, 'meldeBest' => 100],
            ['pArtikelNr' => 10031, 'bezeichnung' => 'Schraubendreher 1.8mm', 'fWgNr' => 2, 'ekPreis' => 1.00, 'vkPreis' => 2.00, 'bestand' => 220, 'meldeBest' => 100],
            ['pArtikelNr' => 10034, 'bezeichnung' => 'Schraubendreher 3.0mm', 'fWgNr' => 2, 'ekPreis' => 1.00, 'vkPreis' => 2.00, 'bestand' => 300, 'meldeBest' => 100],
            ['pArtikelNr' => 10044, 'bezeichnung' => 'Stahllaubsaege', 'fWgNr' => 3, 'ekPreis' => 5.00, 'vkPreis' => 10.00, 'bestand' => 1250, 'meldeBest' => 300],
            ['pArtikelNr' => 10049, 'bezeichnung' => 'Laubsaegeblaetter (12er Set)', 'fWgNr' => 3, 'ekPreis' => 2.00, 'vkPreis' => 4.00, 'bestand' => 2400, 'meldeBest' => 400],
            ['pArtikelNr' => 10050, 'bezeichnung' => 'Universal-Hobbysaege', 'fWgNr' => 3, 'ekPreis' => 6.00, 'vkPreis' => 11.00, 'bestand' => 1350, 'meldeBest' => 200],
            ['pArtikelNr' => 10056, 'bezeichnung' => 'Isolier-Abstreifzaengleinchen', 'fWgNr' => 1, 'ekPreis' => 14.00, 'vkPreis' => 20.00, 'bestand' => 2400, 'meldeBest' => 250],
            ['pArtikelNr' => 10057, 'bezeichnung' => 'Adernendhuelsen-Zaengle', 'fWgNr' => 1, 'ekPreis' => 17.00, 'vkPreis' => 31.00, 'bestand' => 1750, 'meldeBest' => 220],
            ['pArtikelNr' => 10058, 'bezeichnung' => 'Universal-Kabelzange', 'fWgNr' => 1, 'ekPreis' => 6.00, 'vkPreis' => 12.00, 'bestand' => 1900, 'meldeBest' => 300],
            ['pArtikelNr' => 10059, 'bezeichnung' => 'Schraubendreher-Set', 'fWgNr' => 2, 'ekPreis' => 11.00, 'vkPreis' => 21.00, 'bestand' => 1800, 'meldeBest' => 180],
            ['pArtikelNr' => 10062, 'bezeichnung' => 'Pozidriv-Schraubendreher', 'fWgNr' => 2, 'ekPreis' => 3.00, 'vkPreis' => 5.00, 'bestand' => 2850, 'meldeBest' => 200],
            ['pArtikelNr' => 10068, 'bezeichnung' => 'Elektronik-Seitenschneider', 'fWgNr' => 1, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 750, 'meldeBest' => 150],
            ['pArtikelNr' => 10069, 'bezeichnung' => 'Elektronik-Flachzange', 'fWgNr' => 1, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 2800, 'meldeBest' => 250],
            ['pArtikelNr' => 10070, 'bezeichnung' => 'Elektronik-Halbrundzange', 'fWgNr' => 1, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 1950, 'meldeBest' => 400],
            ['pArtikelNr' => 10071, 'bezeichnung' => 'Loch- und Oesenzange', 'fWgNr' => 1, 'ekPreis' => 13.00, 'vkPreis' => 25.00, 'bestand' => 2540, 'meldeBest' => 350],
            ['pArtikelNr' => 10075, 'bezeichnung' => 'Edelstahl-Flachzange', 'fWgNr' => 1, 'ekPreis' => 7.00, 'vkPreis' => 12.00, 'bestand' => 150, 'meldeBest' => 300],
            ['pArtikelNr' => 10076, 'bezeichnung' => 'Automatik-Abisolierzange', 'fWgNr' => 1, 'ekPreis' => 5.00, 'vkPreis' => 9.00, 'bestand' => 100, 'meldeBest' => 250],
            ['pArtikelNr' => 10080, 'bezeichnung' => 'Telefonzange 200mm', 'fWgNr' => 1, 'ekPreis' => 6.00, 'vkPreis' => 11.00, 'bestand' => 1950, 'meldeBest' => 200],
            ['pArtikelNr' => 10081, 'bezeichnung' => 'Mehrzweckzange', 'fWgNr' => 1, 'ekPreis' => 19.00, 'vkPreis' => 35.00, 'bestand' => 4500, 'meldeBest' => 200],
            ['pArtikelNr' => 10086, 'bezeichnung' => 'Multifunktions-Crimpzange', 'fWgNr' => 1, 'ekPreis' => 40.00, 'vkPreis' => 75.00, 'bestand' => 1150, 'meldeBest' => 150],
            ['pArtikelNr' => 11058, 'bezeichnung' => 'Spezial-Bauschubkarre', 'fWgNr' => 4, 'ekPreis' => 60.00, 'vkPreis' => 114.00, 'bestand' => 450, 'meldeBest' => 250],
            ['pArtikelNr' => 11062, 'bezeichnung' => 'Durchwurfsieb verzinkt 100x60cm', 'fWgNr' => 4, 'ekPreis' => 40.00, 'vkPreis' => 76.00, 'bestand' => 550, 'meldeBest' => 250],
            ['pArtikelNr' => 12345, 'bezeichnung' => 'Zange', 'fWgNr' => 1, 'ekPreis' => 12.00, 'vkPreis' => 20.00, 'bestand' => 100, 'meldeBest' => 50],
            ['pArtikelNr' => 70001, 'bezeichnung' => 'Werkzeugkasten Universal', 'fWgNr' => 4, 'ekPreis' => 149.00, 'vkPreis' => 283.00, 'bestand' => 120, 'meldeBest' => 50],
            ['pArtikelNr' => 71001, 'bezeichnung' => 'Schlagbohrmaschine', 'fWgNr' => 4, 'ekPreis' => 63.00, 'vkPreis' => 120.00, 'bestand' => 155, 'meldeBest' => 50],
            ['pArtikelNr' => 71002, 'bezeichnung' => 'Bohrerset fuer Holz/Metall/Stein', 'fWgNr' => 4, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 135, 'meldeBest' => 50],
            ['pArtikelNr' => 71003, 'bezeichnung' => 'Bit-Steckschluesselsatz', 'fWgNr' => 4, 'ekPreis' => 3.00, 'vkPreis' => 6.00, 'bestand' => 124, 'meldeBest' => 50],
            ['pArtikelNr' => 71004, 'bezeichnung' => 'Schlosserhammer', 'fWgNr' => 4, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 90, 'meldeBest' => 80],
            ['pArtikelNr' => 72102, 'bezeichnung' => 'Wasserpumpenzange 240mm', 'fWgNr' => 1, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 122, 'meldeBest' => 20],
            ['pArtikelNr' => 72250, 'bezeichnung' => 'Wasserwaage 400mm', 'fWgNr' => 4, 'ekPreis' => 4.00, 'vkPreis' => 8.00, 'bestand' => 90, 'meldeBest' => 40],
            ['pArtikelNr' => 72255, 'bezeichnung' => 'Universalsaege', 'fWgNr' => 3, 'ekPreis' => 5.00, 'vkPreis' => 10.00, 'bestand' => 95, 'meldeBest' => 40],
            ['pArtikelNr' => 72256, 'bezeichnung' => 'Saegeblatt Holz', 'fWgNr' => 3, 'ekPreis' => 1.00, 'vkPreis' => 1.00, 'bestand' => 124, 'meldeBest' => 40],
            ['pArtikelNr' => 72257, 'bezeichnung' => 'Saegeblatt Metall', 'fWgNr' => 3, 'ekPreis' => 2.00, 'vkPreis' => 3.00, 'bestand' => 132, 'meldeBest' => 40],
            ['pArtikelNr' => 74001, 'bezeichnung' => 'Kasten 75x45', 'fWgNr' => 4, 'ekPreis' => 7.00, 'vkPreis' => 13.00, 'bestand' => 105, 'meldeBest' => 40],
        ]);

        // 4. Seed Kunden
        DB::table('kunden')->insertOrIgnore([
            ['pKdNr' => 24001, 'name' => 'Baumarkt Mueller', 'strasse' => 'Postfach 134', 'plz' => 85579, 'ort' => 'Neubiberg', 'email' => 'mueller@baumarkt.de'],
            ['pKdNr' => 24002, 'name' => 'Friedrich Kunst', 'strasse' => 'Mausweg 24', 'plz' => 72510, 'ort' => 'Stetten a.k.M.', 'email' => 'friedrich.kunst@mail.de'],
            ['pKdNr' => 24003, 'name' => 'BAU MIT GmbH', 'strasse' => 'Im Grund 11', 'plz' => 86657, 'ort' => 'Bissingen', 'email' => 'info@baumit-gmbh.de'],
            ['pKdNr' => 24004, 'name' => 'Otto Weber', 'strasse' => 'Postfach 888', 'plz' => 78727, 'ort' => 'Oberndorf a.N.', 'email' => 'otto.weber@oberndorf.de'],
            ['pKdNr' => 24005, 'name' => 'Peter Helferich', 'strasse' => 'Stuttgarter Straße 44', 'plz' => 75394, 'ort' => 'Oberreichenbach', 'email' => 'peter.helferich@outlook.com'],
            ['pKdNr' => 24006, 'name' => 'Bau und Ausbau GmbH', 'strasse' => 'Postfach 8573', 'plz' => 71106, 'ort' => 'Magstadt', 'email' => 'info@bauausbau.de'],
            ['pKdNr' => 24007, 'name' => 'Hahn & Widmann', 'strasse' => 'Postfach 2112', 'plz' => 72336, 'ort' => 'Balingen', 'email' => 'kontakt@hahn-widmann.de'],
            ['pKdNr' => 24008, 'name' => 'Otto Huber', 'strasse' => 'Kaiserstraße 33', 'plz' => 78224, 'ort' => 'Singen', 'email' => 'otto.huber@singenmail.de'],
            ['pKdNr' => 24013, 'name' => 'Toom Baumarkt', 'strasse' => 'Im Lehen 20', 'plz' => 78315, 'ort' => 'Radolfzell', 'email' => 'service@toom.de'],
        ]);

        // 5. Seed Auftragskoepfe
        DB::table('auftragskoepfe')->insertOrIgnore([
            ['pAufNr' => 22334, 'aufDat' => '2009-01-26 00:00:00', 'fKdNr' => 24001, 'aufTermin' => '2009-02-18 00:00:00'],
            ['pAufNr' => 22335, 'aufDat' => '2009-01-27 00:00:00', 'fKdNr' => 24004, 'aufTermin' => '2009-02-27 00:00:00'],
            ['pAufNr' => 22336, 'aufDat' => '2009-01-31 00:00:00', 'fKdNr' => 24003, 'aufTermin' => '2009-03-02 00:00:00'],
            ['pAufNr' => 22337, 'aufDat' => '2009-02-12 00:00:00', 'fKdNr' => 24005, 'aufTermin' => '2009-03-11 00:00:00'],
        ]);

        // 6. Seed Auftragspositionen
        DB::table('auftragspositionen')->insertOrIgnore([
            ['pAufPosNr' => 1, 'fAufNr' => 22334, 'fArtikelNr' => 10004, 'aufMenge' => 20],
            ['pAufPosNr' => 2, 'fAufNr' => 22334, 'fArtikelNr' => 10030, 'aufMenge' => 3],
            ['pAufPosNr' => 3, 'fAufNr' => 22335, 'fArtikelNr' => 10005, 'aufMenge' => 15],
            ['pAufPosNr' => 4, 'fAufNr' => 22335, 'fArtikelNr' => 10056, 'aufMenge' => 10],
            ['pAufPosNr' => 5, 'fAufNr' => 22335, 'fArtikelNr' => 10059, 'aufMenge' => 35],
            ['pAufPosNr' => 6, 'fAufNr' => 22336, 'fArtikelNr' => 10004, 'aufMenge' => 40],
            ['pAufPosNr' => 7, 'fAufNr' => 22337, 'fArtikelNr' => 10069, 'aufMenge' => 5],
            ['pAufPosNr' => 8, 'fAufNr' => 22337, 'fArtikelNr' => 10070, 'aufMenge' => 5],
        ]);
    }
}
