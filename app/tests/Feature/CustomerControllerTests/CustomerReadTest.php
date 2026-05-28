<?php

namespace Tests\Feature\Customers;

use App\Models\Customers\Customer;
use App\Models\Auth\User;
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

    private static int $customerCounter = 0;

    private function createCustomer(array $overrides = []): Customer
    {
        self::$customerCounter++;

        return Customer::create(array_merge([
            'name'    => 'Test Customer ' . self::$customerCounter,
            'strasse' => 'Teststrasse ' . self::$customerCounter,
            'plz'     => '80331',
            'ort'     => 'Muenchen',
            'email'   => 'customer' . self::$customerCounter . '@example.com',
        ], $overrides));
    }

    private function createOrderForCustomer(int $customerId, array $overrides = []): int
    {
        return DB::table('auftragskoepfe')->insertGetId(array_merge([
            'aufDat'     => '2026-01-10 08:00:00',
            'fKdNr'      => $customerId,
            'aufTermin'  => '2026-01-20 00:00:00',
        ], $overrides), 'pAufNr');
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
        $customerWithOrder    = $this->createCustomer(['email' => 'withorder@example.com']);
        $customerWithoutOrder = $this->createCustomer(['email' => 'withoutorder@example.com']);
        $orderId = $this->createOrderForCustomer($customerWithOrder->pKdNr);

        $response = $this->actingAs($this->viewer)->getJson('/api/customers');

        $response->assertStatus(200)->assertJsonStructure([
            'data' => [
                '*' => ['pKdNr', 'name', 'strasse', 'plz', 'ort', 'email'],
            ],
        ]);

        $data = collect($response->json('data'));

        $withOrder    = $data->firstWhere('pKdNr', $customerWithOrder->pKdNr);
        $withoutOrder = $data->firstWhere('pKdNr', $customerWithoutOrder->pKdNr);

        $this->assertNotNull($withOrder);
        $this->assertNotNull($withoutOrder);
        $this->assertArrayNotHasKey('orders', $withOrder);
        $this->assertArrayNotHasKey('orders', $withoutOrder);

        $this->assertDatabaseHas('auftragskoepfe', [
            'pAufNr' => $orderId,
            'fKdNr'  => $customerWithOrder->pKdNr,
        ]);
    }

    public function test_viewer_can_fetch_single_customer(): void
    {
        $customer = $this->createCustomer();
        $this->createOrderForCustomer($customer->pKdNr);

        $response = $this->actingAs($this->viewer)
                         ->getJson("/api/customers/{$customer->pKdNr}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.pKdNr', $customer->pKdNr);

        $this->assertArrayNotHasKey('orders', $response->json());
    }

    public function test_fetching_non_existent_customer_returns_404(): void
    {
        $this->actingAs($this->viewer)
             ->getJson('/api/customers/999999')
             ->assertStatus(404);
    }
}