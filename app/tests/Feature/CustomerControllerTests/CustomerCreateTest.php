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
            'name'    => 'Test Customer ' . $counter,
            'strasse' => 'Teststrasse ' . $counter,
            'plz'     => '80331',
            'ort'     => 'Muenchen',
            'email'   => "customer{$counter}@example.com",
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        self::$payloadCounter++;
        $n = self::$payloadCounter;

        return array_merge([
            'name'    => "Customer Payload {$n}",
            'strasse' => "Payloadstrasse {$n}",
            'plz'     => '70173',
            'ort'     => 'Stuttgart',
            'email'   => "payload{$n}@example.com",
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
                'email' => "{$role}.create@example.com",
                'name'  => ucfirst($role) . ' Create',
            ]);

            $response = $this->actingAs($user)->postJson('/api/customers', $payload);

            $response->assertStatus(201)
                     ->assertJsonPath('data.name', $payload['name'])
                     ->assertJsonPath('data.email', $payload['email']);

            $this->assertArrayNotHasKey('orders', $response->json());

            $this->assertDatabaseHas('kunden', [
                'name'  => $payload['name'],
                'email' => $payload['email'],
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
                 ->assertJsonValidationErrors(['name', 'strasse', 'plz', 'ort', 'email']);
    }

    public function test_store_validates_plz_and_email_format(): void
    {
        $payload = $this->validPayload([
            'plz'   => '1234',        // must be 5 digits
            'email' => 'not-an-email',
        ]);

        $response = $this->actingAs($this->writer)->postJson('/api/customers', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['plz', 'email']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $existing = $this->createCustomer(['email' => 'already@used.com']);

        $payload = $this->validPayload(['email' => $existing->email]);

        $response = $this->actingAs($this->writer)->postJson('/api/customers', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}