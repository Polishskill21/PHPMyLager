<?php

namespace Tests\Feature\Orders;

use App\Models\Products\Product;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class OrderCreateTest extends TestCase
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

    // ── Role-based access ─────────────────────────────────────────────────────

    public function test_admin_can_create_an_order(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct([Product::COL_VK_PREIS => 10.05, Product::COL_BESTAND => 50]);

        $response = $this->actingAs($this->admin)
                         ->postJson('/api/orders', [
                             Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                             Order::COL_F_KD_NR     => $kdNr,
                             Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                             'items'     => [
                                 [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 10],
                             ],
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.order_info.' .Order::COL_F_KD_NR, $kdNr)
                 ->assertJsonPath('data.order_total', 10)
                 ->assertJsonPath('data.preis_total', 100.5); // 10.05 × 10
    }

    public function test_writer_can_create_an_order(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $response = $this->actingAs($this->writer)
                         ->postJson('/api/orders', [
                             Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                             Order::COL_F_KD_NR     => $kdNr,
                             Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                             'items'     => [
                                 [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 1],
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
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 1],
                 ],
             ])
             ->assertStatus(403);
    }

    // ── Business logic ────────────────────────────────────────────────────────

    public function test_price_at_purchase_is_snapshotted_at_creation(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct([Product::COL_VK_PREIS => 25.25, Product::COL_BESTAND => 50]);

        $response = $this->actingAs($this->admin)
                         ->postJson('/api/orders', [
                             Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                             Order::COL_F_KD_NR     => $kdNr,
                             Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                             'items'     => [
                                 [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 3],
                             ],
                         ]);

        $response->assertStatus(201);
        $this->assertEquals(25.25, $response->json('data.items.0.' .OrderItem::COL_KAUF_PREIS));
        $this->assertEquals(75.75, $response->json('data.preis_total')); // 3 × 25.25

        // Changing the price must not affect the saved snapshot
        $product->update([Product::COL_VK_PREIS => 99.00]);

        $aufNr = $response->json('data.order_info.' .Order::COL_ID);
        $this->actingAs($this->viewer)
             ->getJson("/api/orders/{$aufNr}")
             ->assertJsonPath('data.items.0.' .OrderItem::COL_KAUF_PREIS, 25.25)
             ->assertJsonPath('data.preis_total', 75.75);
    }

    public function test_stock_is_decremented_when_order_is_created(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct([Product::COL_BESTAND => 100]);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 30],
                 ],
             ])
             ->assertStatus(201);

        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_ID        => $product->pArtikelNr,
            Product::COL_BESTAND   => 70, // 100 − 30
        ]);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_order_creation_fails_when_stock_is_insufficient(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct([Product::COL_BESTAND => 5]);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 99],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_ID => $product->pArtikelNr,
            Product::COL_BESTAND    => 5,
        ]);
    }

    public function test_order_creation_is_atomic_on_partial_stock_failure(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct([Product::COL_BESTAND => 50, Product::COL_NAME => 'Product A']);
        $productB = $this->createProduct([Product::COL_BESTAND => 2,  Product::COL_NAME => 'Product B']);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_F_ARTIKEL_NR => $productA->pArtikelNr, OrderItem::COL_AUF_MENGE => 10],
                     [OrderItem::COL_F_ARTIKEL_NR => $productB->pArtikelNr, OrderItem::COL_AUF_MENGE => 99],
                 ],
             ])
             ->assertStatus(422);

        // Product A stock must be rolled back despite passing individually
        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_ID        => $productA->pArtikelNr,
            Product::COL_BESTAND   => 50,
        ]);

        $this->assertDatabaseCount(Order::TABLE, 0);
    }

    public function test_order_creation_fails_with_non_existent_customer(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => 99999,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 1],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors([Order::COL_F_KD_NR]);
    }

    public function test_order_creation_fails_with_empty_items_array(): void
    {
        $kdNr = $this->createCustomer();

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
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
                 Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                 Order::COL_F_KD_NR     => $kdNr,
                 Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                 'items'     => [
                     [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 0],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items.0.' .OrderItem::COL_AUF_MENGE]);
    }
}