<?php

namespace Tests\Feature\Products;

use App\Models\Products\Product;
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

        DB::table('warengruppe')->insert([
            'pWgNr'       => 4,
            'warengruppe' => 'Test Group',
        ]);

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    public function test_admin_can_update_a_product(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Old Name',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/products/{$product->pArtikelNr}", [
                             'bezeichnung' => 'Updated Name',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Product updated successfully.')
                 ->assertJsonPath('data.bezeichnung', 'Updated Name');

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr'  => $product->pArtikelNr,
            'bezeichnung' => 'Updated Name',
        ]);
    }

    public function test_viewer_cannot_update_a_product(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Locked Product',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $response = $this->actingAs($this->viewer)
                         ->putJson("/api/products/{$product->pArtikelNr}", [
                             'bezeichnung' => 'Hacked Name',
                         ]);

        $response->assertStatus(403);
    }

    public function test_update_commits_and_change_persists(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Before Update',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $this->actingAs($this->admin)
             ->putJson("/api/products/{$product->pArtikelNr}", [
                 'bezeichnung' => 'After Update',
             ])
             ->assertStatus(200);

        $this->assertDatabaseHas('artikel', ['bezeichnung' => 'After Update']);
        $this->assertDatabaseMissing('artikel', ['bezeichnung' => 'Before Update']);
    }

    public function test_transaction_is_atomic_on_update(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Before Update',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        DB::spy();

        $this->actingAs($this->admin)
             ->putJson("/api/products/{$product->pArtikelNr}", [
                 'bezeichnung' => 'After Update',
             ]);

        DB::shouldHaveReceived('transaction')->once();
    }

    public function test_update_rolls_back_if_transaction_fails(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Original Name',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $this->mock(\App\Models\Products\Product::class, function ($mock) {
            $mock->shouldReceive('update')
                 ->andThrow(new \RuntimeException('Simulated failure inside transaction'));
        });

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/products/{$product->pArtikelNr}", [
                             'bezeichnung' => 'Should Not Apply',
                         ]);

        $response->assertStatus(500);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr'  => $product->pArtikelNr,
            'bezeichnung' => 'Original Name',
        ]);
    }
}