<?php

namespace Tests\Feature\Products;

use App\Models\Products\Product;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\ForcesInMemorySqlite;
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

        DB::table('warengruppe')->insert([
            'pWgNr'       => 4,
            'warengruppe' => 'Test Group',
        ]);

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    public function test_viewer_can_fetch_all_products(): void
    {
        Product::create([
            'bezeichnung' => 'Test Item',
            'fWgNr'       => 4,
            'ekPreis'     => 10.50,
            'vkPreis'     => 20.00,
            'bestand'     => 100,
            'meldeBest'   => 20,
        ]);

        $response = $this->actingAs($this->viewer)
                         ->getJson('/api/products');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['pArtikelNr', 'bezeichnung', 'fWgNr', 'ekPreis', 'vkPreis'],
                     ],
                 ]);
    }

    public function test_unauthenticated_user_cannot_fetch_products(): void
    {
        $response = $this->getJson('/api/products');
        $response->assertStatus(401);
    }
}