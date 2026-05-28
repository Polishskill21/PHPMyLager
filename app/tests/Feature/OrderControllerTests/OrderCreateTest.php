<?php

namespace Tests\Feature\Orders;

use App\Models\Products\Product;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class OrderCreateTest extends TestCase
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

        DB::table('warengruppe')->insert(['pWgNr' => 1, 'warengruppe' => 'Test Group']);

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'bezeichnung' => 'Test Product',
            'fWgNr'       => 1,
            'ekPreis'     => 5.00,
            'vkPreis'     => 10.00,
            'bestand'     => 100,
            'meldeBest'   => 20,
        ], $overrides));
    }

    private function createCustomer(array $overrides = []): int
    {
        return DB::table('kunden')->insertGetId(array_merge([
            'name'    => 'Test Customer',
            'strasse' => 'Teststraße 1',
            'plz'     => 70000,
            'ort'     => 'Stuttgart',
            'email'   => 'test@example.com',
        ], $overrides), 'pKdNr');
    }

    // ── Role-based access ─────────────────────────────────────────────────────

    public function test_admin_can_create_an_order(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['vkPreis' => 10.00, 'bestand' => 50]);

        $response = $this->actingAs($this->admin)
                         ->postJson('/api/orders', [
                             'aufDat'    => '2024-03-01 08:00:00',
                             'fKdNr'     => $kdNr,
                             'aufTermin' => '2024-03-15 00:00:00',
                             'items'     => [
                                 ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 10],
                             ],
                         ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.order_info.fKdNr', $kdNr)
                 ->assertJsonPath('data.order_total', 10)
                 ->assertJsonPath('data.preis_total', 100.0); // 10 × 10.00
    }

    public function test_writer_can_create_an_order(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $response = $this->actingAs($this->writer)
                         ->postJson('/api/orders', [
                             'aufDat'    => '2024-03-01 08:00:00',
                             'fKdNr'     => $kdNr,
                             'aufTermin' => '2024-03-15 00:00:00',
                             'items'     => [
                                 ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 1],
                             ],
                         ]);

        $response->assertStatus(201);
    }

    public function test_viewer_cannot_create_an_order(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $this->actingAs($this->viewer)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 1],
                 ],
             ])
             ->assertStatus(403);
    }

    // ── Business logic ────────────────────────────────────────────────────────

    public function test_price_at_purchase_is_snapshotted_at_creation(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['vkPreis' => 25.25, 'bestand' => 50]);

        $response = $this->actingAs($this->admin)
                         ->postJson('/api/orders', [
                             'aufDat'    => '2024-03-01 08:00:00',
                             'fKdNr'     => $kdNr,
                             'aufTermin' => '2024-03-15 00:00:00',
                             'items'     => [
                                 ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 3],
                             ],
                         ]);

        $response->assertStatus(201);
        $this->assertEquals(25.25, $response->json('data.items.0.kaufPreis'));
        $this->assertEquals(75.75, $response->json('data.preis_total')); // 3 × 25.25

        // Changing the price must not affect the saved snapshot
        $product->update(['vkPreis' => 99.00]);

        $aufNr = $response->json('data.order_info.pAufNr');
        $this->actingAs($this->viewer)
             ->getJson("/api/orders/{$aufNr}")
             ->assertJsonPath('data.items.0.kaufPreis', 25.25)
             ->assertJsonPath('data.preis_total', 75.75);
    }

    public function test_stock_is_decremented_when_order_is_created(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['bestand' => 100]);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 30],
                 ],
             ])
             ->assertStatus(201);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 70, // 100 − 30
        ]);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_order_creation_fails_when_stock_is_insufficient(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct(['bestand' => 5]);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 99],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bestand'    => 5,
        ]);
    }

    public function test_order_creation_is_atomic_on_partial_stock_failure(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct(['bestand' => 50, 'bezeichnung' => 'Product A']);
        $productB = $this->createProduct(['bestand' => 2,  'bezeichnung' => 'Product B']);

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 10],
                     ['fArtikelNr' => $productB->pArtikelNr, 'aufMenge' => 99],
                 ],
             ])
             ->assertStatus(422);

        // Product A stock must be rolled back despite passing individually
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $productA->pArtikelNr,
            'bestand'    => 50,
        ]);

        $this->assertDatabaseCount('auftragskoepfe', 0);
    }

    public function test_order_creation_fails_with_non_existent_customer(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => 99999,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 1],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['fKdNr']);
    }

    public function test_order_creation_fails_with_empty_items_array(): void
    {
        $kdNr = $this->createCustomer();

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);
    }

    public function test_order_creation_fails_with_zero_quantity(): void
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $this->actingAs($this->admin)
             ->postJson('/api/orders', [
                 'aufDat'    => '2024-03-01 08:00:00',
                 'fKdNr'     => $kdNr,
                 'aufTermin' => '2024-03-15 00:00:00',
                 'items'     => [
                     ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 0],
                 ],
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items.0.aufMenge']);
    }
}