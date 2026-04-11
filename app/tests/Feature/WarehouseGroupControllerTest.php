<?php

namespace Tests\Feature;

use App\Models\WarehouseGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WarehouseGroupControllerTest extends TestCase
{
    use RefreshDatabase;

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

        // Create one user per role
        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    private function forceInMemorySqliteEnvironment(): void
    {
        $forced = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];

        foreach ($forced as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function guardAgainstUnsafeCachedConfig(): void
    {
        $cachedConfigPath = dirname(__DIR__, 2).'/bootstrap/cache/config.php';

        if (!is_file($cachedConfigPath)) {
            return;
        }

        $cachedConfig = require $cachedConfigPath;
        $defaultConnection = $cachedConfig['database']['default'] ?? null;
        $sqliteDatabase = $cachedConfig['database']['connections']['sqlite']['database'] ?? null;

        if ($defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException(
                'Unsafe cached DB config detected for tests. Clear config cache before running tests.'
            );
        }
    }

    // ---------------------------------------------------------------
    // READ — all roles can read
    // ---------------------------------------------------------------

    public function test_viewer_can_fetch_all_warehouse_groups(): void
    {
        WarehouseGroup::create(['warengruppe' => 'Test Group 1']);
        WarehouseGroup::create(['warengruppe' => 'Test Group 2']);

        $response = $this->actingAs($this->viewer)
                         ->getJson('/api/warehouse-groups');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'warehouse_groups' => [
                         '*' => ['pWgNr', 'warengruppe']
                     ]
                 ]);
    }

    public function test_unauthenticated_user_cannot_fetch_warehouse_groups(): void
    {
        $response = $this->getJson('/api/warehouse-groups');
        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // CREATE — admin and writer only
    // ---------------------------------------------------------------

    public function test_admin_can_create_a_warehouse_group(): void
    {
        $response = $this->actingAs($this->admin)
                         ->postJson('/api/warehouse-groups', [
                             'warengruppe' => 'New Electronics',
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Warehouse group created successfully')
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

    // ---------------------------------------------------------------
    // UPDATE — admin and writer only
    // ---------------------------------------------------------------

    public function test_admin_can_update_a_warehouse_group(): void
    {
        $group = WarehouseGroup::create(['warengruppe' => 'Old Name']);

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/warehouse-groups/{$group->pWgNr}", [
                             'warengruppe' => 'Updated Name',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Warehouse group updated successfully')
                 ->assertJsonPath('data.warengruppe', 'Updated Name');

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

    // ---------------------------------------------------------------
    // DELETE — admin only
    // ---------------------------------------------------------------

    public function test_admin_can_delete_a_warehouse_group(): void
    {
        $group = WarehouseGroup::create(['warengruppe' => 'To Be Deleted']);
        $id = $group->pWgNr;

        $response = $this->actingAs($this->admin)
                         ->deleteJson("/api/warehouse-groups/{$id}");

        $response->assertStatus(200)
                 ->assertJsonPath('message', "Warehouse Group ID: {$id} deleted successfully");

        $this->assertDatabaseMissing('warengruppe', ['pWgNr' => $id]);
    }

    public function test_writer_cannot_delete_a_warehouse_group(): void
    {
        $group = WarehouseGroup::create(['warengruppe' => 'Protected Group']);

        $response = $this->actingAs($this->writer)
                         ->deleteJson("/api/warehouse-groups/{$group->pWgNr}");

        $response->assertStatus(403);
    }
}