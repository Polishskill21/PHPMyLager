<?php

namespace Tests\Feature\WarehouseGroups;

use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class WarehouseGroupCreateTest extends TestCase
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

    public function test_admin_can_create_a_warehouse_group(): void
    {
        $response = $this->actingAs($this->admin)
                         ->postJson('/api/warehouse-groups', [
                             'warengruppe' => 'New Electronics',
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Warehouse group created successfully.')
                 ->assertJsonPath('data.warengruppe', 'New Electronics');

        $this->assertDatabaseHas('warengruppe', [
            'warengruppe' => 'New Electronics',
        ]);
    }

    public function test_writer_can_create_a_warehouse_group(): void
    {
        $response = $this->actingAs($this->writer)
                         ->postJson('/api/warehouse-groups', [
                             'warengruppe' => 'Writer Group',
                         ]);

        $response->assertStatus(201);
    }

    public function test_viewer_cannot_create_a_warehouse_group(): void
    {
        $response = $this->actingAs($this->viewer)
                         ->postJson('/api/warehouse-groups', [
                             'warengruppe' => 'Sneaky Group',
                         ]);

        $response->assertStatus(403);
    }
}