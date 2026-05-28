<?php

namespace Tests\Feature\Orders;

use App\Models\Products\Product;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class OrderReadTest extends TestCase
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

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_viewer_can_fetch_all_orders(): void
    {
        $this->createOrderViaApi();

        $response = $this->actingAs($this->viewer)->getJson('/api/orders');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'order_info'  => ['pAufNr', 'aufDat', 'aufTermin', 'fKdNr'],
                             'items',
                             'order_total',
                             'preis_total',
                         ],
                     ],
                 ]);
    }

    public function test_unauthenticated_user_cannot_fetch_orders(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
    }

    public function test_viewer_can_fetch_a_single_order(): void
    {
        $data  = $this->createOrderViaApi();
        $aufNr = $data['order_info']['pAufNr'];

        $response = $this->actingAs($this->viewer)
                         ->getJson("/api/orders/{$aufNr}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.order_info.pAufNr', $aufNr)
                 ->assertJsonStructure([
                     'data' => [
                         'order_info',
                         'items' => [
                             '*' => [
                                 'pAufPosNr',
                                 'fArtikelNr',
                                 'bezeichnung',
                                 'aufMenge',
                                 'kaufPreis',
                                 'line_total',
                                 'is_discontinued',
                             ],
                         ],
                         'order_total',
                         'preis_total',
                     ],
                 ]);
    }

    public function test_fetching_non_existent_order_returns_404(): void
    {
        $this->actingAs($this->viewer)
             ->getJson('/api/orders/99999')
             ->assertStatus(404);
    }
}