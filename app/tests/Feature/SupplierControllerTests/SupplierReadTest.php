<?php

namespace Tests\Feature\Suppliers;

use App\Models\Suppliers\Supplier;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class SupplierReadTest extends TestCase
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

        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    private static int $counter = 0;

    private function createSupplier(array $overrides = []): Supplier
    {
        self::$counter++;
        return Supplier::create(array_merge([
            'name'    => 'Test Supplier ' . self::$counter,
            'strasse' => 'Lieferstrasse ' . self::$counter,
            'plz'     => '10115',
            'ort'     => 'Berlin',
            'email'   => 'supplier' . self::$counter . '@example.com',
        ], $overrides));
    }

    public function test_unauthenticated_user_cannot_access_supplier_endpoints(): void
    {
        $supplier = $this->createSupplier();

        $this->getJson('/api/suppliers')->assertStatus(401);
        $this->getJson("/api/suppliers/{$supplier->getKey()}")->assertStatus(401);
    }

    public function test_viewer_can_fetch_all_suppliers(): void
    {
        $this->createSupplier();
        $this->createSupplier();

        $response = $this->actingAs($this->viewer)->getJson('/api/suppliers');

        $response->assertStatus(200)->assertJsonStructure([
            '*' => [Supplier::COL_ID, Supplier::COL_NAME, Supplier::COL_STRASSE, Supplier::COL_PLZ, Supplier::COL_ORT, Supplier::COL_EMAIL],
        ]);
        
        $this->assertCount(2, $response->json());
    }

    public function test_viewer_can_fetch_single_supplier(): void
    {
        $supplier = $this->createSupplier();

        $response = $this->actingAs($this->viewer)->getJson("/api/suppliers/{$supplier->getKey()}");

        $response->assertStatus(200)
                 ->assertJsonPath(Supplier::COL_ID, $supplier->getKey())
                 ->assertJsonPath(Supplier::COL_NAME, $supplier->{Supplier::COL_NAME});
    }

    public function test_fetching_non_existent_supplier_returns_404(): void
    {
        $this->actingAs($this->viewer)->getJson('/api/suppliers/999999')->assertStatus(404);
    }
}