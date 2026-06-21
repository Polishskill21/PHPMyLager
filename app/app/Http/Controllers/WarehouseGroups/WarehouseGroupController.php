<?php

namespace App\Http\Controllers\WarehouseGroups;

use App\Models\WarehouseGroups\WarehouseGroup;
use App\Models\Products\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Support\DomainCache;
use Illuminate\Contracts\View\View;

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
        $groups = DomainCache::remember(
            DomainCache::WAREHOUSE_GROUPS,
            'warehouse-groups:index',
            fn () => WarehouseGroup::all()
        );

        return $this->ok($groups);
    }

    /**
     * GET /warehouse-groups/page
     * One load-more chunk of the product-groups list, with server-side
     * search/sort.
     */
    public function page(Request $request): JsonResponse
    {
        return $this->renderListChunk(
            DomainCache::WAREHOUSE_GROUPS,
            $this->browseQuery($request),
            $this->isDefaultListView($request),
            fn (WarehouseGroup $g) => $this->formatRow($g),
            'partials.rows.warehouse-row',
            $request,
        );
    }

    /** Server-rendered /warehouse page (cached default-view first chunk). */
    public function indexView(Request $request): View
    {
        $chunk = $this->firstChunk($request);

        return view('warehouse', [
            'firstRows' => $chunk['rows'],
            'meta'      => $chunk['meta'],
        ]);
    }

    /** First chunk for the server-rendered /warehouse page. */
    private function firstChunk(Request $request): array
    {
        return $this->listChunkData(
            DomainCache::WAREHOUSE_GROUPS,
            $this->browseQuery($request),
            $this->isDefaultListView($request),
            fn (WarehouseGroup $g) => $this->formatRow($g),
            $request,
        );
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

        $products = DomainCache::remember(
            DomainCache::PRODUCTS,
            "products:by-group:{$id}",
            fn () => Product::with('warengruppe')->withExists('inventoryLogs')
                ->where(Product::COL_WG_ID, $id)->get()
        );

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
            DomainCache::flush(DomainCache::WAREHOUSE_GROUPS, DomainCache::PRODUCTS);

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
            DomainCache::flush(DomainCache::WAREHOUSE_GROUPS, DomainCache::PRODUCTS);

            return $this->noContent();
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
    // Browse / list helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function browseQuery(Request $request): Builder
    {
        $query = WarehouseGroup::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn (Builder $q) => $q
                ->where(WarehouseGroup::COL_ID, $search)
                ->orWhere(WarehouseGroup::COL_NAME, 'like', "%{$search}%"));
        }

        $column = $request->query('sort') === 'name' ? WarehouseGroup::COL_NAME : WarehouseGroup::COL_ID;
        $query->orderBy($column, $this->sortDirection($request));

        return $query;
    }

    private function formatRow(WarehouseGroup $group): array
    {
        return [
            'id'   => $group->{WarehouseGroup::COL_ID},
            'name' => $group->{WarehouseGroup::COL_NAME},
        ];
    }

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