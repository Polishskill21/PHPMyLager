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
            'name'    => 'Test Customer ' . self::$counter,
            'strasse' => 'Teststrasse ' . self::$counter,
            'plz'     => '80331',
            'ort'     => 'Muenchen',
            'email'   => 'customer' . self::$counter . '@example.com',
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        self::$counter++;

        return array_merge([
            'name'    => 'Customer Payload ' . self::$counter,
            'strasse' => 'Payloadstrasse ' . self::$counter,
            'plz'     => '70173',
            'ort'     => 'Stuttgart',
            'email'   => 'payload' . self::$counter . '@example.com',
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
            $customer = $this->createCustomer(['email' => "{$role}.update.before@example.com"]);

            $payload = $this->validPayload([
                'name'    => ucfirst($role) . ' Updated',
                'strasse' => 'Updated Street 12',
                'plz'     => '80333',
                'ort'     => 'Augsburg',
                'email'   => "{$role}.update.after@example.com",
            ]);

            $response = $this->actingAs($user)
                             ->putJson("/api/customers/{$customer->pKdNr}", $payload);

            $response->assertStatus(200)
                     ->assertJsonPath('data.pKdNr', $customer->pKdNr)
                     ->assertJsonPath('data.name', $payload['name'])
                     ->assertJsonPath('data.email', $payload['email']);

            $this->assertDatabaseHas('kunden', [
                'pKdNr'   => $customer->pKdNr,
                'name'    => $payload['name'],
                'strasse' => $payload['strasse'],
                'plz'     => (int) $payload['plz'],
                'ort'     => $payload['ort'],
                'email'   => $payload['email'],
            ]);
        }
    }

    public function test_viewer_cannot_update_customer(): void
    {
        $customer = $this->createCustomer();

        $this->actingAs($this->viewer)
             ->putJson("/api/customers/{$customer->pKdNr}", $this->validPayload([
                 'email' => 'viewer.update@example.com',
             ]))
             ->assertStatus(403);
    }

    public function test_update_allows_same_email_for_the_same_customer(): void
    {
        $customer = $this->createCustomer(['email' => 'same@email.com']);

        $payload = $this->validPayload([
            'name'  => 'Same Email Still Valid',
            'email' => 'same@email.com',
        ]);

        $response = $this->actingAs($this->writer)
                         ->putJson("/api/customers/{$customer->pKdNr}", $payload);

        $response->assertStatus(200)->assertJsonPath('data.email', 'same@email.com');

        $this->assertDatabaseHas('kunden', [
            'pKdNr' => $customer->pKdNr,
            'name'  => 'Same Email Still Valid',
            'email' => 'same@email.com',
        ]);
    }

    public function test_update_rejects_email_used_by_another_customer(): void
    {
        $customerA = $this->createCustomer(['email' => 'customer.a@example.com']);
        $customerB = $this->createCustomer(['email' => 'customer.b@example.com']);

        $payload = $this->validPayload([
            'name'    => $customerA->name,
            'strasse' => $customerA->strasse,
            'plz'     => (string) $customerA->plz,
            'ort'     => $customerA->ort,
            'email'   => $customerB->email,
        ]);

        $response = $this->actingAs($this->writer)
                         ->putJson("/api/customers/{$customerA->pKdNr}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_update_validates_required_fields_and_plz_format(): void
    {
        $customer = $this->createCustomer();

        $payload = $this->validPayload([
            'name' => '',
            'plz'  => '12',
        ]);

        $response = $this->actingAs($this->writer)
                         ->putJson("/api/customers/{$customer->pKdNr}", $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['name', 'plz']);
    }

    public function test_updating_non_existent_customer_returns_404(): void
    {
        $this->actingAs($this->admin)
             ->putJson('/api/customers/999999', $this->validPayload())
             ->assertStatus(404);
    }
}