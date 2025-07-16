<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        return Inventory::where('user_id', $userId)->get();
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->only(['product_id', 'quantity']);
        $data['user_id'] = $userId;
        $inventory = Inventory::create($data);
        return response()->json($inventory, 201);
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $inventory = Inventory::where('user_id', $userId)->findOrFail($id);
        return $inventory;
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $inventory = Inventory::where('user_id', $userId)->findOrFail($id);
        $inventory->update($request->only(['product_id', 'quantity']));
        return $inventory;
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $inventory = Inventory::where('user_id', $userId)->findOrFail($id);
        $inventory->delete();
        return response()->json(['success' => true]);
    }
}
