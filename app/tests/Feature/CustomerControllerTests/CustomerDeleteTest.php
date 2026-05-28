<?php

namespace Tests\Feature\Customers;

use App\Models\Customers\Customer;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class CustomerDeleteTest extends TestCase
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

    private function createOrderForCustomer(int $customerId, array $overrides = []): int
    {
        return DB::table('auftragskoepfe')->insertGetId(array_merge([
            'aufDat'    => '2026-01-10 08:00:00',
            'fKdNr'     => $customerId,
            'aufTermin' => '2026-01-20 00:00:00',
        ], $overrides), 'pAufNr');
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

    public function test_unauthenticated_user_cannot_delete_customer(): void
    {
        $customer = $this->createCustomer();
        $this->deleteJson("/api/customers/{$customer->pKdNr}")->assertStatus(401);
    }

    public function test_admin_can_soft_delete_customer(): void
    {
        $customer = $this->createCustomer(['email' => 'admin.delete@example.com']);

        $response = $this->actingAs($this->admin)
                         ->deleteJson("/api/customers/{$customer->pKdNr}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('kunden', ['pKdNr' => $customer->pKdNr]);
    }

    public function test_writer_cannot_soft_delete_customer(): void
    {
        $customer = $this->createCustomer(['email' => 'writer.delete@example.com']);

        $this->actingAs($this->writer)
             ->deleteJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(403);
    }

    public function test_viewer_cannot_soft_delete_customer(): void
    {
        $customer = $this->createCustomer(['email' => 'viewer.delete@example.com']);

        $this->actingAs($this->viewer)
             ->deleteJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(403);
    }

    public function test_deleting_non_existent_customer_returns_404(): void
    {
        $this->actingAs($this->admin)
             ->deleteJson('/api/customers/999999')
             ->assertStatus(404);
    }

    public function test_soft_deleted_customer_is_hidden_but_orders_remain_intact(): void
    {
        $customer = $this->createCustomer(['email' => 'deleted.customer@example.com']);
        $orderId  = $this->createOrderForCustomer($customer->pKdNr);

        $this->actingAs($this->admin)
             ->deleteJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(204);

        $this->assertSoftDeleted('kunden', ['pKdNr' => $customer->pKdNr]);

        // Related orders must survive the soft-delete
        $this->assertDatabaseHas('auftragskoepfe', [
            'pAufNr' => $orderId,
            'fKdNr'  => $customer->pKdNr,
        ]);

        // Deleted customer must not appear in index
        $indexResponse = $this->actingAs($this->viewer)->getJson('/api/customers');
        $indexResponse->assertStatus(200);

        $ids = collect($indexResponse->json('data'))->pluck('pKdNr');
        $this->assertFalse($ids->contains($customer->pKdNr));

        // Nor should it be retrievable by ID
        $this->actingAs($this->viewer)
             ->getJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(404);
    }

    public function test_soft_deleted_customer_cannot_be_updated_or_deleted_again(): void
    {
        $customer = $this->createCustomer();

        $this->actingAs($this->admin)
             ->deleteJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(204);

        $payload = $this->validPayload(['email' => 'after.delete@example.com']);

        $this->actingAs($this->admin)
             ->putJson("/api/customers/{$customer->pKdNr}", $payload)
             ->assertStatus(404);

        $this->actingAs($this->admin)
             ->deleteJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(404);
    }
}