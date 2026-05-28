<?php

namespace Tests\Feature\WarehouseGroups;

use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class WarehouseGroupUpdateTest extends TestCase
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

    public function test_admin_can_update_a_warehouse_group(): void
    {
        $group = WarehouseGroup::create([WarehouseGroup::COL_NAME => 'Old Name']);

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/warehouse-groups/{$group->{WarehouseGroup::COL_ID}}", [
                             WarehouseGroup::COL_NAME => 'Updated Name',
                         ]);

        $response->assertStatus(204);

        $this->assertDatabaseHas(WarehouseGroup::TABLE, [
            WarehouseGroup::COL_ID   => $group->{WarehouseGroup::COL_ID},
            WarehouseGroup::COL_NAME => 'Updated Name',
        ]);
    }

    public function test_viewer_cannot_update_a_warehouse_group(): void
    {
        $group = WarehouseGroup::create([WarehouseGroup::COL_NAME => 'Locked Group']);

        $response = $this->actingAs($this->viewer)
                         ->putJson("/api/warehouse-groups/{$group->{WarehouseGroup::COL_ID}}", [
                             WarehouseGroup::COL_NAME => 'Hacked Name',
                         ]);

        $response->assertStatus(403);
    }
}