<?php

namespace Tests\Feature\WarehouseGroups;

use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class WarehouseGroupReadTest extends TestCase
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

    public function test_viewer_can_fetch_all_warehouse_groups(): void
    {
        WarehouseGroup::create(['warengruppe' => 'Test Group 1']);
        WarehouseGroup::create(['warengruppe' => 'Test Group 2']);

        $response = $this->actingAs($this->viewer)
                         ->getJson('/api/warehouse-groups');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['pWgNr', 'warengruppe'],
                     ],
                 ]);
    }

    public function test_unauthenticated_user_cannot_fetch_warehouse_groups(): void
    {
        $response = $this->getJson('/api/warehouse-groups');
        $response->assertStatus(401);
    }
}