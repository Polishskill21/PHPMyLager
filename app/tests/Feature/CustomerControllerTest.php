<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
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

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    private function forceInMemorySqliteEnvironment(): void
    {
        $forced = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        foreach ($forced as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
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

        $cachedConfig = require $cachedConfigPath;
        $defaultConnection = $cachedConfig['database']['default'] ?? null;
        $sqliteDatabase = $cachedConfig['database']['connections']['sqlite']['database'] ?? null;

        if ($defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException(
                'Unsafe cached DB config detected for tests. Clear config cache before running tests.'
            );
        }
    }

    private function createCustomer(array $overrides = []): Customer
    {
        static $counter = 0;
        $counter++;

        return Customer::create(array_merge([
            'name' => 'Test Customer ' . $counter,
            'strasse' => 'Teststrasse ' . $counter,
            'plz' => '80331',
            'ort' => 'Muenchen',
            'email' => "customer{$counter}@example.com",
        ], $overrides));
    }

    private function createOrderForCustomer(int $customerId, array $overrides = []): int
    {
        $payload = array_merge([
            'aufDat' => '2026-01-10 08:00:00',
            'fKdNr' => $customerId,
            'aufTermin' => '2026-01-20 00:00:00',
        ], $overrides);

        return DB::table('auftragskoepfe')->insertGetId($payload, 'pAufNr');
    }

    private function validCustomerPayload(array $overrides = []): array
    {
        static $counter = 0;
        $counter++;

        return array_merge([
            'name' => 'Customer Payload ' . $counter,
            'strasse' => 'Payloadstrasse ' . $counter,
            'plz' => '70173',
            'ort' => 'Stuttgart',
            'email' => "payload{$counter}@example.com",
        ], $overrides);
    }

    public function test_unauthenticated_user_cannot_access_customer_endpoints(): void
    {
        $customer = $this->createCustomer();
        $payload = $this->validCustomerPayload();

        $this->getJson('/api/customers')->assertStatus(401);
        $this->getJson("/api/customers/{$customer->pKdNr}")->assertStatus(401);
        $this->postJson('/api/customers', $payload)->assertStatus(401);
        $this->putJson("/api/customers/{$customer->pKdNr}", $payload)->assertStatus(401);
        $this->deleteJson("/api/customers/{$customer->pKdNr}")->assertStatus(401);
    }

    public function test_viewer_can_fetch_all_customers_with_orders(): void
    {
        $customerWithOrder = $this->createCustomer(['email' => 'withorder@example.com']);
        $customerWithoutOrder = $this->createCustomer(['email' => 'withoutorder@example.com']);
        $orderId = $this->createOrderForCustomer($customerWithOrder->pKdNr);

        $response = $this->actingAs($this->viewer)->getJson('/api/customers');

        $response->assertStatus(200)->assertJsonStructure([
            '*' => [
                'customer' => ['pKdNr', 'name', 'strasse', 'plz', 'ort', 'email'],
                'orders',
            ],
        ]);

        $data = collect($response->json());

        $withOrder = $data->firstWhere('customer.pKdNr', $customerWithOrder->pKdNr);
        $withoutOrder = $data->firstWhere('customer.pKdNr', $customerWithoutOrder->pKdNr);

        $this->assertNotNull($withOrder);
        $this->assertNotNull($withoutOrder);
        $this->assertCount(1, $withOrder['orders']);
        $this->assertSame($orderId, $withOrder['orders'][0]['pAufNr']);
        $this->assertCount(0, $withoutOrder['orders']);
    }

    public function test_viewer_can_fetch_single_customer_with_orders(): void
    {
        $customer = $this->createCustomer();
        $orderId = $this->createOrderForCustomer($customer->pKdNr);

        $response = $this->actingAs($this->viewer)->getJson("/api/customers/{$customer->pKdNr}");

        $response->assertStatus(200)
            ->assertJsonPath('customer.pKdNr', $customer->pKdNr)
            ->assertJsonPath('orders.0.pAufNr', $orderId)
            ->assertJsonPath('orders.0.fKdNr', $customer->pKdNr);
    }

    public function test_fetching_non_existent_customer_returns_404(): void
    {
        $this->actingAs($this->viewer)
            ->getJson('/api/customers/999999')
            ->assertStatus(404);
    }

    public function test_admin_writer_and_viewer_can_create_customer(): void
    {
        $users = [
            'admin' => $this->admin,
            'writer' => $this->writer,
            'viewer' => $this->viewer,
        ];

        foreach ($users as $role => $user) {
            $payload = $this->validCustomerPayload([
                'email' => "{$role}.create@example.com",
                'name' => ucfirst($role) . ' Create',
            ]);

            $response = $this->actingAs($user)->postJson('/api/customers', $payload);

            $response->assertStatus(201)
                ->assertJsonPath('customer.name', $payload['name'])
                ->assertJsonPath('customer.email', $payload['email'])
                ->assertJsonCount(0, 'orders');

            $this->assertDatabaseHas('kunden', [
                'name' => $payload['name'],
                'email' => $payload['email'],
            ]);
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->viewer)->postJson('/api/customers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'strasse', 'plz', 'ort', 'email']);
    }

    public function test_store_validates_plz_and_email_format(): void
    {
        $payload = $this->validCustomerPayload([
            'plz' => '1234',
            'email' => 'not-an-email',
        ]);

        $response = $this->actingAs($this->viewer)->postJson('/api/customers', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plz', 'email']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $existing = $this->createCustomer(['email' => 'already@used.com']);

        $payload = $this->validCustomerPayload(['email' => $existing->email]);

        $response = $this->actingAs($this->viewer)->postJson('/api/customers', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_admin_writer_and_viewer_can_update_customer(): void
    {
        $users = [
            'admin' => $this->admin,
            'writer' => $this->writer,
            'viewer' => $this->viewer,
        ];

        foreach ($users as $role => $user) {
            $customer = $this->createCustomer(['email' => "{$role}.update.before@example.com"]);

            $payload = $this->validCustomerPayload([
                'name' => ucfirst($role) . ' Updated',
                'strasse' => 'Updated Street 12',
                'plz' => '80333',
                'ort' => 'Augsburg',
                'email' => "{$role}.update.after@example.com",
            ]);

            $response = $this->actingAs($user)->putJson("/api/customers/{$customer->pKdNr}", $payload);

            $response->assertStatus(200)
                ->assertJsonPath('customer.pKdNr', $customer->pKdNr)
                ->assertJsonPath('customer.name', $payload['name'])
                ->assertJsonPath('customer.email', $payload['email']);

            $this->assertDatabaseHas('kunden', [
                'pKdNr' => $customer->pKdNr,
                'name' => $payload['name'],
                'strasse' => $payload['strasse'],
                'plz' => (int) $payload['plz'],
                'ort' => $payload['ort'],
                'email' => $payload['email'],
            ]);
        }
    }

    public function test_update_allows_same_email_for_the_same_customer(): void
    {
        $customer = $this->createCustomer(['email' => 'same@email.com']);

        $payload = $this->validCustomerPayload([
            'name' => 'Same Email Still Valid',
            'email' => 'same@email.com',
        ]);

        $response = $this->actingAs($this->viewer)->putJson("/api/customers/{$customer->pKdNr}", $payload);

        $response->assertStatus(200)->assertJsonPath('customer.email', 'same@email.com');

        $this->assertDatabaseHas('kunden', [
            'pKdNr' => $customer->pKdNr,
            'name' => 'Same Email Still Valid',
            'email' => 'same@email.com',
        ]);
    }

    public function test_update_rejects_email_used_by_another_customer(): void
    {
        $customerA = $this->createCustomer(['email' => 'customer.a@example.com']);
        $customerB = $this->createCustomer(['email' => 'customer.b@example.com']);

        $payload = $this->validCustomerPayload([
            'name' => $customerA->name,
            'strasse' => $customerA->strasse,
            'plz' => (string) $customerA->plz,
            'ort' => $customerA->ort,
            'email' => $customerB->email,
        ]);

        $response = $this->actingAs($this->viewer)->putJson("/api/customers/{$customerA->pKdNr}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_update_validates_required_fields_and_plz_format(): void
    {
        $customer = $this->createCustomer();

        $payload = $this->validCustomerPayload([
            'name' => '',
            'plz' => '12',
        ]);

        $response = $this->actingAs($this->viewer)->putJson("/api/customers/{$customer->pKdNr}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['name', 'plz']);
    }

    public function test_updating_non_existent_customer_returns_404(): void
    {
        $payload = $this->validCustomerPayload();

        $this->actingAs($this->viewer)
            ->putJson('/api/customers/999999', $payload)
            ->assertStatus(404);
    }

    public function test_admin_writer_and_viewer_can_soft_delete_customer(): void
    {
        $users = [
            'admin' => $this->admin,
            'writer' => $this->writer,
            'viewer' => $this->viewer,
        ];

        foreach ($users as $role => $user) {
            $customer = $this->createCustomer(['email' => "{$role}.delete@example.com"]);

            $response = $this->actingAs($user)->deleteJson("/api/customers/{$customer->pKdNr}");

            $response->assertStatus(204);
            $this->assertSoftDeleted('kunden', ['pKdNr' => $customer->pKdNr]);
        }
    }

    public function test_deleting_non_existent_customer_returns_404(): void
    {
        $this->actingAs($this->viewer)
            ->deleteJson('/api/customers/999999')
            ->assertStatus(404);
    }

    public function test_soft_deleted_customer_is_hidden_but_orders_remain_intact(): void
    {
        $customer = $this->createCustomer(['email' => 'deleted.customer@example.com']);
        $orderId = $this->createOrderForCustomer($customer->pKdNr);

        $this->actingAs($this->viewer)
            ->deleteJson("/api/customers/{$customer->pKdNr}")
            ->assertStatus(204);

        $this->assertSoftDeleted('kunden', ['pKdNr' => $customer->pKdNr]);

        $this->assertDatabaseHas('auftragskoepfe', [
            'pAufNr' => $orderId,
            'fKdNr' => $customer->pKdNr,
        ]);

        $indexResponse = $this->actingAs($this->viewer)->getJson('/api/customers');
        $indexResponse->assertStatus(200);

        $ids = collect($indexResponse->json())->pluck('customer.pKdNr');
        $this->assertFalse($ids->contains($customer->pKdNr));

        $this->actingAs($this->viewer)
            ->getJson("/api/customers/{$customer->pKdNr}")
            ->assertStatus(404);
    }

    public function test_soft_deleted_customer_cannot_be_updated_or_deleted_again(): void
    {
        $customer = $this->createCustomer();

        $this->actingAs($this->viewer)
            ->deleteJson("/api/customers/{$customer->pKdNr}")
            ->assertStatus(204);

        $payload = $this->validCustomerPayload(['email' => 'after.delete@example.com']);

        $this->actingAs($this->viewer)
            ->putJson("/api/customers/{$customer->pKdNr}", $payload)
            ->assertStatus(404);

        $this->actingAs($this->viewer)
            ->deleteJson("/api/customers/{$customer->pKdNr}")
            ->assertStatus(404);
    }
}
