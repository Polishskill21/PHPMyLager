<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Suppliers\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Guards the Supplier write path. The lieferanten table has no created_at/updated_at
 * columns, so without `public $timestamps = false` on the model, store/update emit an
 * `updated_at` write and 500 — these tests lock that fix in.
 */
class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $writer;
    protected User $viewer;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();

        parent::setUp();

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
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

    public function test_admin_can_create_supplier(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/suppliers', [
            'name'    => 'Remscheid Werkzeuge GmbH',
            'strasse' => 'Industrial Road 12',
            'plz'     => '42853',
            'ort'     => 'Remscheid',
            'email'   => 'orders@remscheid.test',
        ]);

        // 201 (not 500) confirms the lieferanten.updated_at write was suppressed.
        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Remscheid Werkzeuge GmbH');

        $this->assertDatabaseHas('lieferanten', ['name' => 'Remscheid Werkzeuge GmbH']);
    }

    public function test_admin_can_update_supplier(): void
    {
        $supplier = Supplier::create([
            'name'    => 'Old Name',
            'strasse' => 'Old Street 1',
            'plz'     => '11111',
            'ort'     => 'Oldtown',
            'email'   => 'old@supplier.test',
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/suppliers/{$supplier->pLiefNr}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('lieferanten', [
            'pLiefNr' => $supplier->pLiefNr,
            'name'    => 'New Name',
        ]);
    }

    public function test_viewer_cannot_create_supplier(): void
    {
        $this->actingAs($this->viewer)->postJson('/api/suppliers', [
            'name' => 'Sneaky Supplier',
        ])->assertStatus(403);
    }
}
