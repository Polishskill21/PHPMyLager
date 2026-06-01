<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Products\Product;
use App\Models\Suppliers\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $writer;
    protected User $viewer;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();

        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        DB::table('warengruppe')->insert(['pWgNr' => 4, 'warengruppe' => 'Test Group']);

        $this->admin    = User::factory()->create(['role' => 'admin']);
        $this->writer   = User::factory()->create(['role' => 'writer']);
        $this->viewer   = User::factory()->create(['role' => 'viewer']);
        $this->supplier = Supplier::create([
            'name'    => 'Acme Tools',
            'strasse' => 'Industrial Road 1',
            'plz'     => '42853',
            'ort'     => 'Remscheid',
            'email'   => 'orders@acme.test',
        ]);
    }

    private function forceInMemorySqliteEnvironment(): void
    {
        $forced = [
            'APP_ENV'       => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE'   => ':memory:',
            'DB_URL'        => '',
        ];

        foreach ($forced as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function guardAgainstUnsafeCachedConfig(): void
    {
        $cachedConfigPath = dirname(__DIR__, 2).'/bootstrap/cache/config.php';

        if (!is_file($cachedConfigPath)) {
            return;
        }

        $cachedConfig = require $cachedConfigPath;
        $defaultConnection = $cachedConfig['database']['default'] ?? null;
        $sqliteDatabase = $cachedConfig['database']['connections']['sqlite']['database'] ?? null;

        if ($defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException('Unsafe cached DB config detected for tests. Clear config cache before running tests.');
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeProduct(int $stock = 100): Product
    {
        return Product::create([
            'bezeichnung' => 'PO Product',
            'fWgNr'       => 4,
            'ekPreis'     => 5.00,
            'vkPreis'     => 9.00,
            'bestand'     => $stock,
            'meldeBest'   => 10,
        ]);
    }

    /** Create an "offen" PO with one line of $qty units; returns [poId, posNr, product]. */
    private function makeOrder(int $qty = 10, int $stock = 100): array
    {
        $product = $this->makeProduct($stock);

        $response = $this->actingAs($this->writer)->postJson('/api/purchase-orders', [
            'fLiefNr'      => $this->supplier->pLiefNr,
            'bestDat'      => '2026-06-01',
            'erwLieferDat' => '2026-06-10',
            'items'        => [
                ['fArtikelNr' => $product->pArtikelNr, 'bestMenge' => $qty, 'ekPreis' => 5.00],
            ],
        ]);

        $response->assertStatus(201)->assertJsonPath('data.order_info.status', 'offen');

        $poId  = $response->json('data.order_info.pBestNr');
        $posNr = $response->json('data.items.0.pBestPosNr');

        return [$poId, $posNr, $product];
    }

    // ---------------------------------------------------------------
    // SHOW — exposes the Delivered fields the detail modal renders
    // ---------------------------------------------------------------

    public function test_show_exposes_delivered_quantity_fields(): void
    {
        [$poId, $posNr] = $this->makeOrder(10);

        $response = $this->actingAs($this->viewer)->getJson("/api/purchase-orders/{$poId}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'order_info' => ['pBestNr', 'status', 'bestDat', 'erwLieferDat'],
                         'items'      => ['*' => ['pBestPosNr', 'fArtikelNr', 'bestMenge', 'gelieferteMenge', 'ekPreis', 'line_total']],
                         'total_ordered',
                         'total_delivered',
                         'total_value',
                     ],
                 ])
                 ->assertJsonPath('data.items.0.bestMenge', 10)
                 ->assertJsonPath('data.items.0.gelieferteMenge', 0)
                 ->assertJsonPath('data.total_ordered', 10)
                 ->assertJsonPath('data.total_delivered', 0);
    }

    // ---------------------------------------------------------------
    // RECEIVE — lifecycle + stock + the $timestamps=false regression
    // ---------------------------------------------------------------

    public function test_partial_receive_promotes_to_bestellt_and_increments_stock(): void
    {
        [$poId, $posNr, $product] = $this->makeOrder(10, stock: 100);

        $response = $this->actingAs($this->writer)->patchJson("/api/purchase-orders/{$poId}/receive", [
            'items' => [['pBestPosNr' => $posNr, 'gelieferteMenge' => 4]],
        ]);

        // 200 (not 500) confirms the bestellkoepfe.updated_at write was suppressed.
        $response->assertStatus(200)
                 ->assertJsonPath('data.order_info.status', 'bestellt')
                 ->assertJsonPath('data.items.0.gelieferteMenge', 4)
                 ->assertJsonPath('data.total_delivered', 4);

        $this->assertDatabaseHas('artikel', ['pArtikelNr' => $product->pArtikelNr, 'bestand' => 104]);
    }

    public function test_full_receive_marks_geliefert(): void
    {
        [$poId, $posNr, $product] = $this->makeOrder(10, stock: 100);

        $this->actingAs($this->writer)->patchJson("/api/purchase-orders/{$poId}/receive", [
            'items' => [['pBestPosNr' => $posNr, 'gelieferteMenge' => 10]],
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.order_info.status', 'geliefert')
            ->assertJsonPath('data.total_delivered', 10);

        $this->assertDatabaseHas('artikel', ['pArtikelNr' => $product->pArtikelNr, 'bestand' => 110]);
    }

    public function test_cannot_receive_more_than_remaining(): void
    {
        [$poId, $posNr, $product] = $this->makeOrder(10, stock: 100);

        // 4 of 10 received → 6 remaining.
        $this->actingAs($this->writer)->patchJson("/api/purchase-orders/{$poId}/receive", [
            'items' => [['pBestPosNr' => $posNr, 'gelieferteMenge' => 4]],
        ])->assertStatus(200);

        // Asking for 10 more (only 6 remain) is rejected and stock is unchanged.
        $this->actingAs($this->writer)->patchJson("/api/purchase-orders/{$poId}/receive", [
            'items' => [['pBestPosNr' => $posNr, 'gelieferteMenge' => 10]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseHas('artikel', ['pArtikelNr' => $product->pArtikelNr, 'bestand' => 104]);
    }

    public function test_viewer_cannot_receive(): void
    {
        [$poId, $posNr] = $this->makeOrder(10);

        $this->actingAs($this->viewer)->patchJson("/api/purchase-orders/{$poId}/receive", [
            'items' => [['pBestPosNr' => $posNr, 'gelieferteMenge' => 1]],
        ])->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // CANCEL — soft-cancel sets status = storniert
    // ---------------------------------------------------------------

    public function test_admin_can_cancel_order_sets_storniert(): void
    {
        [$poId] = $this->makeOrder(10);

        // Cancel is a soft-cancel: 204 No Content, the row is NOT hard-deleted.
        $this->actingAs($this->admin)->deleteJson("/api/purchase-orders/{$poId}")
             ->assertStatus(204);

        $this->actingAs($this->viewer)->getJson("/api/purchase-orders/{$poId}")
             ->assertStatus(200)
             ->assertJsonPath('data.order_info.status', 'storniert');
    }
}
