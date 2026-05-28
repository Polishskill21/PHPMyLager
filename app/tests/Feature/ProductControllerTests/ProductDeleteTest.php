<?php

namespace Tests\Feature\Products;

use App\Models\Products\Product;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class ProductDeleteTest extends TestCase
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

    public function test_admin_can_delete_a_product(): void
    {
        $product = Product::create([
            'bezeichnung' => 'To Be Deleted',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $id = $product->pArtikelNr;

        $response = $this->actingAs($this->admin)
                         ->deleteJson("/api/products/{$id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('artikel', ['pArtikelNr' => $id]);
    }

    public function test_writer_cannot_delete_a_product(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Protected Product',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $response = $this->actingAs($this->writer)
                         ->deleteJson("/api/products/{$product->pArtikelNr}");

        $response->assertStatus(403);
    }

    public function test_viewer_cannot_delete_a_product(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Protected Product',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $response = $this->actingAs($this->viewer)
                         ->deleteJson("/api/products/{$product->pArtikelNr}");

        $response->assertStatus(403);
    }

    public function test_delete_commits_and_record_is_gone(): void
    {
        $product = Product::create([
            'bezeichnung' => 'To Delete',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $this->actingAs($this->admin)
             ->deleteJson("/api/products/{$product->pArtikelNr}")
             ->assertStatus(204);

        $this->assertSoftDeleted('artikel', ['pArtikelNr' => $product->pArtikelNr]);
    }

    public function test_transaction_is_atomic_on_delete(): void
    {
        $product = Product::create([
            'bezeichnung' => 'To Delete',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        DB::spy();

        $this->actingAs($this->admin)
             ->deleteJson("/api/products/{$product->pArtikelNr}");

        DB::shouldHaveReceived('transaction')->once();
    }
}