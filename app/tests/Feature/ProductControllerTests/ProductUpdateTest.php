<?php

namespace Tests\Feature\Products;

use App\Models\Products\Product;
use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class ProductUpdateTest extends TestCase
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

    public function test_admin_can_update_a_product(): void
    {
        $product = Product::create([
            Product::COL_NAME        => 'Old Name',
            Product::COL_WG_ID       => 4,
            Product::COL_EK_PREIS    => 10,
            Product::COL_VK_PREIS    => 20,
            Product::COL_BESTAND     => 10,
            Product::COL_MELDE_BEST  => 5,
        ]);

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/products/{$product->{Product::COL_ID}}", [
                             Product::COL_NAME => 'Updated Name',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Product updated successfully.')
                 ->assertJsonPath('data.' .Product::COL_NAME, 'Updated Name');

        $this->assertDatabaseHas('artikel', [
            Product::COL_ID  => $product->{Product::COL_ID},
            Product::COL_NAME => 'Updated Name',
        ]);
    }

    public function test_viewer_cannot_update_a_product(): void
    {
        $product = Product::create([
            Product::COL_NAME => 'Locked Product',
            Product::COL_WG_ID       => 4,
            Product::COL_EK_PREIS     => 10,
            Product::COL_VK_PREIS     => 20,
            Product::COL_BESTAND     => 10,
            Product::COL_MELDE_BEST   => 5,
        ]);

        $response = $this->actingAs($this->viewer)
                         ->putJson("/api/products/{$product->{Product::COL_ID}}", [
                             Product::COL_NAME => 'Hacked Name',
                         ]);

        $response->assertStatus(403);
    }

    public function test_update_commits_and_change_persists(): void
    {
        $product = Product::create([
            Product::COL_NAME => 'Before Update',
            Product::COL_WG_ID       => 4,
            Product::COL_EK_PREIS     => 10,
            Product::COL_VK_PREIS     => 20,
            Product::COL_BESTAND     => 10,
            Product::COL_MELDE_BEST   => 5,
        ]);

        $this->actingAs($this->admin)
             ->putJson("/api/products/{$product->{Product::COL_ID}}", [
                 Product::COL_NAME => 'After Update',
             ])
             ->assertStatus(200);

        $this->assertDatabaseHas('artikel', [Product::COL_NAME => 'After Update']);
        $this->assertDatabaseMissing('artikel', [Product::COL_NAME => 'Before Update']);
    }

    public function test_transaction_is_atomic_on_update(): void
    {
        $product = Product::create([
            Product::COL_NAME => 'Before Update',
            Product::COL_WG_ID       => 4,
            Product::COL_EK_PREIS     => 10,
            Product::COL_VK_PREIS     => 20,
            Product::COL_BESTAND     => 10,
            Product::COL_MELDE_BEST   => 5,
        ]);

        DB::spy();

        $this->actingAs($this->admin)
             ->putJson("/api/products/{$product->{Product::COL_ID}}", [
                 Product::COL_NAME => 'After Update',
             ]);

        DB::shouldHaveReceived('transaction')->once();
    }

    public function test_update_rolls_back_if_transaction_fails(): void
    {
        $product = Product::create([
            Product::COL_NAME => 'Original Name',
            Product::COL_WG_ID       => 4,
            Product::COL_EK_PREIS     => 10,
            Product::COL_VK_PREIS     => 20,
            Product::COL_BESTAND     => 10,
            Product::COL_MELDE_BEST   => 5,
        ]);

        $this->mock(\App\Models\Products\Product::class, function ($mock) {
            $mock->shouldReceive('update')
                 ->andThrow(new \RuntimeException('Simulated failure inside transaction'));
        });

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/products/{$product->{Product::COL_ID}}", [
                             Product::COL_NAME => 'Should Not Apply',
                         ]);

        $response->assertStatus(500);

        $this->assertDatabaseHas('artikel', [
            Product::COL_ID  => $product->{Product::COL_ID},
            Product::COL_NAME => 'Original Name',
        ]);
    }
}