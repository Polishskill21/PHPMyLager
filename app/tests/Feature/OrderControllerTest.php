<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;


class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

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

        DB::table('warengruppe')->insert([
            'pWgNr'      => 1,
            'warengruppe' => 'Test Group',
        ]);

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    private function forceInMemorySqliteEnvironment(): void
    {
        $forced = [
            'APP_ENV'       => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE'   => ':memory:',
            'DB_URL'        => '',
        ];

        foreach ($forced as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function guardAgainstUnsafeCachedConfig(): void
    {
        $cachedConfigPath = dirname(__DIR__, 2) . '/bootstrap/cache/config.php';

        if (!is_file($cachedConfigPath)) {
            return;
        }

        $cachedConfig      = require $cachedConfigPath;
        $defaultConnection = $cachedConfig['database']['default'] ?? null;
        $sqliteDatabase    = $cachedConfig['database']['connections']['sqlite']['database'] ?? null;

        if ($defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException(
                'Unsafe cached DB config detected for tests. Clear config cache before running tests.'
            );
        }
    }


    /**
     * Insert a product into `artikel` and return it as a Product model.
     */
    private function createProduct(array $overrides = []): Product
    {
        $attrs = array_merge([
            'bezeichnung' => 'Test Product',
            'fWgNr'       => 1,
            'ekPreis'     => 5.00,
            'vkPreis'     => 10.00,
            'bestand'     => 100,
            'meldeBest'   => 20,
        ], $overrides);

        return Product::create($attrs);
    }

    /**
     * Insert a customer into `kunden` and return the primary key.
     */
    private function createCustomer(array $overrides = []): int
    {
        $attrs = array_merge([
            'name'    => 'Test Customer',
            'strasse' => 'Teststraße 1',
            'plz'     => 70000,
            'ort'     => 'Stuttgart',
            'email'   => 'test@example.com',
        ], $overrides);

        return DB::table('kunden')->insertGetId($attrs, 'pKdNr');
    }

    /**
     * Create a complete order with one line-item via the API (POST /orders).
     * Returns the decoded response body.
     */
    private function createOrderViaApi(array $overrides = []): array
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $payload = array_merge([
            'aufDat'    => '2024-01-15 09:00:00',
            'fKdNr'     => $kdNr,
            'aufTermin' => '2024-02-01 00:00:00',
            'items'     => [
                [
                    'fArtikelNr' => $product->pArtikelNr,
                    'aufMenge'   => 5,
                ],
            ],
        ], $overrides);

        $response = $this->actingAs($this->admin)
                         ->postJson('/api/orders', $payload);

        return $response->json();
    }

    // ═══════════════════════════════════════════════════════════════════
    //  READ — all authenticated roles can read
    // ═══════════════════════════════════════════════════════════════════

    public function test_viewer_can_fetch_all_orders(): void
    {
        $this->createOrderViaApi();

        $response = $this->actingAs($this->viewer)
                         ->getJson('/api/orders');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     '*' => [
                         'order_info' => ['pAufNr', 'aufDat', 'aufTermin', 'fKdNr'],
                         'items',
                         'order_total',
                         'preis_total',
                     ],
                 ]);
    }

    public function test_unauthenticated_user_cannot_fetch_orders(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
    }

    public function test_viewer_can_fetch_a_single_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $response = $this->actingAs($this->viewer)
                         ->getJson("/api/orders/{$aufNr}");

        $response->assertStatus(200)
                 ->assertJsonPath('order_info.pAufNr', $aufNr)
                 ->assertJsonStructure([
                     'order_info',
                     'items' => [
                         '*' => [
                             'pAufPosNr',
                             'fArtikelNr',
                             'bezeichnung',
                             'aufMenge',
                             'kaufPreis',
                             'line_total',
                             'is_discontinued'
                         ],
                     ],
                     'order_total',
                     'preis_total',
                 ]);
    }

    public function test_fetching_non_existent_order_returns_404(): void
    {
        $this->actingAs($this->viewer)
             ->getJson('/api/orders/99999')
             ->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CREATE — admin and writer only
    // ═══════════════════════════════════════════════════════════════════

    public function test_admin_can_create_an_order(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['vkPreis' => 10.00, 'bestand' => 50]);

        $response = $this->actingAs($this->admin)
                         ->postJson('/api/orders', [
                             'aufDat'    => '2024-03-01 08:00:00',
                             'fKdNr'     => $kdNr,
                             'aufTermin' => '2024-03-15 00:00:00',
                             'items'     => [
                                 ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 10],
                             ],
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('order_info.fKdNr', $kdNr)
                 ->assertJsonPath('order_total', 10)
                 ->assertJsonPath('preis_total', 100); // 10 * 10.00
    }

    public function test_writer_can_create_an_order(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $response = $this->actingAs($this->writer)
                         ->postJson('/api/orders', [
                             'aufDat'    => '2024-03-01 08:00:00',
                             'fKdNr'     => $kdNr,
                             'aufTermin' => '2024-03-15 00:00:00',
                             'items'     => [
                                 ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 1],
                             ],
                         ]);

        $response->assertStatus(201);
    }

    public function test_viewer_cannot_create_an_order(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $this->actingAs($this->viewer)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 1],
                 ],
             ])
             ->assertStatus(403);
    }


    public function test_price_at_purchase_is_snapshotted_at_creation(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['vkPreis' => 25.25, 'bestand' => 50]);

        $response = $this->actingAs($this->admin)
                         ->postJson('/api/orders', [
                             'aufDat'    => '2024-03-01 08:00:00',
                             'fKdNr'     => $kdNr,
                             'aufTermin' => '2024-03-15 00:00:00',
                             'items'     => [
                                 ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 3],
                             ],
                         ]);

        $response->assertStatus(201);

        $this->assertEquals(25.25, $response->json('items.0.kaufPreis'));
        $this->assertEquals(75.75, $response->json('preis_total')); // 3 * 25.25

        
        $product->update(['vkPreis' => 99.00]);

        $aufNr = $response->json('order_info.pAufNr');
        $this->actingAs($this->viewer)
             ->getJson("/api/orders/{$aufNr}")
             ->assertJsonPath('items.0.kaufPreis', 25.25)
             ->assertJsonPath('preis_total', 75.75);
    }

    public function test_stock_is_decremented_when_order_is_created(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['bestand' => 100]);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 30],
                 ],
             ])
             ->assertStatus(201);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 70, // 100 - 30
        ]);
    }

    public function test_order_creation_fails_when_stock_is_insufficient(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['bestand' => 5]);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 99],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);

        // Stock must be unchanged after the failed transaction
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 5,
        ]);
    }

    public function test_order_creation_is_atomic_on_partial_stock_failure(): void
    {
        // Product A has enough stock, product B does not.
        // The whole transaction must roll back
        $kdNr      = $this->createCustomer();
        $productA  = $this->createProduct(['bestand' => 50, 'bezeichnung' => 'Product A']);
        $productB  = $this->createProduct(['bestand' => 2,  'bezeichnung' => 'Product B']);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 10],
                     ['fArtikelNr' => $productB->pArtikelNr, 'aufMenge' => 99],
                 ],
             ])
             ->assertStatus(422);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $productA->pArtikelNr,
            'bestand'    => 50,
        ]);

        $this->assertDatabaseCount('auftragskoepfe', 0);
    }

    // ── Validation ─────────────────────────────────────────────────────

    public function test_order_creation_fails_with_non_existent_customer(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => 99999,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 1],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fKdNr']);
    }

    public function test_order_creation_fails_with_empty_items_array(): void
    {
        $kdNr = $this->createCustomer();

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);
    }

    public function test_order_creation_fails_with_zero_quantity(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 0],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items.0.aufMenge']);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  UPDATE — admin and writer only
    // ═══════════════════════════════════════════════════════════════════

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
                                 [
                                     'pAufPosNr'  => $posNr,
                                     'fArtikelNr' => $artNr,
                                     'aufMenge'   => 5,
                                 ],
                             ],
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('order_info.fKdNr', $newKdNr);
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

    // ── Case A: existing item quantity increased → more stock deducted

    public function test_increasing_item_quantity_deducts_additional_stock(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['vkPreis' => 10.00, 'bestand' => 100]);

        // Create order with quantity 10 → bestand becomes 90
        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 10],
                               ],
                           ]);

        $aufNr = $createResp->json('order_info.pAufNr');
        $posNr = $createResp->json('items.0.pAufPosNr');

        // Update quantity from 10 → 25 (diff = +15 should be deducted)
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
             ->assertJsonPath('order_total', 25);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 75, // 100 - 10 (create) - 15 (update diff)
        ]);
    }

    // ── Case A: existing item quantity decreased → stock is restored

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

        $aufNr = $createResp->json('order_info.pAufNr');
        $posNr = $createResp->json('items.0.pAufPosNr');

        // Update: reduce from 20 → 8, diff = 12 should be returned to stock
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
            'bestand'    => 92, // 100 - 20 + 12
        ]);
    }

    // ── Case B: new item added during update ───────────────────────────

    public function test_new_item_added_during_update_is_snapshotted_and_deducts_stock(): void
    {
        $kdNr       = $this->createCustomer();
        $productA   = $this->createProduct(['vkPreis' => 10.00, 'bestand' => 50, 'bezeichnung' => 'A']);
        $productB   = $this->createProduct(['vkPreis' => 20.00, 'bestand' => 50, 'bezeichnung' => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 5],
                               ],
                           ]);

        $aufNr = $createResp->json('order_info.pAufNr');
        $posNr = $createResp->json('items.0.pAufPosNr');

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

        $updateResp->assertStatus(200)
                   ->assertJsonCount(2, 'items');

        // New item price should be snapshotted
        $newItem = collect($updateResp->json('items'))
            ->firstWhere('fArtikelNr', $productB->pArtikelNr);

        $this->assertEquals(20.00, $newItem['kaufPreis']);

        // Stock for B should be decremented
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $productB->pArtikelNr,
            'bestand'    => 46, // 50 - 4
        ]);
    }

    // ── Case C: item omitted from update body → deleted, stock restored

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

        $aufNr  = $createResp->json('order_info.pAufNr');
        $posNrA = collect($createResp->json('items'))
            ->firstWhere('fArtikelNr', $productA->pArtikelNr)['pAufPosNr'];

        // Submit only product A — product B is intentionally omitted
        $updateResp = $this->actingAs($this->admin)
                           ->putJson("/api/orders/{$aufNr}", [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['pAufPosNr' => $posNrA, 'fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 10],
                               ],
                           ]);

        $updateResp->assertStatus(200)
                   ->assertJsonCount(1, 'items');

        // Product B's stock should be fully restored (15 returned)
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $productB->pArtikelNr,
            'bestand'    => 50,
        ]);

        // The position row for B should no longer exist
        $this->assertDatabaseMissing('auftragspositionen', [
            'fAufNr'     => $aufNr,
            'fArtikelNr' => $productB->pArtikelNr,
        ]);
    }

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

        $aufNr = $createResp->json('order_info.pAufNr');
        $posNr = $createResp->json('items.0.pAufPosNr');

        // Try to silently swap product A → B on the existing position
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     [
                         'pAufPosNr'  => $posNr,
                         'fArtikelNr' => $productB->pArtikelNr, // wrong product for this posNr
                         'aufMenge'   => 5,
                     ],
                 ],
             ])
             ->assertStatus(500); // throws \Exception caught by Laravel's handler
    }

    // ── Guard: updating with insufficient stock is rejected ────────────

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

        $aufNr = $createResp->json('order_info.pAufNr');
        $posNr = $createResp->json('items.0.pAufPosNr');

        // bestand is now 5. Trying to order 50 (diff = +45) must fail.
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

        // Stock must remain unchanged after failed transaction
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 5,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  DELETE — admin only
    // ═══════════════════════════════════════════════════════════════════

    public function test_admin_can_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $this->actingAs($this->admin)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(204);

        $this->assertDatabaseMissing('auftragskoepfe',    ['pAufNr'  => $aufNr]);
        $this->assertDatabaseMissing('auftragspositionen',['fAufNr'  => $aufNr]);
    }

    public function test_writer_cannot_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $this->actingAs($this->writer)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(403);
    }

    public function test_viewer_cannot_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $this->actingAs($this->viewer)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(403);
    }

    public function test_stock_is_fully_restored_when_order_is_deleted(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct(['bestand' => 50, 'bezeichnung' => 'A']);
        $productB = $this->createProduct(['bestand' => 80, 'bezeichnung' => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 20],
                                   ['fArtikelNr' => $productB->pArtikelNr, 'aufMenge' => 30],
                               ],
                           ]);

        $aufNr = $createResp->json('order_info.pAufNr');

        $this->actingAs($this->admin)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(204);

        // Both products must have their original stock restored
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $productA->pArtikelNr,
            'bestand'    => 50,
        ]);
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $productB->pArtikelNr,
            'bestand'    => 80,
        ]);
    }

    public function test_deleting_non_existent_order_returns_404(): void
    {
        $this->actingAs($this->admin)
             ->deleteJson('/api/orders/99999')
             ->assertStatus(404);
    }

    /**
     * A product that has been soft-deleted must still appear on every order
     * with is_discontinued=true and its original bezeichnung intact.
     */
    public function test_soft_deleted_product_still_appears_in_order_with_discontinued_flag(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct([
            'bezeichnung' => 'Legacy Widget',
            'vkPreis'     => 25.00,
            'bestand'     => 10,
        ]);
 
        // Place the order while the product is still active
        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-06-01 10:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-06-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 3],
                               ],
                           ]);
 
        $createResp->assertStatus(201);
        $aufNr = $createResp->json('order_info.pAufNr');
 
        // Soft-delete the product
        $product->delete();
        $this->assertSoftDeleted('artikel', ['pArtikelNr' => $product->pArtikelNr]);
 
        // Fetch the order
        $response = $this->actingAs($this->viewer)
                         ->getJson("/api/orders/{$aufNr}");
 
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'items');
 
        $item = $response->json('items.0');
 
        $this->assertEquals($product->pArtikelNr, $item['fArtikelNr']);
        $this->assertEquals('Legacy Widget', $item['bezeichnung'],
            'bezeichnung must be preserved even after soft-delete');
        $this->assertTrue($item['is_discontinued'],
            'is_discontinued must be true for a soft-deleted product');
    }
 
    /**
     * Active products carry is_discontinued=false in the order response.
     */
    public function test_active_product_in_order_has_is_discontinued_false(): void
    {
        $data = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];
 
        $response = $this->actingAs($this->viewer)
                         ->getJson("/api/orders/{$aufNr}");
 
        $response->assertStatus(200);
 
        foreach ($response->json('items') as $item) {
            $this->assertFalse($item['is_discontinued'],
                'is_discontinued must be false for an active product');
        }
    }
 
    /**
     * Soft-deleted products must NOT appear in the general product listing
     * (GET /api/products).
     */
    public function test_soft_deleted_product_does_not_appear_in_product_listing(): void
    {
        $active      = $this->createProduct(['bezeichnung' => 'Active Product']);
        $discontinued = $this->createProduct(['bezeichnung' => 'Discontinued Product']);
 
        $discontinued->delete();
        $this->assertSoftDeleted('artikel', ['pArtikelNr' => $discontinued->pArtikelNr]);
 
        $response = $this->actingAs($this->viewer)
                         ->getJson('/api/products');
 
        $response->assertStatus(200);
 
        $ids = collect($response->json('products'))->pluck('pArtikelNr');
 
        $this->assertContains($active->pArtikelNr, $ids->all(),
            'Active product must appear in the listing');
        $this->assertNotContains($discontinued->pArtikelNr, $ids->all(),
            'Soft-deleted product must NOT appear in the listing');
    }
}