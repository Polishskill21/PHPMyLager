<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Smoke tests for the server-rendered list pages. These guard the Blade edits
 * (column add/removes, the segmented storage-location control, the PO Delivered
 * column) — a missing/renamed variable or broken markup surfaces as a 500 here.
 */
class WebPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();

        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
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

    #[DataProvider('pageProvider')]
    public function test_page_renders_for_admin(string $path): void
    {
        $this->actingAs($this->admin)->get($path)->assertStatus(200);
    }

    public static function pageProvider(): array
    {
        return [
            'dashboard'       => ['/dashboard'],
            'products'        => ['/products'],
            'orders'          => ['/orders'],
            'customers'       => ['/customers'],
            'warehouse'       => ['/warehouse'],
            'purchase-orders' => ['/purchase-orders'],
            'suppliers'       => ['/suppliers'],
        ];
    }

    public function test_products_page_has_location_column_and_storage_field(): void
    {
        $res = $this->actingAs($this->admin)->get('/products');
        $res->assertStatus(200)
            ->assertSee('Location')            // new table column header
            ->assertSee('Storage Location')    // form field label
            ->assertSee('f-lp-zone', false);   // segmented control present
    }

    public function test_suppliers_page_has_no_pos_column(): void
    {
        $this->actingAs($this->admin)->get('/suppliers')
             ->assertStatus(200)
             ->assertDontSee('data-sort="pos"', false);
    }

    public function test_warehouse_page_drops_products_count_and_modal_uses_inspect_list(): void
    {
        $this->actingAs($this->admin)->get('/warehouse')
             ->assertStatus(200)
             ->assertDontSee('data-sort="products"', false)   // count column removed
             ->assertDontSee('warehouse-products-table', false) // old in-modal table removed
             ->assertSee('group-products-list', false);         // shared inspect list present
    }

    public function test_purchase_orders_page_detail_has_delivered_column(): void
    {
        $this->actingAs($this->admin)->get('/purchase-orders')
             ->assertStatus(200)
             ->assertSee('po-view-items', false)
             ->assertSee('Delivered');
    }
}
