<?php

namespace Tests\Feature\Orders;

use App\Models\Products\Product;
use App\Models\Customers\Customer;
use App\Models\Orders\Order;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Auth\User;
use App\Models\Orders\OrderItem;
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

        DB::table(WarehouseGroup::TABLE)->insert([WarehouseGroup::COL_ID => 1, WarehouseGroup::COL_NAME => 'Test Group']);

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            Product::COL_NAME         => 'Test Product',
            Product::COL_WG_ID        => 1,
            Product::COL_EK_PREIS     => 5.00,
            Product::COL_VK_PREIS     => 10.00,
            Product::COL_BESTAND      => 100,
            Product::COL_MELDE_BEST   => 20,
        ], $overrides));
    }

    private function createCustomer(array $overrides = []): int
    {
        return DB::table(Customer::TABLE)->insertGetId(array_merge([
            Customer::COL_NAME    => 'Test Customer',
            Customer::COL_STRASSE => 'Teststraße 1',
            Customer::COL_PLZ     => 70000,
            Customer::COL_ORT     => 'Stuttgart',
            Customer::COL_EMAIL   => 'test@example.com',
        ], $overrides), Customer::COL_ID);
    }

    private function createOrderViaApi(array $overrides = []): array
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $payload = array_merge([
            Order::COL_AUF_DAT     => '2024-01-15 09:00:00',
            Order::COL_F_KD_NR     => $kdNr,
            Order::COL_AUF_TERMIN  => '2024-02-01 00:00:00',
            'items'     => [
                [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 5],
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
        $aufNr   = $data['order_info'][Order::COL_ID ];
        $posNr   = $data['items'][0][OrderItem::COL_ID];
        $artNr   = $data['items'][0][OrderItem::COL_F_ARTIKEL_NR];
        $newKdNr = $this->createCustomer([Customer::COL_EMAIL => 'other@example.com']);

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/orders/{$aufNr}", [
                             Order::COL_AUF_DAT     => '2024-04-01 09:00:00',
                             Order::COL_F_KD_NR     => $newKdNr,
                             Order::COL_AUF_TERMIN  => '2024-04-15 00:00:00',
                             'items'     => [
                                 [OrderItem::COL_ID => $posNr, OrderItem::COL_F_ARTIKEL_NR => $artNr, OrderItem::COL_AUF_MENGE => 5],
                             ],
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.order_info.' .Order::COL_F_KD_NR, $newKdNr);
    }

    public function test_writer_can_update_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info'][Order::COL_ID ];
        $posNr = $data['items'][0][OrderItem::COL_ID];
        $artNr = $data['items'][0][OrderItem::COL_F_ARTIKEL_NR];
        $kdNr  = $data['order_info'][Order::COL_F_KD_NR];

        $this->actingAs($this->writer)
             ->putJson("/api/orders/{$aufNr}", [
                 Order::COL_AUF_DAT     => '2024-04-01 09:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-04-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_ID => $posNr, OrderItem::COL_F_ARTIKEL_NR => $artNr, OrderItem::COL_AUF_MENGE => 5],
                 ],
             ])
             ->assertStatus(200);
    }

    public function test_viewer_cannot_update_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info'][Order::COL_ID ];

        $this->actingAs($this->viewer)
             ->putJson("/api/orders/{$aufNr}", [
                 Order::COL_AUF_DAT     => '2024-04-01 09:00:00',
                 Order::COL_F_KD_NR     => $data['order_info'][Order::COL_F_KD_NR],
                 Order::COL_AUF_TERMIN  => '2024-04-15 00:00:00',
                 'items'     => [],
             ])
             ->assertStatus(403);
    }

    // ── Case A: quantity increased — additional stock deducted ─────────────────

    public function test_increasing_item_quantity_deducts_additional_stock(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct([Product::COL_VK_PREIS => 10.00, Product::COL_BESTAND => 100]);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 10],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.' .Order::COL_ID);
        $posNr = $createResp->json('data.items.0.' .OrderItem::COL_ID);

        // Increase from 10 → 25; diff = +15 should be deducted
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_ID => $posNr, OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 25],
                 ],
             ])
             ->assertStatus(200)
             ->assertJsonPath('data.order_total', 25);

        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_ID        => $product->pArtikelNr,
            Product::COL_BESTAND   => 75, // 100 − 10 (create) − 15 (update diff)
        ]);
    }

    // ── Case A: quantity decreased — stock restored ───────────────────────────

    public function test_decreasing_item_quantity_restores_stock(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct([Product::COL_BESTAND => 100]);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 20],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.' .Order::COL_ID);
        $posNr = $createResp->json('data.items.0.' .OrderItem::COL_ID);

        // Decrease from 20 → 8; diff = −12 should be returned
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_ID => $posNr, OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 8],
                 ],
             ])
             ->assertStatus(200);

        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_ID        => $product->pArtikelNr,
            Product::COL_BESTAND   => 92, // 100 − 20 + 12
        ]);
    }

    // ── Case B: new item added during update ──────────────────────────────────

    public function test_new_item_added_during_update_is_snapshotted_and_deducts_stock(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct([Product::COL_VK_PREIS => 10.00, Product::COL_BESTAND => 50, Product::COL_NAME => 'A']);
        $productB = $this->createProduct([Product::COL_VK_PREIS => 20.00, Product::COL_BESTAND => 50, Product::COL_NAME => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_F_ARTIKEL_NR => $productA->pArtikelNr, OrderItem::COL_AUF_MENGE => 5],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.' .Order::COL_ID);
        $posNr = $createResp->json('data.items.0.' .OrderItem::COL_ID);

        $updateResp = $this->actingAs($this->admin)
                           ->putJson("/api/orders/{$aufNr}", [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_ID => $posNr, OrderItem::COL_F_ARTIKEL_NR => $productA->pArtikelNr, OrderItem::COL_AUF_MENGE => 5],
                                   // New item — no pAufPosNr
                                   [OrderItem::COL_F_ARTIKEL_NR => $productB->pArtikelNr, OrderItem::COL_AUF_MENGE => 4],
                               ],
                           ]);

        $updateResp->assertStatus(200)->assertJsonCount(2, 'data.items');

        $newItem = collect($updateResp->json('data.items'))
            ->firstWhere(OrderItem::COL_F_ARTIKEL_NR, $productB->pArtikelNr);

        $this->assertEquals(20.00, $newItem[OrderItem::COL_KAUF_PREIS]);

        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_ID        => $productB->pArtikelNr,
            Product::COL_BESTAND   => 46, // 50 − 4
        ]);
    }

    // ── Case C: item omitted → deleted and stock restored ─────────────────────

    public function test_omitted_item_during_update_is_deleted_and_stock_restored(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct([Product::COL_BESTAND => 50, Product::COL_NAME => 'A']);
        $productB = $this->createProduct([Product::COL_BESTAND => 50, Product::COL_NAME => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_F_ARTIKEL_NR => $productA->pArtikelNr, OrderItem::COL_AUF_MENGE => 10],
                                   [OrderItem::COL_F_ARTIKEL_NR => $productB->pArtikelNr, OrderItem::COL_AUF_MENGE => 15],
                               ],
                           ]);

        $aufNr  = $createResp->json('data.order_info.' .Order::COL_ID);
        $posNrA = collect($createResp->json('data.items'))
            ->firstWhere(OrderItem::COL_F_ARTIKEL_NR, $productA->pArtikelNr)[OrderItem::COL_ID];

        // Send only product A — product B is intentionally omitted
        $updateResp = $this->actingAs($this->admin)
                           ->putJson("/api/orders/{$aufNr}", [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_ID => $posNrA, OrderItem::COL_F_ARTIKEL_NR => $productA->pArtikelNr, OrderItem::COL_AUF_MENGE => 10],
                               ],
                           ]);

        $updateResp->assertStatus(200)->assertJsonCount(1, 'data.items');

        // Product B stock fully restored (15 returned)
        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_ID        => $productB->pArtikelNr,
            Product::COL_BESTAND   => 50,
        ]);

        // The position row for B must no longer exist
        $this->assertDatabaseMissing(OrderItem::TABLE, [
            OrderItem::COL_F_AUF_NR     => $aufNr,
            OrderItem::COL_F_ARTIKEL_NR => $productB->pArtikelNr,
        ]);
    }

    // ── Guard: swapping the product on an existing position is forbidden ───────

    public function test_changing_artikel_nr_on_existing_item_returns_error(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct([Product::COL_BESTAND => 50, Product::COL_NAME => 'A']);
        $productB = $this->createProduct([Product::COL_BESTAND => 50, Product::COL_NAME => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_F_ARTIKEL_NR => $productA->pArtikelNr, OrderItem::COL_AUF_MENGE => 5],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.' .Order::COL_ID);
        $posNr = $createResp->json('data.items.0.' .OrderItem::COL_ID);

        // Try to silently swap product A → B on the same position
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_ID => $posNr, OrderItem::COL_F_ARTIKEL_NR => $productB->pArtikelNr, OrderItem::COL_AUF_MENGE => 5],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);
    }

    // ── Guard: insufficient stock on increase is rejected ─────────────────────

    public function test_increasing_quantity_beyond_available_stock_is_rejected(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct([Product::COL_BESTAND => 10]);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 5],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.' .Order::COL_ID);
        $posNr = $createResp->json('data.items.0.' .OrderItem::COL_ID);

        // bestand is now 5; requesting 50 (diff = +45) must fail
        $this->actingAs($this->admin)
             ->putJson("/api/orders/{$aufNr}", [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_ID => $posNr, OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 50],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);

        // Stock must remain unchanged after the failed transaction
        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_ID        => $product->pArtikelNr,
            Product::COL_BESTAND   => 5,
        ]);
    }
}