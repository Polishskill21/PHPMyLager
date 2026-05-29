<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrders\PurchaseOrder;
use App\Models\PurchaseOrders\PurchaseOrderItem;
use App\Models\Products\Product;
use App\Models\Suppliers\Supplier;
use App\Models\Auth\User;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class PurchaseOrderCreateTest extends TestCase
{
    use RefreshDatabase, ForcesInMemorySqlite;

    protected User $writer;
    protected Supplier $supplier;
    protected Product $product;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();
        parent::setUp();

        $this->writer = User::factory()->create(['role' => 'writer']);
        
        $this->supplier = Supplier::create([Supplier::COL_NAME => 'Test Supplier']);
        $this->product = Product::create([Product::COL_NAME => 'Test Product', Product::COL_BESTAND => 0, Product::COL_WG_ID => 1]);
    }

    public function test_writer_can_create_purchase_order_with_items(): void
    {
        $payload = [
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT  => '2026-05-28',
            'items' => [
                [
                    PurchaseOrderItem::COL_F_ARTIKEL_NR => $this->product->getKey(),
                    PurchaseOrderItem::COL_BEST_MENGE   => 10,
                    PurchaseOrderItem::COL_EK_PREIS     => 15.50,
                ]
            ]
        ];

        $response = $this->actingAs($this->writer)->postJson('/api/purchase-orders', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.order_info.' . PurchaseOrder::COL_STATUS, PurchaseOrderStatus::Open->value)
                 ->assertJsonPath('data.total_ordered', 10);

        $this->assertDatabaseHas((new PurchaseOrder)->getTable(), [
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
        ]);
    }

    public function test_creation_fails_if_no_items_provided(): void
    {
        $payload = [
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT  => '2026-05-28',
            'items' => []
        ];

        $this->actingAs($this->writer)->postJson('/api/purchase-orders', $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);
    }
}