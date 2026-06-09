<?php

namespace Tests\Feature\Products;

use App\Models\Products\Product;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class ProductCreateTest extends TestCase
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

        DB::table(WarehouseGroup::TABLE)->insert([
            WarehouseGroup::COL_ID     => 4,
            WarehouseGroup::COL_NAME   => 'Test Group',
        ]);

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    public function test_admin_can_create_a_product(): void
    {
        $response = $this->actingAs($this->admin)
                         ->postJson('/api/products', [
                             Product::COL_NAME         => 'New Product 100mm',
                             Product::COL_WG_ID        => 4,
                             Product::COL_EK_PREIS     => 5.00,
                             Product::COL_VK_PREIS     => 15.00,
                             Product::COL_BESTAND      => 50,
                             Product::COL_MELDE_BEST   => 10,
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Product created successfully.')
                 ->assertJsonPath('data.' .Product::COL_NAME, 'New Product 100mm');

        $this->assertDatabaseHas(Product::TABLE, [
            Product::COL_NAME        => 'New Product 100mm',
            Product::COL_BESTAND     => 50,
        ]);
    }

    public function test_writer_can_create_a_product(): void
    {
        $response = $this->actingAs($this->writer)
                         ->postJson('/api/products', [
                             Product::COL_NAME         => 'Writer Product',
                             Product::COL_WG_ID        => 4,
                             Product::COL_EK_PREIS     => 3.00,
                             Product::COL_VK_PREIS     => 6.00,
                             Product::COL_BESTAND      => 20,
                             Product::COL_MELDE_BEST   => 5,
                         ]);

        $response->assertStatus(201);
    }

    public function test_viewer_cannot_create_a_product(): void
    {
        $response = $this->actingAs($this->viewer)
                         ->postJson('/api/products', [
                             Product::COL_NAME         => 'Sneaky Product',
                             Product::COL_WG_ID        => 4,
                             Product::COL_EK_PREIS     => 3.00,
                             Product::COL_VK_PREIS     => 6.00,
                             Product::COL_BESTAND      => 20,
                             Product::COL_MELDE_BEST   => 5,
                         ]);

        $response->assertStatus(403);
    }

    public function test_it_fails_validation_if_warengruppe_does_not_exist(): void
    {
        $response = $this->actingAs($this->admin)
                         ->postJson('/api/products', [
                             Product::COL_NAME         => 'Bad Product',
                             Product::COL_WG_ID        => 999, // does not exist
                             Product::COL_EK_PREIS     => 5.00,
                             Product::COL_VK_PREIS     => 15.00,
                             Product::COL_BESTAND      => 50,
                             Product::COL_MELDE_BEST   => 10,
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([Product::COL_WG_ID ]);
    }

    public function test_store_commits_and_data_persists(): void
    {
        $response = $this->actingAs($this->admin)
                         ->postJson('/api/products', [
                             Product::COL_NAME         => 'Atomic Product',
                             Product::COL_WG_ID        => 4,
                             Product::COL_EK_PREIS     => 5.00,
                             Product::COL_VK_PREIS     => 15.00,
                             Product::COL_BESTAND      => 50,
                             Product::COL_MELDE_BEST   => 10,
                         ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas(Product::TABLE, [Product::COL_NAME => 'Atomic Product']);
    }
}