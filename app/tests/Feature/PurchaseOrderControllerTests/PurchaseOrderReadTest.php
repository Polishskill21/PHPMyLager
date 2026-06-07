<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\PurchaseOrders\PurchaseOrder;
use App\Models\Suppliers\Supplier;
use App\Enums\PurchaseOrderStatus;
use App\Models\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ForcesInMemorySqlite;
use Tests\TestCase;

class PurchaseOrderReadTest extends TestCase
{
    use RefreshDatabase, ForcesInMemorySqlite;

    protected User $viewer;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        $this->guardAgainstUnsafeCachedConfig();
        $this->forceInMemorySqliteEnvironment();
        parent::setUp();
        $this->viewer = User::factory()->create(['role' => 'viewer']);
        $this->supplier = Supplier::create([Supplier::COL_NAME => 'Test Supplier']);
    }

    public function test_viewer_can_fetch_purchase_orders(): void
    {
        $order = PurchaseOrder::create([
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT => '2026-05-01',
            PurchaseOrder::COL_STATUS   => PurchaseOrderStatus::Open,
        ]);

        $response = $this->actingAs($this->viewer)->getJson('/api/purchase-orders');

        $response->assertStatus(200)
                 ->assertJsonPath('0.order_info.' . PurchaseOrder::COL_ID, $order->pAufNr);
    }

    public function test_viewer_can_fetch_single_purchase_order(): void
    {
        $order = PurchaseOrder::create([
            PurchaseOrder::COL_F_LIEF_NR => $this->supplier->getKey(),
            PurchaseOrder::COL_BEST_DAT => '2026-05-01',
            PurchaseOrder::COL_STATUS   => PurchaseOrderStatus::Open,
        ]);

        $response = $this->actingAs($this->viewer)->getJson("/api/purchase-orders/{$order->pAufNr}");

        $response->assertStatus(200)
                 ->assertJsonPath('order_info.' . PurchaseOrder::COL_ID, $order->pAufNr);
    }
}