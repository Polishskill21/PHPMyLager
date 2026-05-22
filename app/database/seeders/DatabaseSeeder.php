<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $users = [
            ['name' => 'Admin User',   'email' => 'admin@example.com',  'role' => 'admin'],
            ['name' => 'Writer User',  'email' => 'writer@example.com', 'role' => 'writer'],
            ['name' => 'Viewer User',  'email' => 'viewer@example.com', 'role' => 'viewer'],
        ];

        foreach ($users as $userData) {
            if (!\App\Models\User::where('email', $userData['email'])->exists()) {
                \App\Models\User::factory()->create($userData);
            }
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
            ['pArtikelNr' => 10004, 'bezeichnung' => 'Handlupe 90mm', 'fWgNr' => 4, 'ekPreis' => 10.00, 'vkPreis' => 18.00, 'bestand' => 300, 'meldeBest' => 100, 'lagerplatz' => 'D01-12A'],
            ['pArtikelNr' => 10005, 'bezeichnung' => 'Lupe 90mm', 'fWgNr' => 4, 'ekPreis' => 5.00, 'vkPreis' => 9.00, 'bestand' => 1010, 'meldeBest' => 400, 'lagerplatz' => 'D01-12B'],
            ['pArtikelNr' => 10028, 'bezeichnung' => 'Pruefschraubendreher-Set', 'fWgNr' => 2, 'ekPreis' => 13.00, 'vkPreis' => 25.00, 'bestand' => 680, 'meldeBest' => 210, 'lagerplatz' => 'B04-02C'],
            ['pArtikelNr' => 10030, 'bezeichnung' => 'Schraubendreher 1.5mm', 'fWgNr' => 2, 'ekPreis' => 1.00, 'vkPreis' => 2.00, 'bestand' => 290, 'meldeBest' => 100, 'lagerplatz' => 'B01-01A'],
            ['pArtikelNr' => 10031, 'bezeichnung' => 'Schraubendreher 1.8mm', 'fWgNr' => 2, 'ekPreis' => 1.00, 'vkPreis' => 2.00, 'bestand' => 220, 'meldeBest' => 100, 'lagerplatz' => 'B01-01B'],
            ['pArtikelNr' => 10034, 'bezeichnung' => 'Schraubendreher 3.0mm', 'fWgNr' => 2, 'ekPreis' => 1.00, 'vkPreis' => 2.00, 'bestand' => 300, 'meldeBest' => 100, 'lagerplatz' => 'B01-02A'],
            ['pArtikelNr' => 10044, 'bezeichnung' => 'Stahllaubsaege', 'fWgNr' => 3, 'ekPreis' => 5.00, 'vkPreis' => 10.00, 'bestand' => 1250, 'meldeBest' => 300, 'lagerplatz' => 'C02-05A'],
            ['pArtikelNr' => 10049, 'bezeichnung' => 'Laubsaegeblaetter (12er Set)', 'fWgNr' => 3, 'ekPreis' => 2.00, 'vkPreis' => 4.00, 'bestand' => 2400, 'meldeBest' => 400, 'lagerplatz' => 'C02-05E'],
            ['pArtikelNr' => 10050, 'bezeichnung' => 'Universal-Hobbysaege', 'fWgNr' => 3, 'ekPreis' => 6.00, 'vkPreis' => 11.00, 'bestand' => 1350, 'meldeBest' => 200, 'lagerplatz' => 'C03-01B'],
            ['pArtikelNr' => 10056, 'bezeichnung' => 'Isolier-Abstreifzaengleinchen', 'fWgNr' => 1, 'ekPreis' => 14.00, 'vkPreis' => 20.00, 'bestand' => 2400, 'meldeBest' => 250, 'lagerplatz' => 'A01-03B'],
            ['pArtikelNr' => 10057, 'bezeichnung' => 'Adernendhuelsen-Zaengle', 'fWgNr' => 1, 'ekPreis' => 17.00, 'vkPreis' => 31.00, 'bestand' => 1750, 'meldeBest' => 220, 'lagerplatz' => 'A01-04C'],
            ['pArtikelNr' => 10058, 'bezeichnung' => 'Universal-Kabelzange', 'fWgNr' => 1, 'ekPreis' => 6.00, 'vkPreis' => 12.00, 'bestand' => 1900, 'meldeBest' => 300, 'lagerplatz' => 'A02-01A'],
            ['pArtikelNr' => 10059, 'bezeichnung' => 'Schraubendreher-Set', 'fWgNr' => 2, 'ekPreis' => 11.00, 'vkPreis' => 21.00, 'bestand' => 1800, 'meldeBest' => 180, 'lagerplatz' => 'B05-01D'],
            ['pArtikelNr' => 10062, 'bezeichnung' => 'Pozidriv-Schraubendreher', 'fWgNr' => 2, 'ekPreis' => 3.00, 'vkPreis' => 5.00, 'bestand' => 2850, 'meldeBest' => 200, 'lagerplatz' => 'B02-03C'],
            ['pArtikelNr' => 10068, 'bezeichnung' => 'Elektronik-Seitenschneider', 'fWgNr' => 1, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 750, 'meldeBest' => 150, 'lagerplatz' => 'A03-02B'],
            ['pArtikelNr' => 10069, 'bezeichnung' => 'Elektronik-Flachzange', 'fWgNr' => 1, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 2800, 'meldeBest' => 250, 'lagerplatz' => 'A03-02C'],
            ['pArtikelNr' => 10070, 'bezeichnung' => 'Elektronik-Halbrundzange', 'fWgNr' => 1, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 1950, 'meldeBest' => 400, 'lagerplatz' => 'A03-02D'],
            ['pArtikelNr' => 10071, 'bezeichnung' => 'Loch- und Oesenzange', 'fWgNr' => 1, 'ekPreis' => 13.00, 'vkPreis' => 25.00, 'bestand' => 2540, 'meldeBest' => 350, 'lagerplatz' => 'A04-05E'],
            ['pArtikelNr' => 10075, 'bezeichnung' => 'Edelstahl-Flachzange', 'fWgNr' => 1, 'ekPreis' => 7.00, 'vkPreis' => 12.00, 'bestand' => 150, 'meldeBest' => 300, 'lagerplatz' => 'A05-01A'],
            ['pArtikelNr' => 10076, 'bezeichnung' => 'Automatik-Abisolierzange', 'fWgNr' => 1, 'ekPreis' => 5.00, 'vkPreis' => 9.00, 'bestand' => 100, 'meldeBest' => 250, 'lagerplatz' => 'A05-02B'],
            ['pArtikelNr' => 10080, 'bezeichnung' => 'Telefonzange 200mm', 'fWgNr' => 1, 'ekPreis' => 6.00, 'vkPreis' => 11.00, 'bestand' => 1950, 'meldeBest' => 200, 'lagerplatz' => 'A02-04C'],
            ['pArtikelNr' => 10081, 'bezeichnung' => 'Mehrzweckzange', 'fWgNr' => 1, 'ekPreis' => 19.00, 'vkPreis' => 35.00, 'bestand' => 4500, 'meldeBest' => 200, 'lagerplatz' => 'A02-05A'],
            ['pArtikelNr' => 10086, 'bezeichnung' => 'Multifunktions-Crimpzange', 'fWgNr' => 1, 'ekPreis' => 40.00, 'vkPreis' => 75.00, 'bestand' => 1150, 'meldeBest' => 150, 'lagerplatz' => 'A06-01B'],
            ['pArtikelNr' => 11058, 'bezeichnung' => 'Spezial-Bauschubkarre', 'fWgNr' => 4, 'ekPreis' => 60.00, 'vkPreis' => 114.00, 'bestand' => 450, 'meldeBest' => 250, 'lagerplatz' => 'E01-01A'],
            ['pArtikelNr' => 11062, 'bezeichnung' => 'Durchwurfsieb verzinkt 100x60cm', 'fWgNr' => 4, 'ekPreis' => 40.00, 'vkPreis' => 76.00, 'bestand' => 550, 'meldeBest' => 250, 'lagerplatz' => 'E02-01A'],
            ['pArtikelNr' => 12345, 'bezeichnung' => 'Zange', 'fWgNr' => 1, 'ekPreis' => 12.00, 'vkPreis' => 20.00, 'bestand' => 100, 'meldeBest' => 50,  'lagerplatz' => 'A01-01A'],
            ['pArtikelNr' => 70001, 'bezeichnung' => 'Werkzeugkasten Universal', 'fWgNr' => 4, 'ekPreis' => 149.00, 'vkPreis' => 283.00, 'bestand' => 120, 'meldeBest' => 50, 'lagerplatz' => 'D05-04A'],
            ['pArtikelNr' => 71001, 'bezeichnung' => 'Schlagbohrmaschine', 'fWgNr' => 4, 'ekPreis' => 63.00, 'vkPreis' => 120.00, 'bestand' => 155, 'meldeBest' => 50, 'lagerplatz' => 'D06-01B'],
            ['pArtikelNr' => 71002, 'bezeichnung' => 'Bohrerset fuer Holz/Metall/Stein', 'fWgNr' => 4, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 135, 'meldeBest' => 50, 'lagerplatz' => 'D06-02A'],
            ['pArtikelNr' => 71003, 'bezeichnung' => 'Bit-Steckschluesselsatz', 'fWgNr' => 4, 'ekPreis' => 3.00, 'vkPreis' => 6.00, 'bestand' => 124, 'meldeBest' => 50, 'lagerplatz' => 'D06-02B'],
            ['pArtikelNr' => 71004, 'bezeichnung' => 'Schlosserhammer', 'fWgNr' => 4, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 90, 'meldeBest' => 80, 'lagerplatz' => 'D03-01C'],
            ['pArtikelNr' => 72102, 'bezeichnung' => 'Wasserpumpenzange 240mm', 'fWgNr' => 1, 'ekPreis' => 4.00, 'vkPreis' => 7.00, 'bestand' => 122, 'meldeBest' => 20, 'lagerplatz' => 'A02-02B'],
            ['pArtikelNr' => 72250, 'bezeichnung' => 'Wasserwaage 400mm', 'fWgNr' => 4, 'ekPreis' => 4.00, 'vkPreis' => 8.00, 'bestand' => 90, 'meldeBest' => 40, 'lagerplatz' => 'D02-01A'],
            ['pArtikelNr' => 72255, 'bezeichnung' => 'Universalsaege', 'fWgNr' => 3, 'ekPreis' => 5.00, 'vkPreis' => 10.00, 'bestand' => 95, 'meldeBest' => 40, 'lagerplatz' => 'C01-01A'],
            ['pArtikelNr' => 72256, 'bezeichnung' => 'Saegeblatt Holz', 'fWgNr' => 3, 'ekPreis' => 1.00, 'vkPreis' => 1.00, 'bestand' => 124, 'meldeBest' => 40, 'lagerplatz' => 'C01-02A'],
            ['pArtikelNr' => 72257, 'bezeichnung' => 'Saegeblatt Metall', 'fWgNr' => 3, 'ekPreis' => 2.00, 'vkPreis' => 3.00, 'bestand' => 132, 'meldeBest' => 40, 'lagerplatz' => 'C01-02B'],
            ['pArtikelNr' => 74001, 'bezeichnung' => 'Kasten 75x45', 'fWgNr' => 4, 'ekPreis' => 7.00, 'vkPreis' => 13.00, 'bestand' => 105, 'meldeBest' => 40, 'lagerplatz' => 'D04-01A'],
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
            ['pAufPosNr' => 1, 'fAufNr' => 22334, 'fArtikelNr' => 10004, 'aufMenge' => 20, 'kaufPreis' => 18.00],
            ['pAufPosNr' => 2, 'fAufNr' => 22334, 'fArtikelNr' => 10030, 'aufMenge' => 3,  'kaufPreis' =>  2.00],
            ['pAufPosNr' => 3, 'fAufNr' => 22335, 'fArtikelNr' => 10005, 'aufMenge' => 15, 'kaufPreis' =>  9.00],
            ['pAufPosNr' => 4, 'fAufNr' => 22335, 'fArtikelNr' => 10056, 'aufMenge' => 10, 'kaufPreis' => 20.00],
            ['pAufPosNr' => 5, 'fAufNr' => 22335, 'fArtikelNr' => 10059, 'aufMenge' => 35, 'kaufPreis' => 21.00],
            ['pAufPosNr' => 6, 'fAufNr' => 22336, 'fArtikelNr' => 10004, 'aufMenge' => 40, 'kaufPreis' => 18.00],
            ['pAufPosNr' => 7, 'fAufNr' => 22337, 'fArtikelNr' => 10069, 'aufMenge' => 5,  'kaufPreis' =>  7.00],
            ['pAufPosNr' => 8, 'fAufNr' => 22337, 'fArtikelNr' => 10070, 'aufMenge' => 5,  'kaufPreis' =>  7.00],
        ]);

        // 7. Seed Lieferanten
        DB::table('lieferanten')->insertOrIgnore([
            ['pLiefNr' => 5001, 'name' => 'Remscheid Werkzeuge GmbH', 'strasse' => 'Industriepark Nord 4', 'plz' => 42853, 'ort' => 'Remscheid', 'email' => 'vertrieb@remscheid-tools.de', 'telefon' => '+49 2191 555120', 'created_at' => '2026-01-15 08:00:00', 'updated_at' => '2026-01-15 08:00:00'],
            ['pLiefNr' => 5002, 'name' => 'Sheffield Steel Co.', 'strasse' => '22 Ironworks Lane', 'plz' => 54321, 'ort' => 'Sheffield', 'email' => 'orders@sheffieldsteel.co.uk', 'telefon' => '+44 114 9620000', 'created_at' => '2026-02-10 09:30:00', 'updated_at' => '2026-02-10 09:30:00'],
            ['pLiefNr' => 5003, 'name' => 'Alpen Werkzeuge Import S.A.', 'strasse' => 'Rue du Commerce 77', 'plz' => 1005, 'ort' => 'Lausanne', 'email' => 'info@alpenimport.ch', 'telefon' => '+41 21 3456789', 'created_at' => '2026-04-01 14:15:00', 'updated_at' => '2026-04-01 14:15:00']
        ]);

        // 8. Seed Bestellkoepfe (Purchase Orders placed to Suppliers)
        DB::table('bestellkoepfe')->insertOrIgnore([
            ['pBestNr' => 80001, 'fLiefNr' => 5001, 'bestDat' => '2026-05-01 10:00:00', 'erwLieferDat' => '2026-05-08 14:00:00', 'status' => 'geliefert', 'created_at' => '2026-05-01 10:00:00', 'updated_at' => '2026-05-08 14:10:00'],
            ['pBestNr' => 80002, 'fLiefNr' => 5002, 'bestDat' => '2026-05-15 11:30:00', 'erwLieferDat' => '2026-05-22 12:00:00', 'status' => 'bestellt', 'created_at' => '2026-05-15 11:30:00', 'updated_at' => '2026-05-22 09:00:00'],
            ['pBestNr' => 80003, 'fLiefNr' => 5003, 'bestDat' => '2026-05-21 16:45:00', 'erwLieferDat' => '2026-05-28 16:00:00', 'status' => 'offen', 'created_at' => '2026-05-21 16:45:00', 'updated_at' => '2026-05-21 16:45:00']
        ]);


        // 9. Seed Bestellpositionen (Purchase Order Items mapping quantities)
        DB::table('bestellpositionen')->insertOrIgnore([
            ['pBestPosNr' => 101, 'fBestNr' => 80001, 'fArtikelNr' => 10059, 'bestMenge' => 100, 'gelieferteMenge' => 100, 'ekPreis' => 11.00], 
            ['pBestPosNr' => 102, 'fBestNr' => 80001, 'fArtikelNr' => 10068, 'bestMenge' => 50, 'gelieferteMenge' => 50, 'ekPreis' => 4.00],
            ['pBestPosNr' => 103, 'fBestNr' => 80002, 'fArtikelNr' => 10044, 'bestMenge' => 200, 'gelieferteMenge' => 150, 'ekPreis' => 4.80],
            ['pBestPosNr' => 104, 'fBestNr' => 80002, 'fArtikelNr' => 10049, 'bestMenge' => 500, 'gelieferteMenge' => 500, 'ekPreis' => 1.85],
            ['pBestPosNr' => 105, 'fBestNr' => 80003, 'fArtikelNr' => 10086, 'bestMenge' => 30, 'gelieferteMenge' => 0, 'ekPreis' => 38.50],
            ['pBestPosNr' => 106, 'fBestNr' => 80003, 'fArtikelNr' => 71001, 'bestMenge' => 15, 'gelieferteMenge' => 0, 'ekPreis' => 60.00]
        ]);
    }
}
