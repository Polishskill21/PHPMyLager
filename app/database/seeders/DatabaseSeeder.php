<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Auth\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        // 1. Seed Users — each role gets its own password from the environment
        //    (SEEDER_ADMIN_PASSWORD / SEEDER_WRITER_PASSWORD / SEEDER_VIEWER_PASSWORD),
        //    falling back to SEEDER_PASSWORD and finally 'password'.
        $users = [
            [User::COL_NAME => 'Admin User',   User::COL_EMAIL => 'admin@example.com',  User::COL_ROLE => 'admin'],
            [User::COL_NAME => 'Writer User',  User::COL_EMAIL => 'writer@example.com', User::COL_ROLE => 'writer'],
            [User::COL_NAME => 'Viewer User',  User::COL_EMAIL => 'viewer@example.com', User::COL_ROLE => 'viewer'],
        ];

        foreach ($users as $userData) {
            if (!User::where(User::COL_EMAIL, $userData[User::COL_EMAIL])->exists()) {
                User::create([
                    User::COL_NAME => $userData[User::COL_NAME],
                    User::COL_EMAIL => $userData[User::COL_EMAIL],
                    User::COL_ROLE => $userData[User::COL_ROLE],
                    User::COL_EMAIL_VERIFIED_AT => now(),
                    User::COL_PASSWORD => $this->rolePassword($userData[User::COL_ROLE]),
                    User::COL_REMEMBER_TOKEN => Str::random(10),
                ]);
            }
        }

        // 2. Seed Warengruppe
        DB::table('warengruppe')->insertOrIgnore([
            ['pWgNr' => 1, 'warengruppe' => 'Zangen'],
            ['pWgNr' => 2, 'warengruppe' => 'Schraubendreher'],
            ['pWgNr' => 3, 'warengruppe' => 'Saegen'],
            ['pWgNr' => 4, 'warengruppe' => 'Sonstige Artikel'],
            ['pWgNr' => 5, 'warengruppe' => 'Elektrowerkzeuge'],
            ['pWgNr' => 6, 'warengruppe' => 'Arbeitsschutz & Sicherheit'],
            ['pWgNr' => 7, 'warengruppe' => 'Messtechnik'],
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
            ['pArtikelNr' => 10076, 'bezeichnung' => 'Automatik-Abisolierzange', 'fWgNr' => 1, 'ekNr' => 5.00, 'vkPreis' => 9.00, 'bestand' => 100, 'meldeBest' => 250, 'lagerplatz' => 'A05-02B'],
            ['pArtikelNr' => 10080, 'bezeichnung' => 'Telefonzange 200mm', 'fWgNr' => 1, 'ekPreis' => 6.00, 'vkPreis' => 11.00, 'bestand' => 1950, 'meldeBest' => 200, 'lagerplatz' => 'A02-04C'],
            ['pArtikelNr' => 10081, 'bezeichnung' => 'Mehrzweckzange', 'fWgNr' => 1, 'ekPreis' => 19.00, 'vkPreis' => 35.00, 'bestand' => 4500, 'meldeBest' => 200, 'lagerplatz' => 'A02-05A'],
            ['pArtikelNr' => 10086, 'bezeichnung' => 'Multifunktions-Crimpzange', 'fWgNr' => 1, 'ekPreis' => 40.00, 'vkPreis' => 75.00, 'bestand' => 1150, 'meldeBest' => 150, 'lagerplatz' => 'A06-01B'],
            ['pArtikelNr' => 11058, 'bezeichnung' => 'Spezial-Bauschubkarre', 'fWgNr' => 4, 'ekPreis' => 60.00, 'vkPreis' => 114.00, 'bestand' => 450, 'meldeBest' => 250, 'lagerplatz' => 'E01-01A'],
            ['pArtikelNr' => 11062, 'bezeichnung' => 'Durchwurfsieb verzinkt 100x60cm', 'fWgNr' => 4, 'ekPreis' => 40.00, 'vkPreis' => 76.00, 'bestand' => 550, 'meldeBest' => 250, 'lagerplatz' => 'E02-01A'],
            ['pArtikelNr' => 12345, 'bezeichnung' => 'Zange', 'fWgNr' => 1, 'ekPreis' => 12.00, 'vkPreis' => 20.00, 'bestand' => 100, 'meldeBest' => 50, 'lagerplatz' => 'A01-01A'],
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

            ['pArtikelNr' => 10110, 'bezeichnung' => 'Kombizange VDE 180mm', 'fWgNr' => 1, 'ekPreis' => 12.50, 'vkPreis' => 22.95, 'bestand' => 450, 'meldeBest' => 100, 'lagerplatz' => 'A01-05A'],
            ['pArtikelNr' => 10112, 'bezeichnung' => 'Kraft-Seitenschneider', 'fWgNr' => 1, 'ekPreis' => 18.20, 'vkPreis' => 34.50, 'bestand' => 320, 'meldeBest' => 80, 'lagerplatz' => 'A03-03A'],
            ['pArtikelNr' => 10115, 'bezeichnung' => 'Gripzange geschmiedet 250mm', 'fWgNr' => 1, 'ekPreis' => 15.00, 'vkPreis' => 29.90, 'bestand' => 180, 'meldeBest' => 60, 'lagerplatz' => 'A04-01C'],
            ['pArtikelNr' => 10118, 'bezeichnung' => 'Monierzange Rabitzzange', 'fWgNr' => 1, 'ekPreis' => 8.50, 'vkPreis' => 14.95, 'bestand' => 600, 'meldeBest' => 150, 'lagerplatz' => 'A02-03B'],
            ['pArtikelNr' => 10122, 'bezeichnung' => 'Sicherungsringzange Innen gerade', 'fWgNr' => 1, 'ekPreis' => 11.10, 'vkPreis' => 19.80, 'bestand' => 210, 'meldeBest' => 50, 'lagerplatz' => 'A05-04D'],

            ['pArtikelNr' => 20010, 'bezeichnung' => 'Schraubendreher Torx T15', 'fWgNr' => 2, 'ekPreis' => 2.40, 'vkPreis' => 4.95, 'bestand' => 850, 'meldeBest' => 200, 'lagerplatz' => 'B02-01A'],
            ['pArtikelNr' => 20012, 'bezeichnung' => 'Schraubendreher Torx T20', 'fWgNr' => 2, 'ekPreis' => 2.50, 'vkPreis' => 5.20, 'bestand' => 920, 'meldeBest' => 200, 'lagerplatz' => 'B02-01B'],
            ['pArtikelNr' => 20015, 'bezeichnung' => 'Schraubendreher Torx T25', 'fWgNr' => 2, 'ekPreis' => 2.70, 'vkPreis' => 5.50, 'bestand' => 1100, 'meldeBest' => 250, 'lagerplatz' => 'B02-01C'],
            ['pArtikelNr' => 20035, 'bezeichnung' => 'Winkelschluesselsatz Inbus 9tlg', 'fWgNr' => 2, 'ekPreis' => 9.50, 'vkPreis' => 18.90, 'bestand' => 430, 'meldeBest' => 100, 'lagerplatz' => 'B04-05E'],
            ['pArtikelNr' => 20040, 'bezeichnung' => 'Drehmomentschraubendreher', 'fWgNr' => 2, 'ekPreis' => 55.00, 'vkPreis' => 99.00, 'bestand' => 65, 'meldeBest' => 20, 'lagerplatz' => 'B06-02B'],

            ['pArtikelNr' => 30015, 'bezeichnung' => 'Metallsaegebogen Profi', 'fWgNr' => 3, 'ekPreis' => 14.00, 'vkPreis' => 27.50, 'bestand' => 280, 'meldeBest' => 70, 'lagerplatz' => 'C01-04C'],
            ['pArtikelNr' => 30022, 'bezeichnung' => 'Japanische Zugsaege Kataba', 'fWgNr' => 3, 'ekPreis' => 22.00, 'vkPreis' => 39.90, 'bestand' => 190, 'meldeBest' => 40, 'lagerplatz' => 'C04-01A'],
            ['pArtikelNr' => 30025, 'bezeichnung' => 'Feinsaege mit Holzruecken', 'fWgNr' => 3, 'ekPreis' => 7.20, 'vkPreis' => 14.50, 'bestand' => 340, 'meldeBest' => 80, 'lagerplatz' => 'C03-03B'],
            ['pArtikelNr' => 30040, 'bezeichnung' => 'Stichsaegeblatt Holz (5er Pack)', 'fWgNr' => 3, 'ekPreis' => 3.10, 'vkPreis' => 6.90, 'bestand' => 1500, 'meldeBest' => 300, 'lagerplatz' => 'C05-02E'],

            ['pArtikelNr' => 50010, 'bezeichnung' => 'Akku-Bohrschrauber 18V', 'fWgNr' => 5, 'ekPreis' => 75.00, 'vkPreis' => 139.00, 'bestand' => 140, 'meldeBest' => 35, 'lagerplatz' => 'F01-01A'],
            ['pArtikelNr' => 50015, 'bezeichnung' => 'Ersatzakku Li-Ion 18V 4.0Ah', 'fWgNr' => 5, 'ekPreis' => 32.00, 'vkPreis' => 59.90, 'bestand' => 95, 'meldeBest' => 25, 'lagerplatz' => 'F01-01E'],
            ['pArtikelNr' => 50020, 'bezeichnung' => 'Winkelschleifer 125mm 840W', 'fWgNr' => 5, 'ekPreis' => 42.00, 'vkPreis' => 79.00, 'bestand' => 110, 'meldeBest' => 30, 'lagerplatz' => 'F02-01B'],
            ['pArtikelNr' => 50025, 'bezeichnung' => 'Trennscheibe Stahl (10er Set)', 'fWgNr' => 5, 'ekPreis' => 6.50, 'vkPreis' => 12.90, 'bestand' => 850, 'meldeBest' => 150, 'lagerplatz' => 'F02-03C'],
            ['pArtikelNr' => 50030, 'bezeichnung' => 'Stichsaege elektronisch 650W', 'fWgNr' => 5, 'ekPreis' => 58.00, 'vkPreis' => 105.00, 'bestand' => 75, 'meldeBest' => 20, 'lagerplatz' => 'F03-01C'],
            ['pArtikelNr' => 50045, 'bezeichnung' => 'Exzenterschleifer 300W', 'fWgNr' => 5, 'ekPreis' => 49.00, 'vkPreis' => 89.95, 'bestand' => 60, 'meldeBest' => 15, 'lagerplatz' => 'F04-02B'],
            ['pArtikelNr' => 50060, 'bezeichnung' => 'Heissluftgeblaese 2000W', 'fWgNr' => 5, 'ekPreis' => 24.00, 'vkPreis' => 45.00, 'bestand' => 130, 'meldeBest' => 40, 'lagerplatz' => 'F05-01D'],
            ['pArtikelNr' => 60010, 'bezeichnung' => 'Schutzbrille klar Antikratz', 'fWgNr' => 6, 'ekPreis' => 3.20, 'vkPreis' => 7.50, 'bestand' => 1200, 'meldeBest' => 200, 'lagerplatz' => 'G01-01A'],
            ['pArtikelNr' => 60015, 'bezeichnung' => 'Feinstaubmaske FFP2 (20er Box)', 'fWgNr' => 6, 'ekPreis' => 12.00, 'vkPreis' => 24.95, 'bestand' => 450, 'meldeBest' => 100, 'lagerplatz' => 'G01-03E'],
            ['pArtikelNr' => 60020, 'bezeichnung' => 'Arbeitshandschuhe PU Gr. 10', 'fWgNr' => 6, 'ekPreis' => 1.10, 'vkPreis' => 2.80, 'bestand' => 3500, 'meldeBest' => 500, 'lagerplatz' => 'G02-01A'],
            ['pArtikelNr' => 60022, 'bezeichnung' => 'Arbeitshandschuhe Leder Gr. 10', 'fWgNr' => 6, 'ekPreis' => 2.90, 'vkPreis' => 6.50, 'bestand' => 1800, 'meldeBest' => 300, 'lagerplatz' => 'G02-02B'],
            ['pArtikelNr' => 60035, 'bezeichnung' => 'Kapselgehoerschutz SNR 32dB', 'fWgNr' => 6, 'ekPreis' => 9.50, 'vkPreis' => 18.00, 'bestand' => 260, 'meldeBest' => 60, 'lagerplatz' => 'G03-01C'],
            ['pArtikelNr' => 60050, 'bezeichnung' => 'Bauhelm gelb PE-Schale', 'fWgNr' => 6, 'ekPreis' => 6.00, 'vkPreis' => 11.90, 'bestand' => 140, 'meldeBest' => 40, 'lagerplatz' => 'G04-01A'],
            ['pArtikelNr' => 60070, 'bezeichnung' => 'Erste-Hilfe-Koffer DIN 13157', 'fWgNr' => 6, 'ekPreis' => 22.50, 'vkPreis' => 39.95, 'bestand' => 85, 'meldeBest' => 20, 'lagerplatz' => 'G05-02B'],

            ['pArtikelNr' => 70010, 'bezeichnung' => 'Rollbandmass 5m Feststeller', 'fWgNr' => 7, 'ekPreis' => 2.80, 'vkPreis' => 5.95, 'bestand' => 1400, 'meldeBest' => 250, 'lagerplatz' => 'H01-01A'],
            ['pArtikelNr' => 70012, 'bezeichnung' => 'Taschenmessschieber 150mm', 'fWgNr' => 7, 'ekPreis' => 18.50, 'vkPreis' => 35.00, 'bestand' => 230, 'meldeBest' => 50, 'lagerplatz' => 'H02-01B'],
            ['pArtikelNr' => 70015, 'bezeichnung' => 'Digital-Multimeter CAT III', 'fWgNr' => 7, 'ekPreis' => 24.00, 'vkPreis' => 45.00, 'bestand' => 160, 'meldeBest' => 40, 'lagerplatz' => 'H03-02C'],
            ['pArtikelNr' => 70020, 'bezeichnung' => 'Infrarot-Thermometer -30/500C', 'fWgNr' => 7, 'ekPreis' => 19.00, 'vkPreis' => 38.50, 'bestand' => 90, 'meldeBest' => 25, 'lagerplatz' => 'H03-04D'],
            ['pArtikelNr' => 70035, 'bezeichnung' => 'Kreuzlinienlaser selbstnivellierend', 'fWgNr' => 7, 'ekPreis' => 65.00, 'vkPreis' => 119.00, 'bestand' => 45, 'meldeBest' => 15, 'lagerplatz' => 'H04-01A'],

            ['pArtikelNr' => 40100, 'bezeichnung' => 'Kabelbinder-Set 300tlg sortiert', 'fWgNr' => 4, 'ekPreis' => 3.50, 'vkPreis' => 7.95, 'bestand' => 2200, 'meldeBest' => 400, 'lagerplatz' => 'D02-05A'],
            ['pArtikelNr' => 40105, 'bezeichnung' => 'Universal-Isolierband schwarz', 'fWgNr' => 4, 'ekPreis' => 0.60, 'vkPreis' => 1.50, 'bestand' => 4800, 'meldeBest' => 800, 'lagerplatz' => 'D02-05E'],
            ['pArtikelNr' => 40120, 'bezeichnung' => 'Schlossschrauben M8x60 (50er)', 'fWgNr' => 4, 'ekPreis' => 5.20, 'vkPreis' => 10.90, 'bestand' => 340, 'meldeBest' => 80, 'lagerplatz' => 'D03-04A'],
            ['pArtikelNr' => 40122, 'bezeichnung' => 'Sechskantmuttern M8 (100er)', 'fWgNr' => 4, 'ekPreis' => 2.10, 'vkPreis' => 4.50, 'bestand' => 510, 'meldeBest' => 100, 'lagerplatz' => 'D03-04B'],
            ['pArtikelNr' => 40140, 'bezeichnung' => 'Drahtstift-Sortiment 500g', 'fWgNr' => 4, 'ekPreis' => 3.00, 'vkPreis' => 6.20, 'bestand' => 420, 'meldeBest' => 90, 'lagerplatz' => 'D04-03C'],
            ['pArtikelNr' => 40160, 'bezeichnung' => 'Fischer Duebel SX 6mm (100er)', 'fWgNr' => 4, 'ekPreis' => 2.40, 'vkPreis' => 5.40, 'bestand' => 1600, 'meldeBest' => 300, 'lagerplatz' => 'D05-01A'],
            ['pArtikelNr' => 40162, 'bezeichnung' => 'Fischer Duebel SX 8mm (100er)', 'fWgNr' => 4, 'ekPreis' => 3.10, 'vkPreis' => 6.80, 'bestand' => 1400, 'meldeBest' => 300, 'lagerplatz' => 'D05-01B'],
            ['pArtikelNr' => 40180, 'bezeichnung' => 'Gewebeband Silber 50m', 'fWgNr' => 4, 'ekPreis' => 4.50, 'vkPreis' => 9.90, 'bestand' => 720, 'meldeBest' => 150, 'lagerplatz' => 'D02-02C'],
            ['pArtikelNr' => 40200, 'bezeichnung' => 'WD-40 Multifunktionsoel 400ml', 'fWgNr' => 4, 'ekPreis' => 3.20, 'vkPreis' => 6.50, 'bestand' => 1100, 'meldeBest' => 200, 'lagerplatz' => 'D06-05D'],
            ['pArtikelNr' => 40210, 'bezeichnung' => 'Bau-Silikon transparent 310ml', 'fWgNr' => 4, 'ekPreis' => 2.80, 'vkPreis' => 5.90, 'bestand' => 640, 'meldeBest' => 120, 'lagerplatz' => 'D06-04A'],
            ['pArtikelNr' => 40230, 'bezeichnung' => 'Schraubzwinge 300x120mm', 'fWgNr' => 4, 'ekPreis' => 8.50, 'vkPreis' => 16.90, 'bestand' => 240, 'meldeBest' => 60, 'lagerplatz' => 'D04-05A'],
            ['pArtikelNr' => 40250, 'bezeichnung' => 'Blindnietzange inkl. Nietensatz', 'fWgNr' => 4, 'ekPreis' => 13.40, 'vkPreis' => 24.95, 'bestand' => 195, 'meldeBest' => 40, 'lagerplatz' => 'D05-02B'],
            ['pArtikelNr' => 40270, 'bezeichnung' => 'Heissklebepistole 80W', 'fWgNr' => 4, 'ekPreis' => 11.00, 'vkPreis' => 19.90, 'bestand' => 310, 'meldeBest' => 70, 'lagerplatz' => 'D06-03C'],
            ['pArtikelNr' => 40272, 'bezeichnung' => 'Heissklebesticks 11mm (1kg)', 'fWgNr' => 4, 'ekPreis' => 7.50, 'vkPreis' => 14.90, 'bestand' => 480, 'meldeBest' => 100, 'lagerplatz' => 'D06-03E'],
            ['pArtikelNr' => 40300, 'bezeichnung' => 'Blechschere rechtsschneidend', 'fWgNr' => 4, 'ekPreis' => 12.00, 'vkPreis' => 22.50, 'bestand' => 140, 'meldeBest' => 35, 'lagerplatz' => 'D01-04A'],
            ['pArtikelNr' => 40320, 'bezeichnung' => 'Cuttermesser 18mm Metallfuehrung', 'fWgNr' => 4, 'ekPreis' => 1.80, 'vkPreis' => 3.95, 'bestand' => 1350, 'meldeBest' => 250, 'lagerplatz' => 'D01-05B'],
            ['pArtikelNr' => 40322, 'bezeichnung' => 'Ersatzklingen 18mm (10er Box)', 'fWgNr' => 4, 'ekPreis' => 0.90, 'vkPreis' => 2.50, 'bestand' => 2900, 'meldeBest' => 400, 'lagerplatz' => 'D01-05E'],
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
            ['pLiefNr' => 5001, 'name' => 'Remscheid Werkzeuge GmbH', 'strasse' => 'Industriepark Nord 4', 'plz' => 42853, 'ort' => 'Remscheid', 'email' => 'vertrieb@remscheid-tools.de'],
            ['pLiefNr' => 5002, 'name' => 'Sheffield Steel Co.', 'strasse' => '22 Ironworks Lane', 'plz' => 54321, 'ort' => 'Sheffield', 'email' => 'orders@sheffieldsteel.co.uk'],
            ['pLiefNr' => 5003, 'name' => 'Alpen Werkzeuge Import S.A.', 'strasse' => 'Rue du Commerce 77', 'plz' => 10050, 'ort' => 'Lausanne', 'email' => 'info@alpenimport.ch']
        ]);

        // 8. Seed Bestellkoepfe (Purchase Orders placed to Suppliers)
        DB::table('bestellkoepfe')->insertOrIgnore([
            ['pBestNr' => 80001, 'fLiefNr' => 5001, 'bestDat' => '2026-05-01 10:00:00', 'erwLieferDat' => '2026-05-08 14:00:00', 'status' => 'geliefert'],
            ['pBestNr' => 80002, 'fLiefNr' => 5002, 'bestDat' => '2026-05-15 11:30:00', 'erwLieferDat' => '2026-05-22 12:00:00', 'status' => 'bestellt'],
            ['pBestNr' => 80003, 'fLiefNr' => 5003, 'bestDat' => '2026-05-21 16:45:00', 'erwLieferDat' => '2026-05-28 16:00:00', 'status' => 'offen']
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

    /**
     * Resolve the hashed seed password for a role.
     *
     * Resolution order: SEEDER_<ROLE>_PASSWORD → SEEDER_PASSWORD → 'password'.
     */
    private function rolePassword(string $role): string
    {
        $candidates = [
            env('SEEDER_' . strtoupper($role) . '_PASSWORD'),
            env('SEEDER_PASSWORD'),
        ];

        $plain = 'password';
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $plain = trim($candidate);
                break;
            }
        }

        return Hash::make($plain);
    }
}
