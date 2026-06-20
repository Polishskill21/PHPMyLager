<?php

namespace Tests\Feature\Customers;

use App\Models\Customers\Customer;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class CustomerCreateTest extends TestCase
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static int $payloadCounter = 0;

    private function createCustomer(array $overrides = []): Customer
    {
        static $counter = 0;
        $counter++;

        return Customer::create(array_merge([
            Customer::COL_NAME    => 'Test Customer ' . $counter,
            Customer::COL_STRASSE => 'Teststrasse ' . $counter,
            Customer::COL_PLZ     => '80331',
            Customer::COL_ORT     => 'Muenchen',
            Customer::COL_EMAIL   => "customer{$counter}@example.com",
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        self::$payloadCounter++;
        $n = self::$payloadCounter;

        return array_merge([
            Customer::COL_NAME    => "Customer Payload {$n}",
            Customer::COL_STRASSE => "Payloadstrasse {$n}",
            Customer::COL_PLZ     => '70173',
            Customer::COL_ORT     => 'Stuttgart',
            Customer::COL_EMAIL   => "payload{$n}@example.com",
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_create_customer(): void
    {
        $this->postJson('/api/customers', $this->validPayload())->assertStatus(401);
    }

    public function test_admin_and_writer_can_create_customer(): void
    {
        $users = ['admin' => $this->admin, 'writer' => $this->writer];

        foreach ($users as $role => $user) {
            $payload = $this->validPayload([
                Customer::COL_EMAIL => "{$role}.create@example.com",
                Customer::COL_NAME  => ucfirst($role) . ' Create',
            ]);

            $response = $this->actingAs($user)->postJson('/api/customers', $payload);

            $response->assertStatus(201)
                     ->assertJsonPath('data.' .Customer::COL_NAME, $payload[Customer::COL_NAME])
                     ->assertJsonPath('data.' .Customer::COL_EMAIL, $payload[Customer::COL_EMAIL]);

            $this->assertArrayNotHasKey('orders', $response->json());

            $this->assertDatabaseHas(Customer::TABLE, [
                Customer::COL_NAME  => $payload[Customer::COL_NAME],
                Customer::COL_EMAIL => $payload[Customer::COL_EMAIL],
            ]);
        }
    }

    public function test_viewer_cannot_create_customer(): void
    {
        $this->actingAs($this->viewer)
             ->postJson('/api/customers', $this->validPayload())
             ->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->writer)->postJson('/api/customers', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([Customer::COL_NAME, Customer::COL_STRASSE, Customer::COL_PLZ, Customer::COL_ORT, Customer::COL_EMAIL]);
    }

    public function test_store_validates_plz_and_email_format(): void
    {
        $payload = $this->validPayload([
            Customer::COL_PLZ   => '1234',        // must be 5 digits
            Customer::COL_EMAIL => 'not-an-email',
        ]);

        $response = $this->actingAs($this->writer)->postJson('/api/customers', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([Customer::COL_PLZ, Customer::COL_EMAIL]);
    }

    public function test_store_preserves_leading_zero_in_plz(): void
    {
        $payload = $this->validPayload([
            Customer::COL_PLZ   => '01067',   // Dresden — must not be truncated to 1067 or octal 567
            Customer::COL_EMAIL => 'dresden.leadingzero@example.com',
        ]);

        $response = $this->actingAs($this->writer)->postJson('/api/customers', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.' . Customer::COL_PLZ, '01067');

        $this->assertDatabaseHas(Customer::TABLE, [
            Customer::COL_EMAIL => 'dresden.leadingzero@example.com',
            Customer::COL_PLZ   => '01067',
        ]);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $existing = $this->createCustomer([Customer::COL_EMAIL => 'already@used.com']);

        $payload = $this->validPayload([Customer::COL_EMAIL => $existing->email]);

        $response = $this->actingAs($this->writer)->postJson('/api/customers', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors([Customer::COL_EMAIL]);
    }
}