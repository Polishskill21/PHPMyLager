<?php

namespace Tests\Feature\Customers;

use App\Models\Customers\Customer;
use App\Models\Auth\User;
use App\Models\Orders\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class CustomerReadTest extends TestCase
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

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private static int $customer = 0;

    private function createCustomer(array $overrides = []): Customer
    {
        self::$customer++;

        return Customer::create(array_merge([
            Customer::COL_NAME    => 'Test Customer ' . self::$customer,
            Customer::COL_STRASSE => 'Teststrasse ' . self::$customer,
            Customer::COL_PLZ     => '80331',
            Customer::COL_ORT     => 'Muenchen',
            Customer::COL_EMAIL   => 'customer' . self::$customer . '@example.com',
        ], $overrides));
    }

    private function createOrderForCustomer(int $customerId, array $overrides = []): int
    {
        return DB::table(Order::TABLE)->insertGetId(array_merge([
            Order::COL_AUF_DAT      => '2026-01-10 08:00:00',
            Order::COL_F_KD_NR      => $customerId,
            Order::COL_AUF_TERMIN   => '2026-01-20 00:00:00',
        ], $overrides), Order::COL_ID);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_customer_endpoints(): void
    {
        $customer = $this->createCustomer();

        $this->getJson('/api/customers')->assertStatus(401);
        $this->getJson("/api/customers/{$customer->pKdNr}")->assertStatus(401);
    }

    public function test_viewer_can_fetch_all_customers(): void
    {
        $customerWithOrder    = $this->createCustomer([Customer::COL_EMAIL => 'withorder@example.com']);
        $customerWithoutOrder = $this->createCustomer([Customer::COL_EMAIL => 'withoutorder@example.com']);
        $orderId = $this->createOrderForCustomer($customerWithOrder->pKdNr);

        $response = $this->actingAs($this->viewer)->getJson('/api/customers');

        $response->assertStatus(200)->assertJsonStructure([
            'data' => [
                '*' => [Customer::COL_ID, Customer::COL_NAME, Customer::COL_STRASSE, Customer::COL_PLZ, Customer::COL_ORT, Customer::COL_EMAIL],
            ],
        ]);

        $data = collect($response->json('data'));

        $withOrder    = $data->firstWhere(Customer::COL_ID, $customerWithOrder->pKdNr);
        $withoutOrder = $data->firstWhere(Customer::COL_ID, $customerWithoutOrder->pKdNr);

        $this->assertNotNull($withOrder);
        $this->assertNotNull($withoutOrder);
        $this->assertArrayNotHasKey('orders', $withOrder);
        $this->assertArrayNotHasKey('orders', $withoutOrder);

        $this->assertDatabaseHas(Order::TABLE, [
            Order::COL_ID => $orderId,
            Order::COL_F_KD_NR  => $customerWithOrder->pKdNr,
        ]);
    }

    public function test_viewer_can_fetch_single_customer(): void
    {
        $customer = $this->createCustomer();
        $this->createOrderForCustomer($customer->pKdNr);

        $response = $this->actingAs($this->viewer)
                         ->getJson("/api/customers/{$customer->pKdNr}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.' .Customer::COL_ID, $customer->pKdNr);

        $this->assertArrayNotHasKey('orders', $response->json());
    }

    public function test_fetching_non_existent_customer_returns_404(): void
    {
        $this->actingAs($this->viewer)
             ->getJson('/api/customers/999999')
             ->assertStatus(404);
    }
}