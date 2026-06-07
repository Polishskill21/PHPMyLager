<?php

namespace Tests\Feature\Orders;

use App\Models\Products\Product;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Orders\OrderItem;
use App\Models\Auth\User;
use App\Models\Orders\Order;
use App\Models\Customers\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class OrderReadTest extends TestCase
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
            Order::COL_AUF_DAT    => '2024-01-15 09:00:00',
            Order::COL_F_KD_NR     => $kdNr,
            Order::COL_AUF_TERMIN => '2024-02-01 00:00:00',
            'items'     => [
                [OrderItem::COL_F_ARTIKEL_NR => $product->pArtikelNr, OrderItem::COL_AUF_MENGE => 5],
            ],
        ], $overrides);

        return $this->actingAs($this->admin)
                    ->postJson('/api/orders', $payload)
                    ->json('data');
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_viewer_can_fetch_all_orders(): void
    {
        $this->createOrderViaApi();

        $response = $this->actingAs($this->viewer)->getJson('/api/orders');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'order_info'  => [Order::COL_ID, Order::COL_AUF_DAT, Order::COL_AUF_TERMIN, Order::COL_F_KD_NR],
                             'items',
                             'order_total',
                             'preis_total',
                         ],
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
        $aufNr = $data['order_info'][Order::COL_ID];

        $response = $this->actingAs($this->viewer)
                         ->getJson("/api/orders/{$aufNr}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.order_info.'.Order::COL_ID, $aufNr)
                 ->assertJsonStructure([
                     'data' => [
                         'order_info',
                         'items' => [
                             '*' => [
                                 OrderItem::COL_ID,
                                 OrderItem::COL_F_ARTIKEL_NR,
                                 Product::COL_NAME,
                                 OrderItem::COL_AUF_MENGE,
                                 OrderItem::COL_KAUF_PREIS,
                                 'line_total',
                                 'is_discontinued',
                             ],
                         ],
                         'order_total',
                         'preis_total',
                     ],
                 ]);
    }

    public function test_fetching_non_existent_order_returns_404(): void
    {
        $this->actingAs($this->viewer)
             ->getJson('/api/orders/99999')
             ->assertStatus(404);
    }
}