<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// to create a test run: docker exec -it phpmylager_app php artisan make:test ProductControllerTest
// and to validate a test run: docker exec -it phpmylager_app php artisan test --filter ProductControllerTest

class ProductControllerTest extends TestCase
{
    // This trait ensures your database resets completely after every single test
    use RefreshDatabase;

    protected User $admin;
    protected User $writer;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the warengruppe needed for FK constraints
        DB::table('warengruppe')->insert([
            'pWgNr' => 4,
            'warengruppe' => 'Test Group'
        ]);

        // Create one user per role — available to all tests
        $this->admin  = User::factory()->create(['role' => 'admin']);
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->viewer = User::factory()->create(['role' => 'viewer']);
    }

    // ---------------------------------------------------------------
    // READ — all roles can read
    // ---------------------------------------------------------------

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
                     'products' => [
                         '*' => ['pArtikelNr', 'bezeichnung', 'fWgNr', 'ekPreis', 'vkPreis']
                     ]
                 ]);
    }

    public function test_unauthenticated_user_cannot_fetch_products(): void
    {
        $response = $this->getJson('/api/products');
        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // CREATE — admin and writer only
    // ---------------------------------------------------------------

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
                 ->assertJsonPath('message', 'Product created successfully')
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

    // ---------------------------------------------------------------
    // UPDATE — admin and writer only
    // ---------------------------------------------------------------

    public function test_admin_can_update_a_product(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Old Name',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $response = $this->actingAs($this->admin)
                         ->putJson("/api/products/{$product->pArtikelNr}", [
                             'bezeichnung' => 'Updated Name',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Product updated successfully')
                 ->assertJsonPath('data.bezeichnung', 'Updated Name');

        $this->assertDatabaseHas('artikel', [
            'pArtikelNr'  => $product->pArtikelNr,
            'bezeichnung' => 'Updated Name',
        ]);
    }

    public function test_viewer_cannot_update_a_product(): void
    {
        $product = Product::create([
            'bezeichnung' => 'Locked Product',
            'fWgNr'       => 4,
            'ekPreis'     => 10,
            'vkPreis'     => 20,
            'bestand'     => 10,
            'meldeBest'   => 5,
        ]);

        $response = $this->actingAs($this->viewer)
                         ->putJson("/api/products/{$product->pArtikelNr}", [
                             'bezeichnung' => 'Hacked Name',
                         ]);

        $response->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // DELETE — admin only
    // ---------------------------------------------------------------

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

        $response->assertStatus(200)
                 ->assertJsonPath('message', "Product ID: {$id} deleted successfully");

        $this->assertDatabaseMissing('artikel', ['pArtikelNr' => $id]);
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
}