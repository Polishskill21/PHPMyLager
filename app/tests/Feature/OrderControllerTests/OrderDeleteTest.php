<?php

namespace Tests\Feature\Orders;

use App\Models\Products\Product;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class OrderDeleteTest extends TestCase
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

    private function createOrderViaApi(array $overrides = []): array
    {
        $kdNr    = $this->createCustomer();
        $product = $this->createProduct();

        $payload = array_merge([
            'aufDat'    => '2024-01-15 09:00:00',
            'fKdNr'     => $kdNr,
            'aufTermin' => '2024-02-01 00:00:00',
            'items'     => [
                ['fArtikelNr' => $product->pArtikelNr, 'aufMenge' => 5],
            ],
        ], $overrides);

        return $this->actingAs($this->admin)
                    ->postJson('/api/orders', $payload)
                    ->json('data');
    }

    // ── Role-based access ─────────────────────────────────────────────────────

    public function test_admin_can_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $this->actingAs($this->admin)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(204);

        $this->assertDatabaseMissing('auftragskoepfe',     ['pAufNr' => $aufNr]);
        $this->assertDatabaseMissing('auftragspositionen', ['fAufNr' => $aufNr]);
    }

    public function test_writer_cannot_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $this->actingAs($this->writer)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(403);
    }

    public function test_viewer_cannot_delete_an_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $this->actingAs($this->viewer)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(403);
    }

    public function test_deleting_non_existent_order_returns_404(): void
    {
        $this->actingAs($this->admin)
             ->deleteJson('/api/orders/99999')
             ->assertStatus(404);
    }

    // ── Stock restoration ─────────────────────────────────────────────────────

    public function test_stock_is_fully_restored_when_order_is_deleted(): void
    {
        $kdNr     = $this->createCustomer();
        $productA = $this->createProduct(['bestand' => 50, 'bezeichnung' => 'A']);
        $productB = $this->createProduct(['bestand' => 80, 'bezeichnung' => 'B']);

        $createResp = $this->actingAs($this->admin)
                           ->postJson('/api/orders', [
                               'aufDat'    => '2024-03-01 08:00:00',
                               'fKdNr'     => $kdNr,
                               'aufTermin' => '2024-03-15 00:00:00',
                               'items'     => [
                                   ['fArtikelNr' => $productA->pArtikelNr, 'aufMenge' => 20],
                                   ['fArtikelNr' => $productB->pArtikelNr, 'aufMenge' => 30],
                               ],
                           ]);

        $aufNr = $createResp->json('data.order_info.pAufNr');

        $this->actingAs($this->admin)
             ->deleteJson("/api/orders/{$aufNr}")
             ->assertStatus(204);

        $this->assertDatabaseHas('artikel', ['pArtikelNr' => $productA->pArtikelNr, 'bestand' => 50]);
        $this->assertDatabaseHas('artikel', ['pArtikelNr' => $productB->pArtikelNr, 'bestand' => 80]);
    }
}