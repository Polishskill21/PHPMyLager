<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrders\PurchaseOrder;
use App\Models\PurchaseOrders\PurchaseOrderItem;
use App\Models\WarehouseGroups\WarehouseGroup;
use Illuminate\Support\Facades\DB;
use App\Models\Suppliers\Supplier;
use App\Models\Products\Product;
use App\Models\Auth\User;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class PurchaseOrderUpdateTest extends TestCase
{
    use RefreshDatabase, ForcesInMemorySqlite;

    protected User $writer;
    protected Product $product;
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
        $this->product = Product::create([Product::COL_NAME => 'Product A', Product::COL_BESTAND => 0, Product::COL_WG_ID => 1]);
    }

    public function test_can_update_open_order_and_add_lines(): void
    {
        $order = PurchaseOrder::create([
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT => '2026-05-01',
            PurchaseOrder::COL_STATUS   => PurchaseOrderStatus::Open,
        ]);

        $item = $order->items()->create([
            PurchaseOrderItem::COL_F_ARTIKEL_NR     => $this->product->getKey(),
            PurchaseOrderItem::COL_BEST_MENGE       => 5,
            PurchaseOrderItem::COL_GELIEFERTE_MENGE => 0,
        ]);

        $payload = [
            PurchaseOrder::COL_BEST_DAT => '2026-05-02',
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            'items' => [
                [
                    PurchaseOrderItem::COL_ID           => $item->getKey(),
                    PurchaseOrderItem::COL_F_ARTIKEL_NR => $this->product->getKey(),
                    PurchaseOrderItem::COL_BEST_MENGE   => 10, // increased
                ]
            ]
        ];

        $response = $this->actingAs($this->writer)->putJson("/api/purchase-orders/{$order->getKey()}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas((new PurchaseOrderItem)->getTable(), [
            PurchaseOrderItem::COL_ID => $item->getKey(),
            PurchaseOrderItem::COL_BEST_MENGE => 10,
        ]);
    }

    public function test_cannot_update_delivered_or_cancelled_orders(): void
    {
        $order = PurchaseOrder::create([
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT => '2026-05-01',
            PurchaseOrder::COL_STATUS   => PurchaseOrderStatus::Delivered,
        ]);

        $this->actingAs($this->writer)->putJson("/api/purchase-orders/{$order->getKey()}", [
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT => '2026-05-02',
            'items' => [['dummy' => 'data']]
        ])->assertStatus(422);
    }
}