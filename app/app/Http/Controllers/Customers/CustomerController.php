<?php

namespace App\Http\Controllers\Customers;

use App\Models\Customers\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Support\DomainCache;

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
        $customers = DomainCache::remember(
            DomainCache::CUSTOMERS,
            'customers:index',
            fn () => Customer::all()->map(fn (Customer $c) => $this->formatCustomer($c))
        );

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
            DomainCache::flush(DomainCache::CUSTOMERS);

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
            DomainCache::flush(DomainCache::CUSTOMERS);

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
            $id = $customer->{Customer::COL_ID};
            DB::transaction(fn () => $customer->delete());
            DomainCache::flush(DomainCache::CUSTOMERS);

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
            Customer::COL_NAME    => 'required|string|max:50',
            Customer::COL_STRASSE => 'required|string|max:50',
            Customer::COL_PLZ     => 'required|digits:5',
            Customer::COL_ORT     => 'required|string|max:50',
            Customer::COL_EMAIL   => 'required|email|max:50|unique:' . Customer::TABLE . ',' . Customer::COL_EMAIL,
        ];
    }

    private function updateRules(Customer $customer): array
    {
        return [
            Customer::COL_NAME    => 'required|string|max:50',
            Customer::COL_STRASSE => 'required|string|max:50',
            Customer::COL_PLZ     => 'required|digits:5',
            Customer::COL_ORT     => 'required|string|max:50',
            Customer::COL_EMAIL   => ['required', 'email', 'max:50',
            Rule::unique(Customer::TABLE, Customer::COL_EMAIL)->ignore($customer->{Customer::COL_ID}, Customer::COL_ID),
            ],
        ];
    }

    private function customMessages(): array
    {
        return [
            Customer::COL_PLZ.'.digits'   => 'The postal code must be exactly 5 digits.',
            Customer::COL_EMAIL.'.unique' => 'A customer with this email address already exists.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formatting
    // ─────────────────────────────────────────────────────────────────────────

    private function formatCustomer(Customer $customer): array
    {
        return [
            Customer::COL_ID      => $customer->{Customer::COL_ID},
            Customer::COL_NAME    => $customer->{Customer::COL_NAME},
            Customer::COL_STRASSE => $customer->{Customer::COL_STRASSE},
            Customer::COL_PLZ     => $customer->{Customer::COL_PLZ},
            Customer::COL_ORT     => $customer->{Customer::COL_ORT},
            Customer::COL_EMAIL   => $customer->{Customer::COL_EMAIL},
        ];
    }
}