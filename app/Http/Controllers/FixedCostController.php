<?php

namespace App\Http\Controllers;

use App\Models\FixedCost;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FixedCostController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $fixedCosts = FixedCost::where('user_id', $userId)->get();
        
        return response()->json([
            'data' => $fixedCosts
        ]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->only([
            'name', 'amount', 'description', 'frequency', 
            'dueDate', 'category', 'isActive', 'isPaid'
        ]);
        
        // Mapear campos del frontend a la base de datos
        $data['user_id'] = $userId;
        $data['is_active'] = $data['isActive'] ?? true;
        $data['is_paid'] = $data['isPaid'] ?? false;
        $data['due_date'] = $data['dueDate'] ?? null;
        
        // Limpiar campos que no existen en la base de datos
        unset($data['isActive'], $data['isPaid'], $data['dueDate']);
        
        $fixedCost = FixedCost::create($data);
        return response()->json($fixedCost, 201);
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $fixedCost = FixedCost::where('user_id', $userId)->findOrFail($id);
        return $fixedCost;
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $fixedCost = FixedCost::where('user_id', $userId)->findOrFail($id);
        
        $data = $request->only([
            'name', 'amount', 'description', 'frequency', 
            'dueDate', 'category', 'isActive', 'isPaid'
        ]);
        
        // Mapear campos del frontend a la base de datos
        $data['is_active'] = $data['isActive'] ?? true;
        $data['is_paid'] = $data['isPaid'] ?? false;
        $data['due_date'] = $data['dueDate'] ?? null;
        
        // Limpiar campos que no existen en la base de datos
        unset($data['isActive'], $data['isPaid'], $data['dueDate']);
        
        $fixedCost->update($data);
        return $fixedCost;
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $fixedCost = FixedCost::where('user_id', $userId)->findOrFail($id);
        $fixedCost->delete();
        return response()->json(['success' => true]);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        
        $stats = [
            'totalAmount' => FixedCost::where('user_id', $userId)
                ->where('is_active', true)
                ->sum('amount'),
            'paidAmount' => FixedCost::where('user_id', $userId)
                ->where('is_active', true)
                ->where('is_paid', true)
                ->sum('amount'),
            'pendingAmount' => FixedCost::where('user_id', $userId)
                ->where('is_active', true)
                ->where('is_paid', false)
                ->sum('amount'),
            'totalCosts' => FixedCost::where('user_id', $userId)
                ->where('is_active', true)
                ->count(),
            'paidCosts' => FixedCost::where('user_id', $userId)
                ->where('is_active', true)
                ->where('is_paid', true)
                ->count(),
            'pendingCosts' => FixedCost::where('user_id', $userId)
                ->where('is_active', true)
                ->where('is_paid', false)
                ->count(),
            'categories' => FixedCost::where('user_id', $userId)
                ->where('is_active', true)
                ->whereNotNull('category')
                ->selectRaw('category, SUM(amount) as total')
                ->groupBy('category')
                ->get()
                ->map(function ($item) {
                    return [
                        'category' => $item->category,
                        'total' => (float) $item->total
                    ];
                })
        ];

        return response()->json(['data' => $stats]);
    }

    public function togglePayment(Request $request, $id)
    {
        $userId = $request->user()->id;
        $fixedCost = FixedCost::where('user_id', $userId)->findOrFail($id);
        
        $fixedCost->update([
            'is_paid' => !$fixedCost->is_paid
        ]);
        
        return response()->json([
            'success' => true,
            'is_paid' => $fixedCost->is_paid
        ]);
    }
}
