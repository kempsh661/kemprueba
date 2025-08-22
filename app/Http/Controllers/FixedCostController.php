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
        $month = $request->query('month'); // YYYY-MM
        $fixedCosts = FixedCost::where('user_id', $userId)->get();

        if ($month) {
            // Mezclar con estado por período si existe; por defecto is_paid = false en vista mensual
            $periods = \App\Models\FixedCostPeriod::where('user_id', $userId)
                ->where('month', $month)
                ->get()
                ->keyBy('fixed_cost_id');

            $fixedCosts = $fixedCosts->map(function ($cost) use ($periods) {
                if (isset($periods[$cost->id])) {
                    $period = $periods[$cost->id];
                    $cost->isActive = $period->is_active;
                    $cost->isPaid = $period->is_paid;
                    $cost->partialAmount = $period->partial_amount;
                    $cost->paidAmount = $period->paid_amount;
                    $cost->hasPartialPayment = !empty($period->partial_amount);
                    $cost->paymentNotes = $period->notes;
                } else {
                    // En contexto mensual, si no hay periodo, considerar no pagado por defecto
                    if ($cost->frequency === 'MONTHLY') {
                        $cost->isActive = true; // Por defecto activo
                        $cost->isPaid = false;  // Por defecto no pagado
                        $cost->partialAmount = null;
                        $cost->paidAmount = null;
                        $cost->hasPartialPayment = false;
                        $cost->paymentNotes = null;
                    }
                }
                return $cost;
            });
        }
        
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
            'dueDate', 'category', 'isActive', 'isPaid', 'month'
        ]);
        
        // Mapear campos del frontend a la base de datos
        $data['is_active'] = $data['isActive'] ?? true;
        $data['is_paid'] = $data['isPaid'] ?? false;
        $data['due_date'] = $data['dueDate'] ?? null;
        
        // Limpiar campos que no existen en la base de datos
        unset($data['isActive'], $data['isPaid'], $data['dueDate']);
        
        // Si se proporciona un mes, actualizar/crear estado del periodo
        if (!empty($request->month)) {
            $month = $request->month; // YYYY-MM
            $period = \App\Models\FixedCostPeriod::firstOrNew([
                'user_id' => $userId,
                'fixed_cost_id' => $fixedCost->id,
                'month' => $month,
            ]);
            if (array_key_exists('is_active', $data)) {
                $period->is_active = (bool) $data['is_active'];
            }
            if (array_key_exists('is_paid', $data)) {
                $period->is_paid = (bool) $data['is_paid'];
            }
            $period->save();
            // Actualizar solo metadatos del costo si vienen (nombre, monto, etc.)
            $fixedCost->update(array_diff_key($data, ['is_active' => true, 'is_paid' => true, 'due_date' => true, 'month' => true]));
            return $fixedCost;
        }

        $fixedCost->update($data);
        return $fixedCost;
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $month = $request->query('month');
        $fixedCost = FixedCost::where('user_id', $userId)->findOrFail($id);
        if ($month) {
            // Solo para este mes: marcar periodo inactivo
            $period = \App\Models\FixedCostPeriod::firstOrCreate([
                'user_id' => $userId,
                'fixed_cost_id' => $fixedCost->id,
                'month' => $month,
            ]);
            $period->update(['is_active' => false]);
            return response()->json(['success' => true, 'scoped' => true]);
        }
        $fixedCost->delete();
        return response()->json(['success' => true]);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $month = $request->query('month'); // YYYY-MM

        $base = FixedCost::where('user_id', $userId);
        if ($month) {
            // Aplicar estados por período al calcular; por defecto no pagado si no hay periodo
            $periods = \App\Models\FixedCostPeriod::where('user_id', $userId)
                ->where('month', $month)
                ->get()
                ->keyBy('fixed_cost_id');
            $costs = $base->get()->map(function ($c) use ($periods) {
                if (isset($periods[$c->id])) {
                    $c->is_active = $periods[$c->id]->is_active;
                    $c->is_paid = $periods[$c->id]->is_paid;
                } else {
                    $c->is_paid = false;
                }
                return $c;
            });
        } else {
            $costs = $base->get();
        }

        $active = $costs->filter(function($cost) {
            return $cost->isActive ?? $cost->is_active;
        });
        $paid = $active->filter(function($cost) {
            return $cost->isPaid ?? $cost->is_paid;
        });
        $pending = $active->filter(function($cost) {
            return !($cost->isPaid ?? $cost->is_paid);
        });

        $stats = [
            'totalAmount' => (float) $active->sum('amount'),
            'paidAmount' => (float) $paid->sum('amount'),
            'pendingAmount' => (float) $pending->sum('amount'),
            'totalCosts' => $active->count(),
            'paidCosts' => $paid->count(),
            'pendingCosts' => $pending->count(),
            'categories' => $active->whereNotNull('category')
                ->groupBy('category')
                ->map(function ($group, $category) {
                    return [
                        'category' => $category,
                        'total' => (float) $group->sum('amount'),
                    ];
                })->values(),
        ];

        return response()->json(['data' => $stats]);
    }

    public function missing(Request $request)
    {
        $userId = $request->user()->id;
        $month = $request->query('month'); // YYYY-MM
        if (!$month) {
            return response()->json(['data' => []]);
        }

        $monthlyCosts = FixedCost::where('user_id', $userId)
            ->where('frequency', 'MONTHLY')
            ->get();
        $periods = \App\Models\FixedCostPeriod::where('user_id', $userId)
            ->where('month', $month)
            ->pluck('fixed_cost_id')
            ->toArray();

        $missing = $monthlyCosts->filter(function ($c) use ($periods) {
            return !in_array($c->id, $periods);
        })->values()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'amount' => (float) $c->amount,
                'category' => $c->category,
            ];
        });

        return response()->json(['data' => $missing]);
    }

    public function togglePayment(Request $request, $id)
    {
        $userId = $request->user()->id;
        $fixedCost = FixedCost::where('user_id', $userId)->findOrFail($id);
        $month = $request->query('month');

        if ($month) {
            // Alternar solo para el mes indicado
            $period = \App\Models\FixedCostPeriod::firstOrCreate([
                'user_id' => $userId,
                'fixed_cost_id' => $fixedCost->id,
                'month' => $month,
            ], [
                'is_active' => true,
                'is_paid' => false,
            ]);
            $period->is_paid = !$period->is_paid;
            $period->save();

            return response()->json([
                'success' => true,
                'is_paid' => $period->is_paid,
                'scoped' => true,
            ]);
        }

        // Alternar globalmente
        $fixedCost->update([
            'is_paid' => !$fixedCost->is_paid
        ]);

        return response()->json([
            'success' => true,
            'is_paid' => $fixedCost->is_paid
        ]);
    }
}
