<?php

namespace Tests\Feature\Orders;

use App\Models\Products\Product;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class OrderUpdateTest extends TestCase
{
    use RefreshDatabase, ForcesInMemorySqlite;

    protected User $admin;
    protected User $writer;
    protected User $viewer;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();

        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        DB::table('warengruppe')->insert(['pWgNr' => 1, 'warengruppe' => 'Test Group']);

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'bezeichnung' => 'Test Product',
            'fWgNr'       => 1,
            'ekPreis'     => 5.00,
            'vkPreis'     => 10.00,
            'bestand'     => 100,
            'meldeBest'   => 20,
        ], $overrides));
    }

    private function createCustomer(array $overrides = []): int
    {
        return DB::table('kunden')->insertGetId(array_merge([
            'name'    => 'Test Customer',
            'strasse' => 'Teststraße 1',
            'plz'     => 70000,
            'ort'     => 'Stuttgart',
            'email'   => 'test@example.com',
        ], $overrides), 'pKdNr');
    }

    private function createOrderViaApi(array $overrides = []): array
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $payload = array_merge([
            'aufDat'    => '2024-01-15 09:00:00',
            'fKdNr'     => $kdNr,
            'aufTermin' => '2024-02-01 00:00:00',
            'items'     => [
                ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 5],
            ],
        ], $overrides);

        return $this->actingAs($this->admin)
                    ->postJson('/api/orders', $payload)
                    ->json('data');
    }

    // ── Role-based access ─────────────────────────────────────────────────────

    public function test_admin_can_update_an_order(): void
    {
        $data    = $this->createOrderViaApi();
        $aufNr   = $data['order_info']['pAufNr'];
        $posNr   = $data['items'][0]['pAufPosNr'];
        $artNr   = $data['items'][0]['fArtikelNr'];
        $newKdNr = $this->createCustomer(['email' => 'other@example.com']);

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/orders/{$aufNr}", [
                             'aufDat'    => '2024-04-01 09:00:00',
                             'fKdNr'     => $newKdNr,
                             'aufTermin' => '2024-04-15 00:00:00',
                             'items'     => [
                                 ['pAufPosNr' => $posNr, 'fArtikelNr' => $artNr, 'aufMenge' => 5],
                             ],
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.order_info.fKdNr', $newKdNr);
    }

    public function test_writer_can_update_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];
        $posNr = $data['items'][0]['pAufPosNr'];
        $artNr = $data['items'][0]['fArtikelNr'];
        $kdNr  = $data['order_info']['fKdNr'];

        $this->actingAs($this->writer)
             ->putJson("/api/orders/{$aufNr}", [
                 'aufDat'    => '2024-04-01 09:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-04-15 00:00:00',
                 'items'     => [
                     ['pAufPosNr' => $posNr, 'fArtikelNr' => $artNr, 'aufMenge' => 5],
                 ],
             ])
             ->assertStatus(200);
    }

    public function test_viewer_cannot_update_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $this->actingAs($this->viewer)
             ->putJson("/api/orders/{$aufNr}", [
                 'aufDat'    => '2024-04-01 09:00:00',
                 'fKdNr'     => $data['order_info']['fKdNr'],
                 'aufTermin' => '2024-04-15 00:00:00',
                 'items'     => [],
             ])
             ->assertStatus(403);
    }

    // ── Case A: quantity increased — additional stock deducted ─────────────────

    public function test_increasing_item_quantity_deducts_additional_stock(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['vkPreis' => 10.00, 'bestand' => 100]);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 10],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.pAufNr');
        $posNr = $createResp->json('data.items.0.pAufPosNr');

        // Increase from 10 → 25; diff = +15 should be deducted
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['pAufPosNr' => $posNr, 'fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 25],
                 ],
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.order_total', 25);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 75, // 100 − 10 (create) − 15 (update diff)
        ]);
    }

    // ── Case A: quantity decreased — stock restored ───────────────────────────

    public function test_decreasing_item_quantity_restores_stock(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['bestand' => 100]);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 20],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.pAufNr');
        $posNr = $createResp->json('data.items.0.pAufPosNr');

        // Decrease from 20 → 8; diff = −12 should be returned
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['pAufPosNr' => $posNr, 'fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 8],
                 ],
             ])
             ->assertStatus(200);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 92, // 100 − 20 + 12
        ]);
    }

    // ── Case B: new item added during update ──────────────────────────────────

    public function test_new_item_added_during_update_is_snapshotted_and_deducts_stock(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct(['vkPreis' => 10.00, 'bestand' => 50, 'bezeichnung' => 'A']);
        $productB = $this->createProduct(['vkPreis' => 20.00, 'bestand' => 50, 'bezeichnung' => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 5],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.pAufNr');
        $posNr = $createResp->json('data.items.0.pAufPosNr');

        $updateResp = $this->actingAs($this->admin)
                           ->putJson("/api/orders/{$aufNr}", [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['pAufPosNr' => $posNr, 'fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 5],
                                   // New item — no pAufPosNr
                                   ['fArtikelNr' => $productB->pArtikelNr, 'aufMenge' => 4],
                               ],
                           ]);

        $updateResp->assertStatus(200)->assertJsonCount(2, 'data.items');

        $newItem = collect($updateResp->json('data.items'))
            ->firstWhere('fArtikelNr', $productB->pArtikelNr);

        $this->assertEquals(20.00, $newItem['kaufPreis']);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $productB->pArtikelNr,
            'bestand'    => 46, // 50 − 4
        ]);
    }

    // ── Case C: item omitted → deleted and stock restored ─────────────────────

    public function test_omitted_item_during_update_is_deleted_and_stock_restored(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct(['bestand' => 50, 'bezeichnung' => 'A']);
        $productB = $this->createProduct(['bestand' => 50, 'bezeichnung' => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 10],
                                   ['fArtikelNr' => $productB->pArtikelNr, 'aufMenge' => 15],
                               ],
                           ]);

        $aufNr  = $createResp->json('data.order_info.pAufNr');
        $posNrA = collect($createResp->json('data.items'))
            ->firstWhere('fArtikelNr', $productA->pArtikelNr)['pAufPosNr'];

        // Send only product A — product B is intentionally omitted
        $updateResp = $this->actingAs($this->admin)
                           ->putJson("/api/orders/{$aufNr}", [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['pAufPosNr' => $posNrA, 'fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 10],
                               ],
                           ]);

        $updateResp->assertStatus(200)->assertJsonCount(1, 'data.items');

        // Product B stock fully restored (15 returned)
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $productB->pArtikelNr,
            'bestand'    => 50,
        ]);

        // The position row for B must no longer exist
        $this->assertDatabaseMissing('auftragspositionen', [
            'fAufNr'     => $aufNr,
            'fArtikelNr' => $productB->pArtikelNr,
        ]);
    }

    // ── Guard: swapping the product on an existing position is forbidden ───────

    public function test_changing_artikel_nr_on_existing_item_returns_error(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct(['bestand' => 50, 'bezeichnung' => 'A']);
        $productB = $this->createProduct(['bestand' => 50, 'bezeichnung' => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 5],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.pAufNr');
        $posNr = $createResp->json('data.items.0.pAufPosNr');

        // Try to silently swap product A → B on the same position
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['pAufPosNr' => $posNr, 'fArtikelNr' => $productB->pArtikelNr, 'aufMenge' => 5],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);
    }

    // ── Guard: insufficient stock on increase is rejected ─────────────────────

    public function test_increasing_quantity_beyond_available_stock_is_rejected(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['bestand' => 10]);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 5],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.pAufNr');
        $posNr = $createResp->json('data.items.0.pAufPosNr');

        // bestand is now 5; requesting 50 (diff = +45) must fail
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['pAufPosNr' => $posNr, 'fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 50],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);

        // Stock must remain unchanged after the failed transaction
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 5,
        ]);
    }
}