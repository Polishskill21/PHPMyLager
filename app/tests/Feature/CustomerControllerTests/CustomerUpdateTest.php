<?php

namespace Tests\Feature\Customers;

use App\Models\Customers\Customer;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class CustomerUpdateTest extends TestCase
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

    private static int $counter = 0;

    private function createCustomer(array $overrides = []): Customer
    {
        self::$counter++;

        return Customer::create(array_merge([
            Customer::COL_NAME    => 'Test Customer ' . self::$counter,
            Customer::COL_STRASSE => 'Teststrasse ' . self::$counter,
            Customer::COL_PLZ     => '80331',
            Customer::COL_ORT     => 'Muenchen',
            Customer::COL_EMAIL   => 'customer' . self::$counter . '@example.com',
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        self::$counter++;

        return array_merge([
            Customer::COL_NAME    => 'Customer Payload ' . self::$counter,
            Customer::COL_STRASSE => 'Payloadstrasse ' . self::$counter,
            Customer::COL_PLZ     => '70173',
            Customer::COL_ORT     => 'Stuttgart',
            Customer::COL_EMAIL   => 'payload' . self::$counter . '@example.com',
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_update_customer(): void
    {
        $customer = $this->createCustomer();
        $this->putJson("/api/customers/{$customer->pKdNr}", $this->validPayload())->assertStatus(401);
    }

    public function test_admin_and_writer_can_update_customer(): void
    {
        $users = ['admin' => $this->admin, 'writer' => $this->writer];

        foreach ($users as $role => $user) {
            $customer = $this->createCustomer([Customer::COL_EMAIL => "{$role}.update.before@example.com"]);

            $payload = $this->validPayload([
                Customer::COL_NAME    => ucfirst($role) . ' Updated',
                Customer::COL_STRASSE => 'Updated Street 12',
                Customer::COL_PLZ     => '80333',
                Customer::COL_ORT     => 'Augsburg',
                Customer::COL_EMAIL   => "{$role}.update.after@example.com",
            ]);

            $response = $this->actingAs($user)
                             ->putJson("/api/customers/{$customer->pKdNr}", $payload);

            $response->assertStatus(200)
                     ->assertJsonPath('data.' .Customer::COL_ID, $customer->pKdNr)
                     ->assertJsonPath('data.' .Customer::COL_NAME, $payload[Customer::COL_NAME])
                     ->assertJsonPath('data.' .Customer::COL_EMAIL, $payload[Customer::COL_EMAIL]);

            $this->assertDatabaseHas(Customer::TABLE, [
                Customer::COL_ID   => $customer->pKdNr,
                Customer::COL_NAME    => $payload[Customer::COL_NAME],
                Customer::COL_STRASSE => $payload[Customer::COL_STRASSE],
                Customer::COL_PLZ     => (int) $payload[Customer::COL_PLZ],
                Customer::COL_ORT     => $payload[Customer::COL_ORT],
                Customer::COL_EMAIL   => $payload[Customer::COL_EMAIL],
            ]);
        }
    }

    public function test_viewer_cannot_update_customer(): void
    {
        $customer = $this->createCustomer();

        $this->actingAs($this->viewer)
             ->putJson("/api/customers/{$customer->pKdNr}", $this->validPayload([
                 Customer::COL_EMAIL => 'viewer.update@example.com',
             ]))
             ->assertStatus(403);
    }

    public function test_update_allows_same_email_for_the_same_customer(): void
    {
        $customer = $this->createCustomer([Customer::COL_EMAIL => 'same@email.com']);

        $payload = $this->validPayload([
            Customer::COL_NAME  => 'Same Email Still Valid',
            Customer::COL_EMAIL => 'same@email.com',
        ]);

        $response = $this->actingAs($this->writer)
                         ->putJson("/api/customers/{$customer->pKdNr}", $payload);

        $response->assertStatus(200)->assertJsonPath('data.email', 'same@email.com');

        $this->assertDatabaseHas(Customer::TABLE, [
            Customer::COL_ID => $customer->pKdNr,
            Customer::COL_NAME  => 'Same Email Still Valid',
            Customer::COL_EMAIL => 'same@email.com',
        ]);
    }

    public function test_update_rejects_email_used_by_another_customer(): void
    {
        $customerA = $this->createCustomer([Customer::COL_EMAIL => 'customer.a@example.com']);
        $customerB = $this->createCustomer([Customer::COL_EMAIL => 'customer.b@example.com']);

        $payload = $this->validPayload([
            Customer::COL_NAME    => $customerA->name,
            Customer::COL_STRASSE => $customerA->strasse,
            Customer::COL_PLZ     => (string) $customerA->plz,
            Customer::COL_ORT     => $customerA->ort,
            Customer::COL_EMAIL   => $customerB->email,
        ]);

        $response = $this->actingAs($this->writer)
                         ->putJson("/api/customers/{$customerA->pKdNr}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors([Customer::COL_EMAIL]);
    }

    public function test_update_validates_required_fields_and_plz_format(): void
    {
        $customer = $this->createCustomer();

        $payload = $this->validPayload([
            Customer::COL_NAME => '',
            Customer::COL_PLZ  => '12',
        ]);

        $response = $this->actingAs($this->writer)
                         ->putJson("/api/customers/{$customer->pKdNr}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors([Customer::COL_NAME, Customer::COL_PLZ]);
    }

    public function test_updating_non_existent_customer_returns_404(): void
    {
        $this->actingAs($this->admin)
             ->putJson('/api/customers/999999', $this->validPayload())
             ->assertStatus(404);
    }
}