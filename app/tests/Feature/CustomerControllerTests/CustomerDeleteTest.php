<?php

namespace Tests\Feature\Customers;

use App\Models\Customers\Customer;
use App\Models\Auth\User;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
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
            Customer::COL_NAME    => 'Test Customer ' . self::$counter,
            Customer::COL_STRASSE => 'Teststrasse ' . self::$counter,
            Customer::COL_PLZ     => '80331',
            Customer::COL_ORT     => 'Muenchen',
            Customer::COL_EMAIL   => 'customer' . self::$counter . '@example.com',
        ], $overrides));
    }

    private function createOrderForCustomer(int $customerId, array $overrides = []): int
    {
        return DB::table(Order::TABLE)->insertGetId(array_merge([
            Order::COL_AUF_DAT     => '2026-01-10 08:00:00',
            Order::COL_F_KD_NR     => $customerId,
            Order::COL_AUF_TERMIN  => '2026-01-20 00:00:00',
        ], $overrides), Order::COL_ID);
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

    public function test_unauthenticated_user_cannot_delete_customer(): void
    {
        $customer = $this->createCustomer();
        $this->deleteJson("/api/customers/{$customer->pKdNr}")->assertStatus(401);
    }

    public function test_admin_can_soft_delete_customer(): void
    {
        $customer = $this->createCustomer([Customer::COL_EMAIL => 'admin.delete@example.com']);

        $response = $this->actingAs($this->admin)
                         ->deleteJson("/api/customers/{$customer->pKdNr}");

        $response->assertStatus(204);
        $this->assertSoftDeleted(Customer::TABLE, [Customer::COL_ID => $customer->pKdNr]);
    }

    public function test_writer_cannot_soft_delete_customer(): void
    {
        $customer = $this->createCustomer([Customer::COL_EMAIL => 'writer.delete@example.com']);

        $this->actingAs($this->writer)
             ->deleteJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(403);
    }

    public function test_viewer_cannot_soft_delete_customer(): void
    {
        $customer = $this->createCustomer([Customer::COL_EMAIL => 'viewer.delete@example.com']);

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
        $customer = $this->createCustomer([Customer::COL_EMAIL => 'deleted.customer@example.com']);
        $orderId  = $this->createOrderForCustomer($customer->pKdNr);

        $this->actingAs($this->admin)
             ->deleteJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(204);

        $this->assertSoftDeleted(Customer::TABLE, [Customer::COL_ID => $customer->pKdNr]);

        // Related orders must survive the soft-delete
        $this->assertDatabaseHas(Order::TABLE, [
            Order::COL_ID       => $orderId,
            Order::COL_F_KD_NR  => $customer->pKdNr,
        ]);

        // Deleted customer must not appear in index
        $indexResponse = $this->actingAs($this->viewer)->getJson('/api/customers');
        $indexResponse->assertStatus(200);

        $ids = collect($indexResponse->json('data'))->pluck(Customer::COL_ID);
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

        $payload = $this->validPayload([Customer::COL_EMAIL => 'after.delete@example.com']);

        $this->actingAs($this->admin)
             ->putJson("/api/customers/{$customer->pKdNr}", $payload)
             ->assertStatus(404);

        $this->actingAs($this->admin)
             ->deleteJson("/api/customers/{$customer->pKdNr}")
             ->assertStatus(404);
    }
}