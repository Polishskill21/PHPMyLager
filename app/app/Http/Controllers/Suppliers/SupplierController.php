<?php

namespace App\Http\Controllers\Suppliers;

use App\Models\Suppliers\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Support\DomainCache;

class SupplierController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $suppliers = DomainCache::remember(
            DomainCache::SUPPLIERS,
            'suppliers:index',
            fn () => Supplier::all()->map(fn (Supplier $s) => $this->formatSupplier($s))
        );
        return $this->ok($suppliers);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->ok($this->formatSupplier($supplier));
    }

    /**
     * GET /suppliers/page
     * One load-more chunk of the suppliers list, with server-side search/sort.
     */
    public function page(Request $request): JsonResponse
    {
        return $this->renderListChunk(
            DomainCache::SUPPLIERS,
            $this->browseQuery($request),
            $this->isDefaultListView($request),
            fn (Supplier $s) => $this->formatRow($s),
            'partials.rows.suppliers-row',
            $request,
        );
    }

    /** First chunk for the server-rendered /suppliers page. */
    public function firstChunk(Request $request): array
    {
        return $this->listChunkData(
            DomainCache::SUPPLIERS,
            $this->browseQuery($request),
            $this->isDefaultListView($request),
            fn (Supplier $s) => $this->formatRow($s),
            $request,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->storeRules(),
            $this->customMessages()
        );

        try {
            $supplier = DB::transaction(fn () => Supplier::create($validated));
            DomainCache::flush(DomainCache::SUPPLIERS);

            return $this->created($this->formatSupplier($supplier->fresh()), 'Supplier created successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate(
            $this->updateRules($supplier),
            $this->customMessages()
        );

        try {
            $supplier = DB::transaction(function () use ($validated, $supplier): Supplier {
                $supplier->update($validated);
                return $supplier->fresh();
            });
            DomainCache::flush(DomainCache::SUPPLIERS);

            return $this->ok($this->formatSupplier($supplier), 'Supplier updated successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Supplier $supplier): JsonResponse
    {
        try {
            DB::transaction(fn () => $supplier->delete());
            DomainCache::flush(DomainCache::SUPPLIERS);

            return $this->noContent();
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }


    // ─────────────────────────────────────────────────────────────────────────
    // Browse / list helpers
    // ─────────────────────────────────────────────────────────────────────────

    private const SORTABLE = [
        'id'     => Supplier::COL_ID,
        'name'   => Supplier::COL_NAME,
        'email'  => Supplier::COL_EMAIL,
        'street' => Supplier::COL_STRASSE,
        'city'   => Supplier::COL_ORT,
        'plz'    => Supplier::COL_PLZ,
    ];

    private function browseQuery(Request $request): Builder
    {
        $query = Supplier::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $like = "%{$search}%";
            $query->where(fn (Builder $q) => $q
                ->where(Supplier::COL_ID, $search)
                ->orWhere(Supplier::COL_NAME, 'like', $like)
                ->orWhere(Supplier::COL_EMAIL, 'like', $like)
                ->orWhere(Supplier::COL_STRASSE, 'like', $like)
                ->orWhere(Supplier::COL_ORT, 'like', $like)
                ->orWhere(Supplier::COL_PLZ, 'like', $like));
        }

        $sort = (string) $request->query('sort');
        $query->orderBy(self::SORTABLE[$sort] ?? Supplier::COL_ID, $this->sortDirection($request));

        return $query;
    }

    private function formatRow(Supplier $supplier): array
    {
        return [
            'id'     => $supplier->{Supplier::COL_ID},
            'name'   => $supplier->{Supplier::COL_NAME},
            'email'  => $supplier->{Supplier::COL_EMAIL},
            'street' => $supplier->{Supplier::COL_STRASSE},
            'city'   => $supplier->{Supplier::COL_ORT},
            'plz'    => $supplier->{Supplier::COL_PLZ},
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validation rule sets
    // ─────────────────────────────────────────────────────────────────────────

    private function storeRules(): array
    {
       return [
            Supplier::COL_NAME    => 'required|string|max:100',
            Supplier::COL_STRASSE => 'nullable|string|max:50',
            Supplier::COL_PLZ     => 'nullable|digits:5',
            Supplier::COL_ORT     => 'nullable|string|max:50',
            Supplier::COL_EMAIL   => 'nullable|email|max:50|unique:'.Supplier::TABLE .','. Supplier::COL_EMAIL,
        ];
    }

    private function updateRules(Supplier $supplier): array
    {
        return [
            Supplier::COL_NAME    => 'required|string|max:100',
            Supplier::COL_STRASSE => 'nullable|string|max:50',
            Supplier::COL_PLZ     => 'nullable|digits:5',
            Supplier::COL_ORT     => 'nullable|string|max:50',
            Supplier::COL_EMAIL   => [
                'nullable', 'email', 'max:50',
                Rule::unique(Supplier::TABLE, Supplier::COL_EMAIL)->ignore($supplier->{Supplier::COL_ID}, Supplier::COL_ID),
            ],
        ];
    }

    private function customMessages(): array
    {
        return [
            Supplier::COL_PLZ.'.digits'   => 'The postal code must be exactly 5 digits.',
            Supplier::COL_EMAIL.'.unique' => 'A supplier with this email address already exists.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formatting
    // ─────────────────────────────────────────────────────────────────────────

    private function formatSupplier(Supplier $supplier): array
    {
        return [
            Supplier::COL_ID      => $supplier->{Supplier::COL_ID},
            Supplier::COL_NAME    => $supplier->{Supplier::COL_NAME},
            Supplier::COL_STRASSE => $supplier->{Supplier::COL_STRASSE},
            Supplier::COL_PLZ     => $supplier->{Supplier::COL_PLZ},
            Supplier::COL_ORT     => $supplier->{Supplier::COL_ORT},
            Supplier::COL_EMAIL   => $supplier->{Supplier::COL_EMAIL},
        ];
    }
}