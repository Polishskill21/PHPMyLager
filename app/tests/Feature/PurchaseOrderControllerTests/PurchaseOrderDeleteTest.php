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

class PurchaseOrderDeleteTest extends TestCase
{
    use RefreshDatabase, ForcesInMemorySqlite;

    protected User $admin;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->supplier = Supplier::create([Supplier::COL_NAME => 'Test Supplier']);
    }

    public function test_canceling_order_reverts_partial_stock_delivery(): void
    {
        $product = Product::create([Product::COL_NAME => 'Test', Product::COL_BESTAND => 10]); // Stock includes 5 from this order
        
        $order = PurchaseOrder::create([
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT => '2026-05-01',
            PurchaseOrder::COL_STATUS   => PurchaseOrderStatus::Ordered,
        ]);

        $order->items()->create([
            PurchaseOrderItem::COL_F_ARTIKEL_NR     => $product->getKey(),
            PurchaseOrderItem::COL_BEST_MENGE       => 10,
            PurchaseOrderItem::COL_GELIEFERTE_MENGE => 5,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/purchase-orders/{$order->getKey()}");

        $response->assertStatus(204);

        // Stock should revert by 5
        $this->assertEquals(5, $product->fresh()->{Product::COL_BESTAND});
        
        // Status should be cancelled
        $this->assertEquals(PurchaseOrderStatus::Cancelled, $order->fresh()->{PurchaseOrder::COL_STATUS});
    }

    public function test_cannot_cancel_fully_delivered_order(): void
    {
        $order = PurchaseOrder::create([
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT => '2026-05-01',
            PurchaseOrder::COL_STATUS   => PurchaseOrderStatus::Delivered,
        ]);

        $this->actingAs($this->admin)->deleteJson("/api/purchase-orders/{$order->getKey()}")
             ->assertStatus(422);
    }
}