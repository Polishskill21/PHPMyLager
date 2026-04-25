<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{

    // return customers
    public function index(): JsonResponse
    {
        $customers = Customer::with('orders')->get();

        return response()->json(
            $customers->map(fn (Customer $customer) => $this->formatCustomer($customer))
        );
    }

    // return customer
    public function show(Customer $customer): JsonResponse
    {
        $customer->load("orders");
        return response()->json($this->formatCustomer($customer));
    }

    // create customer
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'strasse' => 'required|string|max:255',
            'plz'     => 'required|digits:5',
            'ort'     => 'required|string|max:255',
            'email'   => 'required|email|max:255|unique:kunden,email',
        ]);

        $customer = DB::transaction(function () use ($validated): Customer {
            return Customer::create($validated);
        });

        return response()->json($this->formatCustomer($customer->fresh(['orders'])), 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'strasse' => 'required|string|max:255',
            'plz'     => 'required|digits:5',
            'ort'     => 'required|string|max:255',
            'email'   => ['required','email','max:255',Rule::unique('kunden','email')->ignore($customer->pKdNr,'pKdNr'),],
        ]);

        $customer = DB::transaction(function () use ($validated, $customer): Customer {
            $customer->update($validated);
            return $customer->fresh(['orders']);
        });

        return response()->json($this->formatCustomer($customer));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        DB::transaction(function () use ($customer): void {
            $customer->delete();
        });

        return response()->json(null, 204);
    }

    private function formatCustomer(Customer $customer): array
    {

        $orders = $customer->orders;

        return [
            'customer' => [
                'pKdNr'   => $customer->pKdNr,
                'name'    => $customer->name,
                'strasse' => $customer->strasse,
                'plz'     => $customer->plz,
                'ort'     => $customer->ort,
                'email' => $customer->email,
            ],
            'orders' => $orders->map(fn (Order $order) => [
                'pAufNr'    => $order->pAufNr,
                'aufDat'    => $order->aufDat,
                'aufTermin' => $order->aufTermin,
                'fKdNr'     => $order->fKdNr,
            ])->values(),
        ];
    }
}
