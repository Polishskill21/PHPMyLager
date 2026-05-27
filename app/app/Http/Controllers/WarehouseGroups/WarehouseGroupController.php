<?php

namespace App\Http\Controllers\WarehouseGroups;

use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Products\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class WarehouseGroupController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /warehouse-groups
     */
    public function index(): JsonResponse
    {
        return $this->ok(WarehouseGroup::all());
    }

    /**
     * GET /warehouse-groups/{id}
     */
    public function show(int $id): JsonResponse
    {
        $group = WarehouseGroup::find($id);

        if (!$group) {
            return $this->notFound("Warehouse group {$id} not found.");
        }

        return $this->ok($group);
    }

    /**
     * GET /warehouse-groups/{id}/products
     */
    public function products(int $id): JsonResponse
    {
        $group = WarehouseGroup::find($id);

        if (!$group) {
            return $this->notFound("Warehouse group {$id} not found.");
        }

        $products = Product::where(Product::COL_WG_ID, $id)->get();

        return $this->ok($products);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /warehouse-groups
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->rules(),
            $this->customMessages()
        );

        try {
            $group = DB::transaction(fn () => WarehouseGroup::create($validated));

            return $this->created($group, 'Warehouse group created successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PUT /warehouse-groups/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $group = WarehouseGroup::find($id);

        if (!$group) {
            return $this->notFound("Warehouse group {$id} not found.");
        }

        $validated = $request->validate(
            $this->rules(),
            $this->customMessages()
        );

        try {
            $group = DB::transaction(function () use ($group, $validated): WarehouseGroup {
                $group->update($validated);
                return $group->fresh();
            });

            return $this->ok($group, 'Warehouse group updated successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────

    // public function destroy(int $id): JsonResponse
    // {
    //     $group = WarehouseGroup::find($id);
    //
    //     if (!$group) {
    //         return $this->notFound("Warehouse group {$id} not found.");
    //     }
    //
    //     try {
    //         DB::transaction(fn () => $group->delete());
    //         return $this->ok(null, "Warehouse group {$id} deleted successfully.");
    //     } catch (\Exception $e) {
    //         report($e);
    //         return $this->serverError();
    //     }
    // }

    // ─────────────────────────────────────────────────────────────────────────
    // Validation
    // ─────────────────────────────────────────────────────────────────────────

    private function rules(): array
    {
        return [
            WarehouseGroup::COL_NAME => 'required|string|max:50',
        ];
    }

    private function customMessages(): array
    {
        return [
            WarehouseGroup::COL_NAME.'required' => 'The warehouse group name is required.',
            WarehouseGroup::COL_NAME.'.max'     => 'The warehouse group name may not exceed 50 characters.',
        ];
    }
}