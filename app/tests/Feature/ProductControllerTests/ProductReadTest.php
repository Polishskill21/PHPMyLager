<?php

namespace Tests\Feature\Products;

use App\Models\Products\Product;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class ProductReadTest extends TestCase
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

    public function test_viewer_can_fetch_all_products(): void
    {
        Product::create([
            Product::COL_NAME         => 'Test Item',
            Product::COL_WG_ID        => 4,
            Product::COL_EK_PREIS     => 10.50,
            Product::COL_VK_PREIS     => 20.00,
            Product::COL_BESTAND      => 100,
            Product::COL_MELDE_BEST   => 20,
        ]);

        $response = $this->actingAs($this->viewer)
                         ->getJson('/api/products');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [Product::COL_ID, Product::COL_NAME, Product::COL_WG_ID , Product::COL_EK_PREIS, Product::COL_VK_PREIS],
                     ],
                 ]);
    }

    public function test_unauthenticated_user_cannot_fetch_products(): void
    {
        $response = $this->getJson('/api/products');
        $response->assertStatus(401);
    }
}