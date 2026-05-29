<?php

namespace Tests\Feature\Orders;

use App\Models\Products\Product;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Auth\User;
use App\Models\Customers\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class OrderDeleteTest extends TestCase
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
            Product::COL_NAME => 'Test Product',
            Product::COL_WG_ID        => 1,
            Product::COL_EK_PREIS     => 5.00,
            Product::COL_VK_PREIS     => 10.00,
            Product::COL_BESTAND     => 100,
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

    public function test_admin_can_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info'][Order::COL_ID];

        $this->actingAs($this->admin)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(204);

        $this->assertDatabaseMissing(Order::TABLE,     [Order::COL_ID => $aufNr]);
        $this->assertDatabaseMissing(OrderItem::TABLE, [OrderItem::COL_F_ARTIKEL_NR => $aufNr]);
    }

    public function test_writer_cannot_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info'][Order::COL_ID];

        $this->actingAs($this->writer)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(403);
    }

    public function test_viewer_cannot_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info'][Order::COL_ID];

        $this->actingAs($this->viewer)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(403);
    }

    public function test_deleting_non_existent_order_returns_404(): void
    {
        $this->actingAs($this->admin)
             ->deleteJson('/api/orders/99999')
             ->assertStatus(404);
    }

    // ── Stock restoration ─────────────────────────────────────────────────────

    public function test_stock_is_fully_restored_when_order_is_deleted(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct([Product::COL_BESTAND => 50, Product::COL_NAME => 'A']);
        $productB = $this->createProduct([Product::COL_BESTAND => 80, Product::COL_NAME => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               Order::COL_AUF_DAT     => '2024-03-01 08:00:00',
                               Order::COL_F_KD_NR     => $kdNr,
                               Order::COL_AUF_TERMIN  => '2024-03-15 00:00:00',
                               'items'     => [
                                   [OrderItem::COL_F_ARTIKEL_NR => $productA->pArtikelNr, OrderItem::COL_AUF_MENGE => 20],
                                   [OrderItem::COL_F_ARTIKEL_NR => $productB->pArtikelNr, OrderItem::COL_AUF_MENGE => 30],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.' .Order::COL_ID);

        $this->actingAs($this->admin)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(204);

        $this->assertDatabaseHas(Product::TABLE, [Product::COL_ID => $productA->pArtikelNr, Product::COL_BESTAND => 50]);
        $this->assertDatabaseHas(Product::TABLE, [Product::COL_ID => $productB->pArtikelNr, Product::COL_BESTAND => 80]);
    }
}