<?php

namespace App\Http\Controllers\Suppliers;

use App\Models\Suppliers\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;

class SupplierController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $suppliers = Supplier::all()->map(fn (Supplier $s) => $this->formatSupplier($s));
        return $this->ok($suppliers);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return $this->ok($this->formatSupplier($supplier));
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
            $id = $supplier->{Supplier::COL_ID};
            DB::transaction(fn () => $supplier->delete());
 
            return $this->ok(null, "Suppler {$id} deleted successfully.");
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
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