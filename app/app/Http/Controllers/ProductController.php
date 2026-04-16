<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(['products' => Product::all()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->canWrite()) { 
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'bezeichnung' => 'required|string|max:35',
            'fWgNr'       => 'required|integer|exists:warengruppe,pWgNr',
            'ekPreis'     => 'required|numeric|min:0|max:999999.99',
            'vkPreis'     => 'required|numeric|min:0|max:999999.99',
            'bestand'     => 'required|integer|min:0',
            'meldeBest'   => 'required|integer|min:0',
        ]);

        $product = Product::create($validated);
        return response()->json(['data' => $product, 'message' => 'Product created successfully'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json(['productsById' => $product]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->canWrite()) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        // 'sometimes' means it only checks these rules if the field is included in the request
        $validated = $request->validate([
            'bezeichnung' => 'sometimes|required|string|max:35',
            'fWgNr'       => 'sometimes|integer|exists:warengruppe,pWgNr',
            'ekPreis'     => 'sometimes|required|numeric|min:0|max:999999.99',
            'vkPreis'     => 'sometimes|required|numeric|min:0|max:999999.99',
            'bestand'     => 'sometimes|required|integer|min:0',
            'meldeBest'   => 'sometimes|required|integer|min:0',
        ]);

        $product->update($validated);
        return response()->json(['data' => $product, 'message' => 'Product updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->canDelete()) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        try {
            $id = $product->pArtikelNr;
            $product->delete();
            return response()->json(['message' => "Product ID: {$id} deleted successfully"]);
        } catch (QueryException $e) {
            if ($e->getCode() == '23000') {
                return response()->json(['error' => 'This product cannot be deleted because it is used in one or more orders.'], 409);
            }
            return response()->json(['error' => 'An error occurred while deleting the product.'], 500);
        }
    }
}