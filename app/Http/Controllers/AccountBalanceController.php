<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccountBalance;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\FixedCost;
use Illuminate\Support\Facades\Auth;

class AccountBalanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id ?? 1; // Temporal: userId 1 si no hay auth
        $limit = $request->query('limit', 50);
        $offset = $request->query('offset', 0);
        $balances = AccountBalance::where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();
        $total = AccountBalance::where('user_id', $userId)->count();
        
        // Transformar los datos para que coincidan con el formato esperado por el frontend
        $transformedBalances = $balances->map(function ($balance) {
            return [
                'id' => $balance->id,
                'date' => $balance->date,
                'bankBalance' => (float) $balance->bank_balance,
                'nequiAleja' => (float) $balance->nequi_aleja,
                'nequiKem' => (float) $balance->nequi_kem,
                'cashBalance' => (float) $balance->cash_balance,
                'totalBalance' => (float) $balance->total_balance,
                'notes' => $balance->notes,
                'type' => $balance->type,
                'created_at' => $balance->created_at,
                'updated_at' => $balance->updated_at
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $transformedBalances,
            'pagination' => [
                'total' => $total,
                'limit' => (int)$limit,
                'offset' => (int)$offset
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $userId = $request->user()->id ?? 1; // Temporal: userId 1 si no hay auth
        $todayString = now('America/Bogota')->toDateString();
        // Verificar si ya existe una apertura de caja para hoy que no esté cerrada
        $existing = AccountBalance::where('user_id', $userId)
            ->where('date', $todayString)
            ->where('is_closed', false)
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe una apertura de caja para hoy que no está cerrada.',
                'data' => [
                    'id' => $existing->id,
                    'date' => $existing->date,
                    'bankBalance' => (float) $existing->bank_balance,
                    'nequiAleja' => (float) $existing->nequi_aleja,
                    'nequiKem' => (float) $existing->nequi_kem,
                    'cashBalance' => (float) $existing->cash_balance,
                    'totalBalance' => (float) $existing->total_balance,
                    'notes' => $existing->notes,
                    'created_at' => $existing->created_at,
                    'updated_at' => $existing->updated_at
                ]
            ], 409);
        }
        $data = $request->only(['bank_balance', 'nequi_aleja', 'nequi_kem', 'cash_balance', 'notes']);
        $data['user_id'] = $userId;
        $data['bank_balance'] = (float)($data['bank_balance'] ?? 0);
        $data['nequi_aleja'] = (float)($data['nequi_aleja'] ?? 0);
        $data['nequi_kem'] = (float)($data['nequi_kem'] ?? 0);
        $data['cash_balance'] = (float)($data['cash_balance'] ?? 0);
        $data['total_balance'] = $data['bank_balance'] + $data['nequi_aleja'] + $data['nequi_kem'] + $data['cash_balance'];
        $data['date'] = $todayString; // Usar siempre la fecha de hoy en zona horaria Colombia
        $data['is_closed'] = false; // Marcar como no cerrada
        $data['type'] = 'manual'; // Guardar como balance manual
        $balance = AccountBalance::create($data);
        $transformedBalance = [
            'id' => $balance->id,
            'date' => $balance->date,
            'bankBalance' => (float) $balance->bank_balance,
            'nequiAleja' => (float) $balance->nequi_aleja,
            'nequiKem' => (float) $balance->nequi_kem,
            'cashBalance' => (float) $balance->cash_balance,
            'totalBalance' => (float) $balance->total_balance,
            'notes' => $balance->notes,
            'created_at' => $balance->created_at,
            'updated_at' => $balance->updated_at
        ];
        return response()->json([
            'success' => true,
            'data' => $transformedBalance
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // Obtener el balance más reciente
    public function latest(Request $request)
    {
        $userId = $request->user()->id ?? 1; // Temporal: userId 1 si no hay auth
        $todayString = now('America/Bogota')->toDateString();
        
        // Buscar el balance más reciente de hoy que NO esté cerrado
        $latest = AccountBalance::where('user_id', $userId)
            ->where('date', $todayString)
            ->where('is_closed', false)
            ->orderBy('created_at', 'desc')
            ->first();
            
        // Obtener estadísticas de ventas de hoy
        $today = now()->startOfDay();
        
        $todaySales = \App\Models\Sale::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->get();
            
        // Calcular totales por método de pago (usando minúsculas como en la BD)
        $cashTotal = $todaySales->where('payment_method', 'cash')->sum('total');
        $cardTotal = $todaySales->where('payment_method', 'card')->sum('total');
        $transferTotal = $todaySales->where('payment_method', 'transfer')->sum('total');
        $totalSales = $todaySales->sum('total');
        
        // Obtener gastos de hoy (compras + costos fijos)
        $todayPurchases = \App\Models\Purchase::where('user_id', $userId)
            ->whereDate('date', $today)
            ->sum('amount');
            
        $todayFixedCosts = \App\Models\FixedCost::where('user_id', $userId)
            ->where('is_active', true)
            ->where('is_paid', true)
            ->whereDate('updated_at', $today)
            ->sum('amount');
            
        $expenses = $todayPurchases + $todayFixedCosts;
        
        // Si no hay registros de balance abierto de hoy, devolver caja cerrada
        if (!$latest) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => null,
                    'user_id' => $userId,
                    'date' => now()->toDateString(),
                    'openingBalance' => 0,
                    'closingBalance' => null,
                    'cashTotal' => $cashTotal,
                    'cardTotal' => $cardTotal,
                    'transferTotal' => $transferTotal,
                    'totalSales' => $totalSales,
                    'expenses' => $expenses,
                    'profit' => $totalSales - $expenses,
                    'isOpen' => false, // Caja cerrada si no hay registros abiertos de hoy
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }
        
        // Si hay balance abierto de hoy, usar el cash_balance como saldo inicial
        $openingBalance = $latest->cash_balance; // El saldo inicial es el cash_balance que se ingresó al abrir
        
        // Calcular balance de cierre: saldo inicial + ventas en efectivo - gastos
        $closingBalance = $openingBalance + $cashTotal - $expenses;
        
        // Determinar si la caja está abierta: si hay un balance de hoy que no esté cerrado
        $isOpen = $latest && $latest->date === $todayString && !($latest->is_closed ?? false);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $latest->id,
                'user_id' => $latest->user_id,
                'date' => $latest->date,
                'openingBalance' => $openingBalance,
                'closingBalance' => $closingBalance,
                'cashTotal' => $cashTotal,
                'cardTotal' => $cardTotal,
                'transferTotal' => $transferTotal,
                'totalSales' => $totalSales,
                'expenses' => $expenses,
                'profit' => $totalSales - $expenses,
                'isOpen' => $isOpen,
                'created_at' => $latest->created_at,
                'updated_at' => $latest->updated_at
            ]
        ]);
    }
    
    // Método para cerrar la caja
    public function closeCash(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $todayString = now('America/Bogota')->toDateString();
        
        // Buscar el balance abierto de hoy
        $latest = AccountBalance::where('user_id', $userId)
            ->where('date', $todayString)
            ->where('is_closed', false)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if (!$latest) {
            return response()->json([
                'success' => false,
                'message' => 'No hay caja abierta para cerrar'
            ], 400);
        }
        
        // Obtener estadísticas de ventas de hoy
        $today = now()->startOfDay();
        
        $todaySales = \App\Models\Sale::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->get();
            
        // Calcular totales por método de pago (usando minúsculas como en la BD)
        $cashTotal = $todaySales->where('payment_method', 'cash')->sum('total');
        $cardTotal = $todaySales->where('payment_method', 'card')->sum('total');
        $transferTotal = $todaySales->where('payment_method', 'transfer')->sum('total');
        $totalSales = $todaySales->sum('total');
        
        // Obtener gastos de hoy
        $todayPurchases = \App\Models\Purchase::where('user_id', $userId)
            ->whereDate('date', $today)
            ->sum('amount');
            
        $todayFixedCosts = \App\Models\FixedCost::where('user_id', $userId)
            ->where('is_active', true)
            ->where('is_paid', true)
            ->whereDate('updated_at', $today)
            ->sum('amount');
            
        $expenses = $todayPurchases + $todayFixedCosts;
        
        // Calcular saldo inicial y final
        $openingBalance = $latest->cash_balance; // Saldo inicial ingresado al abrir
        $closingBalance = $openingBalance + $cashTotal - $expenses; // Saldo final calculado
        
        // Marcar la caja como cerrada y actualizar el saldo final
        $latest->update([
            'is_closed' => true,
            'total_balance' => $closingBalance, // Actualizar el saldo total con el cálculo final
            'notes' => $request->notes || 'Cierre de caja'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Caja cerrada exitosamente',
            'data' => [
                'openingBalance' => $openingBalance,
                'cashTotal' => $cashTotal,
                'cardTotal' => $cardTotal,
                'transferTotal' => $transferTotal,
                'totalSales' => $totalSales,
                'expenses' => $expenses,
                'closingBalance' => $closingBalance,
                'profit' => $totalSales - $expenses
            ]
        ]);
    }
    
    // Método de debug para verificar fechas del sistema
    public function debugDates(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        
        $debugInfo = [
            'current_time' => now()->format('Y-m-d H:i:s'),
            'current_date' => now()->toDateString(),
            'timezone' => config('app.timezone'),
            'today_start' => now()->startOfDay()->format('Y-m-d H:i:s'),
            'today_end' => now()->endOfDay()->format('Y-m-d H:i:s'),
            'recent_sales' => \App\Models\Sale::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($sale) {
                    return [
                        'id' => $sale->id,
                        'created_at' => $sale->created_at->format('Y-m-d H:i:s'),
                        'saleDate' => $sale->sale_date ? $sale->sale_date->format('Y-m-d') : null,
                        'total' => $sale->total
                    ];
                }),
            'today_sales_count' => \App\Models\Sale::where('user_id', $userId)
                ->whereDate('created_at', now()->toDateString())
                ->count()
        ];
        
        return response()->json([
            'success' => true,
            'data' => $debugInfo
        ]);
    }
    
    // Método de debug específico para la caja
    public function debugCash(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        
        $latest = AccountBalance::where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->first();
            
        $allBalances = AccountBalance::where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($balance) {
                return [
                    'id' => $balance->id,
                    'date' => $balance->date,
                    'total_balance' => $balance->total_balance,
                    'is_closed' => $balance->is_closed,
                    'notes' => $balance->notes,
                    'created_at' => $balance->created_at->format('Y-m-d H:i:s')
                ];
            });
            
        // Obtener ventas de hoy para debug
        $today = now()->startOfDay();
        $todaySales = \App\Models\Sale::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->get();
            
        $salesDebug = $todaySales->map(function ($sale) {
            return [
                'id' => $sale->id,
                'total' => $sale->total,
                'payment_method' => $sale->payment_method,
                'created_at' => $sale->created_at->format('Y-m-d H:i:s')
            ];
        });
            
        $debugInfo = [
            'current_date' => now()->toDateString(),
            'timezone' => config('app.timezone'),
            'user_id' => $userId,
            'latest_balance_exists' => $latest ? true : false,
            'latest_balance' => $latest ? [
                'id' => $latest->id,
                'date' => $latest->date,
                'total_balance' => $latest->total_balance,
                'is_closed' => $latest->is_closed,
                'notes' => $latest->notes
            ] : null,
            'is_open_calculation' => [
                'date_matches_today' => $latest ? ($latest->date === now()->toDateString()) : false,
                'is_closed_in_db' => $latest ? ($latest->is_closed ?? false) : false,
                'final_result' => $latest ? ($latest->date === now()->toDateString() && !($latest->is_closed ?? false)) : false
            ],
            'all_recent_balances' => $allBalances,
            'today_sales_count' => $todaySales->count(),
            'today_sales' => $salesDebug,
            'payment_methods_found' => $todaySales->pluck('payment_method')->unique()->values()
        ];
        
        return response()->json([
            'success' => true,
            'data' => $debugInfo
        ]);
    }
}
