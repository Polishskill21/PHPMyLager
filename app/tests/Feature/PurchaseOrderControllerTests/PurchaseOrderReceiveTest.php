<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrders\PurchaseOrder;
use App\Models\PurchaseOrders\PurchaseOrderItem;
use App\Models\WarehouseGroups\WarehouseGroup;
use Illuminate\Support\Facades\DB;
use App\Models\Products\Product;
use App\Models\Suppliers\Supplier;
use App\Models\Auth\User;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class PurchaseOrderReceiveTest extends TestCase
{
    use RefreshDatabase, ForcesInMemorySqlite;

    protected User $writer;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();
        parent::setUp();
        $this->writer = User::factory()->create(['role' => 'writer']);
        $this->supplier = Supplier::create([Supplier::COL_NAME => 'Test Supplier']);

        DB::table(WarehouseGroup::TABLE)->insert([
            WarehouseGroup::COL_ID     => 1,
            WarehouseGroup::COL_NAME   => 'Test Group',
        ]);
    }

    public function test_receiving_delivery_updates_stock_and_status(): void
    {
        $product = Product::create([Product::COL_NAME => 'Test', Product::COL_BESTAND => 10, Product::COL_WG_ID => 1]);
        
        $order = PurchaseOrder::create([
            PurchaseOrder::COL_BEST_DAT => '2026-05-01',
            PurchaseOrder::COL_STATUS   => PurchaseOrderStatus::Ordered,
        ]);

        $item = $order->items()->create([
            PurchaseOrderItem::COL_F_ARTIKEL_NR     => $product->getKey(),
            PurchaseOrderItem::COL_BEST_MENGE       => 20,
            PurchaseOrderItem::COL_GELIEFERTE_MENGE => 0,
        ]);

        $payload = [
            'items' => [
                [
                    PurchaseOrderItem::COL_ID               => $item->getKey(),
                    PurchaseOrderItem::COL_GELIEFERTE_MENGE => 20,
                ]
            ]
        ];

        $response = $this->actingAs($this->writer)->patchJson("/api/purchase-orders/{$order->getKey()}/receive", $payload);

        $response->assertStatus(200);

        // Assert stock incremented
        $this->assertEquals(30, $product->fresh()->{Product::COL_BESTAND});
        
        // Assert line updated
        $this->assertEquals(20, $item->fresh()->{PurchaseOrderItem::COL_GELIEFERTE_MENGE});

        // Assert Order Status is Delivered
        $this->assertEquals(PurchaseOrderStatus::Delivered, $order->fresh()->{PurchaseOrder::COL_STATUS});
    }

    public function test_cannot_receive_more_than_remaining(): void
    {
        $product = Product::create([Product::COL_NAME => 'Test', Product::COL_BESTAND => 0, Product::COL_WG_ID => 1]);
        
        $order = PurchaseOrder::create([
            PurchaseOrder::COL_BEST_DAT => '2026-05-01',
            PurchaseOrder::COL_STATUS   => PurchaseOrderStatus::Ordered,
        ]);

        $item = $order->items()->create([
            PurchaseOrderItem::COL_F_ARTIKEL_NR     => $product->getKey(),
            PurchaseOrderItem::COL_BEST_MENGE       => 10,
            PurchaseOrderItem::COL_GELIEFERTE_MENGE => 5,
        ]);

        $payload = [
            'items' => [
                [
                    PurchaseOrderItem::COL_ID               => $item->getKey(),
                    PurchaseOrderItem::COL_GELIEFERTE_MENGE => 6, // Trying to receive 6 (only 5 left)
                ]
            ]
        ];

        $this->actingAs($this->writer)->patchJson("/api/purchase-orders/{$order->getKey()}/receive", $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['items']);
    }
}