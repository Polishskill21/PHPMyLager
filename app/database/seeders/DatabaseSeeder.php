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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // DB::table('users')->insert([
        //     ['id' => 1, 'username' => 'root', 'password' => Hash::make('superLager123'), 'role' => 'admin'],
        //     ['id' => 2, 'username' => 'user_viewer', 'password' => Hash::make('lager1'), 'role' => 'viewer'],
        //     ['id' => 3, 'username' => 'user_editor', 'password' => Hash::make('lager1'), 'role' => 'editor'],
        // ]);

        // 2. Seed Warengruppe
        DB::table('warengruppe')->insert([
            ['pWgNr' => 1, 'warengruppe' => 'Zangen'],
            ['pWgNr' => 2, 'warengruppe' => 'Schraubendreher'],
            ['pWgNr' => 3, 'warengruppe' => 'Saegen'],
            ['pWgNr' => 4, 'warengruppe' => 'Sonstige Artikel'],
        ]);

        // 3. Seed Artikel (Just a few examples so you get the idea, you can copy the rest!)
        DB::table('artikel')->insert([
            ['pArtikelNr' => 10004, 'bezeichnung' => 'Handlupe 90mm', 'fWgNr' => 4, 'ekPreis' => 10.00, 'vkPreis' => 18.00, 'bestand' => 300, 'meldeBest' => 100],
            ['pArtikelNr' => 10005, 'bezeichnung' => 'Lupe 90mm', 'fWgNr' => 4, 'ekPreis' => 5.00, 'vkPreis' => 9.00, 'bestand' => 1010, 'meldeBest' => 400],
            ['pArtikelNr' => 10028, 'bezeichnung' => 'Pruefschraubendreher-Set', 'fWgNr' => 2, 'ekPreis' => 13.00, 'vkPreis' => 25.00, 'bestand' => 680, 'meldeBest' => 210],
        ]);

        // 4. Seed Kunden
        DB::table('kunden')->insert([
            ['pKdNr' => 24001, 'name' => 'Baumarkt Mueller', 'strasse' => 'Postfach 134', 'plz' => 85579, 'ort' => 'Neubiberg', 'email' => 'mueller@baumarkt.de'],
            ['pKdNr' => 24002, 'name' => 'Friedrich Kunst', 'strasse' => 'Mausweg 24', 'plz' => 72510, 'ort' => 'Stetten a.k.M.', 'email' => 'friedrich.kunst@mail.de'],
        ]);

        // 5. Seed Auftragskoepfe
        DB::table('auftragskoepfe')->insert([
            ['pAufNr' => 22334, 'aufDat' => '2009-01-26 00:00:00', 'fKdNr' => 24001, 'aufTermin' => '2009-02-18 00:00:00'],
            ['pAufNr' => 22335, 'aufDat' => '2009-01-27 00:00:00', 'fKdNr' => 24001, 'aufTermin' => '2009-02-27 00:00:00'], // Note: KdNr 24004 isn't in our shortened list above, so I mapped it to 24001 for the demo to prevent a foreign key crash!
        ]);

        // 6. Seed Auftragspositionen
        DB::table('auftragspositionen')->insert([
            ['fAufNr' => 22334, 'fArtikelNr' => 10004, 'aufMenge' => 20],
            ['fAufNr' => 22335, 'fArtikelNr' => 10005, 'aufMenge' => 15],
        ]);
    }
}
