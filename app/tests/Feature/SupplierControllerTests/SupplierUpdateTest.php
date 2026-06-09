<?php

namespace Tests\Feature\Suppliers;

use App\Models\Suppliers\Supplier;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class SupplierUpdateTest extends TestCase
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

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    private function createSupplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            Supplier::COL_NAME    => 'Old Name',
            Supplier::COL_PLZ     => '10000',
            Supplier::COL_EMAIL   => 'old@example.com',
        ], $overrides));
    }

    public function test_admin_and_writer_can_update_supplier(): void
    {
        $supplier = $this->createSupplier();
        $payload = [
            Supplier::COL_NAME  => 'Updated Name',
            Supplier::COL_EMAIL => 'updated@example.com',
            Supplier::COL_PLZ   => '54321',
        ];

        $response = $this->actingAs($this->writer)
                         ->putJson("/api/suppliers/{$supplier->getKey()}", $payload);

        $response->assertStatus(200)
                 ->assertJsonPath('data.' . Supplier::COL_NAME, 'Updated Name');

        $this->assertDatabaseHas(Supplier::TABLE, [
            Supplier::COL_ID    => $supplier->getKey(),
            Supplier::COL_NAME  => 'Updated Name',
            Supplier::COL_EMAIL => 'updated@example.com',
        ]);
    }

    public function test_update_allows_same_email_for_the_same_supplier(): void
    {
        $supplier = $this->createSupplier([Supplier::COL_EMAIL => 'keep@example.com']);

        $payload = [
            Supplier::COL_NAME  => 'Changed Name',
            Supplier::COL_EMAIL => 'keep@example.com',
        ];

        $this->actingAs($this->writer)
             ->putJson("/api/suppliers/{$supplier->getKey()}", $payload)
             ->assertStatus(200);
    }

    public function test_update_rejects_email_used_by_another_supplier(): void
    {
        $this->createSupplier([Supplier::COL_EMAIL => 'taken@example.com']);
        $supplier = $this->createSupplier([Supplier::COL_EMAIL => 'mine@example.com']);

        $payload = [
            Supplier::COL_NAME  => 'Name',
            Supplier::COL_EMAIL => 'taken@example.com',
        ];

        $this->actingAs($this->writer)
             ->putJson("/api/suppliers/{$supplier->getKey()}", $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors([Supplier::COL_EMAIL]);
    }
}