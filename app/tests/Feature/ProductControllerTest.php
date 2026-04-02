<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// to create a test run: docker exec -it phpmylager_app php artisan make:test ProductControllerTest
// and to validate a test run: docker exec -it phpmylager_app php artisan test --filter ProductControllerTest

class ProductControllerTest extends TestCase
{
    // This trait ensures your database resets completely after every single test
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('warengruppe')->insert([
            'pWgNr' => 4,
            'warengruppe' => 'Test Group'
        ]);
    }

    public function test_it_can_fetch_all_products()
    {
        // Create a dummy product
        Product::create([
            'bezeichnung' => 'Test Item',
            'fWgNr' => 4,
            'ekPreis' => 10.50,
            'vkPreis' => 20.00,
            'bestand' => 100,
            'meldeBest' => 20,
        ]);

        // Send GET request
        $response = $this->getJson('/api/products');

        // Assert response
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'products' => [
                         '*' => ['pArtikelNr', 'bezeichnung', 'fWgNr', 'ekPreis', 'vkPreis']
                     ]
                 ]);
    }

    public function test_it_can_create_a_product()
    {
        $payload = [
            'bezeichnung' => 'New Product 100mm',
            'fWgNr' => 4,
            'ekPreis' => 5.00,
            'vkPreis' => 15.00,
            'bestand' => 50,
            'meldeBest' => 10,
        ];

        // Send POST request
        $response = $this->postJson('/api/products', $payload);

        // Assert successful creation
        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Product created successfully')
                 ->assertJsonPath('data.bezeichnung', 'New Product 100mm');

        // Verify it actually saved to the database
        $this->assertDatabaseHas('artikel', [
            'bezeichnung' => 'New Product 100mm',
            'bestand' => 50
        ]);
    }

    public function test_it_fails_validation_if_warengruppe_does_not_exist()
    {
        $payload = [
            'bezeichnung' => 'Bad Product',
            'fWgNr' => 999, // This ID does not exist
            'ekPreis' => 5.00,
            'vkPreis' => 15.00,
            'bestand' => 50,
            'meldeBest' => 10,
        ];

        $response = $this->postJson('/api/products', $payload);

        // Assert 422 Unprocessable Entity
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['fWgNr']);
    }

    public function test_it_can_update_a_product()
    {
        $product = Product::create([
            'bezeichnung' => 'Old Name',
            'fWgNr' => 4,
            'ekPreis' => 10,
            'vkPreis' => 20,
            'bestand' => 10,
            'meldeBest' => 5,
        ]);

        // Send PUT request with only the field we want to update
        $response = $this->putJson("/api/products/{$product->pArtikelNr}", [
            'bezeichnung' => 'Updated Name'
        ]);

        // Assert successful update
        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Product updated successfully')
                 ->assertJsonPath('data.bezeichnung', 'Updated Name');

        // Verify DB was updated
        $this->assertDatabaseHas('artikel', [
            'pArtikelNr' => $product->pArtikelNr,
            'bezeichnung' => 'Updated Name'
        ]);
    }

    public function test_it_can_delete_a_product()
    {
        $product = Product::create([
            'bezeichnung' => 'To Be Deleted',
            'fWgNr' => 4,
            'ekPreis' => 10,
            'vkPreis' => 20,
            'bestand' => 10,
            'meldeBest' => 5,
        ]);

        $id = $product->pArtikelNr;

        $response = $this->deleteJson("/api/products/{$id}");

        // Assert JSON message
        $response->assertStatus(200)
                 ->assertJsonPath('message', "Product ID: {$id} deleted successfully");

        // Verify it is gone from the database
        $this->assertDatabaseMissing('artikel', [
            'pArtikelNr' => $id
        ]);
    }
}