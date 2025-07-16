<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        return StockMovement::where('user_id', $userId)->get();
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->only(['product_id', 'type', 'quantity', 'description']);
        $data['user_id'] = $userId;
        $movement = StockMovement::create($data);
        return response()->json($movement, 201);
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $movement = StockMovement::where('user_id', $userId)->findOrFail($id);
        return $movement;
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $movement = StockMovement::where('user_id', $userId)->findOrFail($id);
        $movement->update($request->only(['product_id', 'type', 'quantity', 'description']));
        return $movement;
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $movement = StockMovement::where('user_id', $userId)->findOrFail($id);
        $movement->delete();
        return response()->json(['success' => true]);
    }

    public function productMovements(Request $request, $id)
    {
        $userId = $request->user()->id;
        $movements = \App\Models\StockMovement::where('user_id', $userId)
            ->where('product_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['data' => $movements]);
    }
}
