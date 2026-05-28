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
        $group = WarehouseGroup::create(['warengruppe' => 'Old Name']);

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/warehouse-groups/{$group->pWgNr}", [
                             'warengruppe' => 'Updated Name',
                         ]);

        $response->assertStatus(204);

        $this->assertDatabaseHas('warengruppe', [
            'pWgNr'       => $group->pWgNr,
            'warengruppe' => 'Updated Name',
        ]);
    }

    public function test_viewer_cannot_update_a_warehouse_group(): void
    {
        $group = WarehouseGroup::create(['warengruppe' => 'Locked Group']);

        $response = $this->actingAs($this->viewer)
                         ->putJson("/api/warehouse-groups/{$group->pWgNr}", [
                             'warengruppe' => 'Hacked Name',
                         ]);

        $response->assertStatus(403);
    }
}