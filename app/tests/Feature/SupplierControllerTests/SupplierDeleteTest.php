<?php

namespace Tests\Feature\Suppliers;

use App\Models\Suppliers\Supplier;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class SupplierDeleteTest extends TestCase
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

    public function test_admin_can_delete_supplier(): void
    {
        $supplier = Supplier::create([
            Supplier::COL_NAME  => 'To Be Deleted',
            Supplier::COL_EMAIL => 'delete@example.com',
        ]);

        $this->actingAs($this->admin)
             ->deleteJson("/api/suppliers/{$supplier->getKey()}")
             ->assertStatus(204);

        $this->assertSoftDeleted(Supplier::TABLE, [Supplier::COL_ID => $supplier->getKey()]);
    }

    public function test_writer_and_viewer_cannot_delete_supplier(): void
    {
        $supplier = Supplier::create([Supplier::COL_NAME => 'Safe Supplier']);

        $this->actingAs($this->writer)
             ->deleteJson("/api/suppliers/{$supplier->getKey()}")
             ->assertStatus(403);

        $this->actingAs($this->viewer)
             ->deleteJson("/api/suppliers/{$supplier->getKey()}")
             ->assertStatus(403);
    }
}