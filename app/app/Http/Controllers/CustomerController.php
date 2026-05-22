<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /customers
     */
    public function index(): JsonResponse
    {
        $customers = Customer::all()->map(fn (Customer $c) => $this->formatCustomer($c));

        return $this->ok($customers);
    }

    /**
     * GET /customers/{customer}
     */
    public function show(Customer $customer): JsonResponse
    {
        return $this->ok($this->formatCustomer($customer));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /customers
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->storeRules(),
            $this->customMessages()
        );

        try {
            $customer = DB::transaction(fn () => Customer::create($validated));

            return $this->created($this->formatCustomer($customer->fresh()), 'Customer created successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PUT /customers/{customer}
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate(
            $this->updateRules($customer),
            $this->customMessages()
        );

        try {
            $customer = DB::transaction(function () use ($validated, $customer): Customer {
                $customer->update($validated);
                return $customer->fresh();
            });

            return $this->ok($this->formatCustomer($customer), 'Customer updated successfully.');
        } catch (\Exception $e) {
            report($e);
            return $this->serverError();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * DELETE /customers/{customer}
     * Soft-deletes the customer record.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $id = $customer->pKdNr;
            DB::transaction(fn () => $customer->delete());

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
            'name'    => 'required|string|max:50',
            'strasse' => 'required|string|max:50',
            'plz'     => 'required|digits:5',
            'ort'     => 'required|string|max:50',
            'email'   => 'required|email|max:50|unique:kunden,email',
        ];
    }

    private function updateRules(Customer $customer): array
    {
        return [
            'name'    => 'required|string|max:50',
            'strasse' => 'required|string|max:50',
            'plz'     => 'required|digits:5',
            'ort'     => 'required|string|max:50',
            // Ignore the current customer's email so they can keep their own
            'email'   => [
                'required', 'email', 'max:50',
                Rule::unique('kunden', 'email')->ignore($customer->pKdNr, 'pKdNr'),
            ],
        ];
    }

    private function customMessages(): array
    {
        return [
            'plz.digits'    => 'The postal code must be exactly 5 digits.',
            'email.unique'  => 'A customer with this email address already exists.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formatting
    // ─────────────────────────────────────────────────────────────────────────

    private function formatCustomer(Customer $customer): array
    {
        return [
            'pKdNr'   => $customer->pKdNr,
            'name'    => $customer->name,
            'strasse' => $customer->strasse,
            'plz'     => $customer->plz,
            'ort'     => $customer->ort,
            'email'   => $customer->email,
        ];
    }
}