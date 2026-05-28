<?php

namespace Tests\Feature\Products;

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

        DB::table('warengruppe')->insert([
            'pWgNr'       => 4,
            'warengruppe' => 'Test Group',
        ]);

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    public function test_admin_can_create_a_product(): void
    {
        $response = $this->actingAs($this->admin)
                         ->postJson('/api/products', [
                             'bezeichnung' => 'New Product 100mm',
                             'fWgNr'       => 4,
                             'ekPreis'     => 5.00,
                             'vkPreis'     => 15.00,
                             'bestand'     => 50,
                             'meldeBest'   => 10,
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Product created successfully.')
                 ->assertJsonPath('data.bezeichnung', 'New Product 100mm');

        $this->assertDatabaseHas('artikel', [
            'bezeichnung' => 'New Product 100mm',
            'bestand'     => 50,
        ]);
    }

    public function test_writer_can_create_a_product(): void
    {
        $response = $this->actingAs($this->writer)
                         ->postJson('/api/products', [
                             'bezeichnung' => 'Writer Product',
                             'fWgNr'       => 4,
                             'ekPreis'     => 3.00,
                             'vkPreis'     => 6.00,
                             'bestand'     => 20,
                             'meldeBest'   => 5,
                         ]);

        $response->assertStatus(201);
    }

    public function test_viewer_cannot_create_a_product(): void
    {
        $response = $this->actingAs($this->viewer)
                         ->postJson('/api/products', [
                             'bezeichnung' => 'Sneaky Product',
                             'fWgNr'       => 4,
                             'ekPreis'     => 3.00,
                             'vkPreis'     => 6.00,
                             'bestand'     => 20,
                             'meldeBest'   => 5,
                         ]);

        $response->assertStatus(403);
    }

    public function test_it_fails_validation_if_warengruppe_does_not_exist(): void
    {
        $response = $this->actingAs($this->admin)
                         ->postJson('/api/products', [
                             'bezeichnung' => 'Bad Product',
                             'fWgNr'       => 999, // does not exist
                             'ekPreis'     => 5.00,
                             'vkPreis'     => 15.00,
                             'bestand'     => 50,
                             'meldeBest'   => 10,
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['fWgNr']);
    }

    public function test_store_commits_and_data_persists(): void
    {
        $response = $this->actingAs($this->admin)
                         ->postJson('/api/products', [
                             'bezeichnung' => 'Atomic Product',
                             'fWgNr'       => 4,
                             'ekPreis'     => 5.00,
                             'vkPreis'     => 15.00,
                             'bestand'     => 50,
                             'meldeBest'   => 10,
                         ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('artikel', ['bezeichnung' => 'Atomic Product']);
    }
}