<?php

namespace App\Http\Controllers;

use App\Models\WarehouseGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseGroupController extends Controller
{
    /**
     * Get all warehouse groups.
     */
    public function index()
    {
        return response()->json([
            'warehouse_groups' => WarehouseGroup::all()
        ], 200);
    }

    /**
     * Retrieve a single warehouse group by its ID.
     */
    public function show($id)
    {
        $group = WarehouseGroup::findOrFail($id);
        
        return response()->json($group, 200);
    }

    /**
     * Create a new warehouse group safely inside a transaction.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warengruppe' => 'nullable|string|max:50',
        ]);

        $group = DB::transaction(function () use ($validated) {
            return WarehouseGroup::create($validated);
        });

        return response()->json([
            'message' => 'Warehouse group created successfully',
            'data'    => $group
        ], 201);
    }

    /**
     * Update an existing warehouse group safely inside a transaction.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'warengruppe' => 'nullable|string|max:50',
        ]);

        $group = DB::transaction(function () use ($id, $validated) {
            $groupToUpdate = WarehouseGroup::findOrFail($id);
            $groupToUpdate->update($validated);
            
            return $groupToUpdate;
        });

        return response()->json([
            'message' => 'Warehouse group updated successfully',
            'data'    => $group
        ], 200);
    }

    /**
     * Delete a warehouse group safely inside a transaction.
     */
    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $group = WarehouseGroup::findOrFail($id);
            $group->delete();
        });

        return response()->json([
            'message' => "Warehouse Group ID: {$id} deleted successfully"
        ], 200);
    }
}