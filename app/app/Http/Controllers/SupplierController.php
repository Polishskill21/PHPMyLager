<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        if ($supplier->purchaseOrders()->exists()) {
            return $this->conflict(
                'This supplier cannot be deleted because they are attached to existing purchase orders.'
            );
        }

        try {
            DB::transaction(fn () => $supplier->delete());

            return $this->noContent();
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
            'name'    => 'required|string|max:100',
            'strasse' => 'nullable|string|max:50',
            'plz'     => 'nullable|digits:5',
            'ort'     => 'nullable|string|max:50',
            'email'   => 'nullable|email|max:50|unique:lieferanten,email',
            'telefon' => 'nullable|string|max:30',
        ];
    }

    private function updateRules(Supplier $supplier): array
    {
        return [
            'name'    => 'required|string|max:100',
            'strasse' => 'nullable|string|max:50',
            'plz'     => 'nullable|digits:5',
            'ort'     => 'nullable|string|max:50',
            'email'   => [
                'nullable', 'email', 'max:50',
                Rule::unique('lieferanten', 'email')->ignore($supplier->pLiefNr, 'pLiefNr'),
            ],
            'telefon' => 'nullable|string|max:30',
        ];
    }

    private function customMessages(): array
    {
        return [
            'plz.digits'   => 'The postal code must be exactly 5 digits.',
            'email.unique' => 'A supplier with this email address already exists.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formatting
    // ─────────────────────────────────────────────────────────────────────────

    private function formatSupplier(Supplier $supplier): array
    {
        return [
            'pLiefNr' => $supplier->pLiefNr,
            'name'    => $supplier->name,
            'strasse' => $supplier->strasse,
            'plz'     => $supplier->plz,
            'ort'     => $supplier->ort,
            'email'   => $supplier->email,
            'telefon' => $supplier->telefon,
        ];
    }
}