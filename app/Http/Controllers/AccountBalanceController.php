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
            
        // Si no hay balance abierto de hoy, buscar el más reciente de cualquier fecha
        if (!$latest) {
            $latest = AccountBalance::where('user_id', $userId)
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();
        }
            
        // Debug: mostrar información de la caja (comentado para producción)
        // \Log::info('=== DEBUG CAJA ===');
        // \Log::info('Usuario ID: ' . $userId);
        // \Log::info('Fecha actual: ' . now()->toDateString());
        // \Log::info('Balance más reciente encontrado: ' . ($latest ? 'SÍ' : 'NO'));
        
        // if ($latest) {
        //     \Log::info('Fecha del balance: ' . $latest->date);
        //     \Log::info('Is closed: ' . ($latest->is_closed ? 'SÍ' : 'NO'));
        //     \Log::info('Total balance: ' . $latest->total_balance);
        // }
            
        // Obtener estadísticas de ventas de hoy (usando la fecha actual en zona horaria de Colombia)
        $today = now()->startOfDay();
        
        $todaySales = \App\Models\Sale::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->get();
            
        // Calcular totales por método de pago
        $cashTotal = $todaySales->where('payment_method', 'CASH')->sum('total');
        $cardTotal = $todaySales->where('payment_method', 'CARD')->sum('total');
        $transferTotal = $todaySales->where('payment_method', 'TRANSFER')->sum('total');
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
        
        // Si no hay registros de balance, devolver un balance vacío con estadísticas
        if (!$latest) {
            // \Log::info('No hay balance - caja cerrada');
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
                    'isOpen' => false, // Caja cerrada si no hay registros
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }
        
        // CORRECCIÓN: Calcular balance de apertura basado en la sesión anterior, no en fechas anteriores
        // Buscar el balance más reciente que esté cerrado (sesión anterior)
        $previousSession = AccountBalance::where('user_id', $userId)
            ->where('is_closed', true)
            ->orderBy('created_at', 'desc')
            ->first();
            
        // Si no hay sesión anterior cerrada, usar 0 como balance de apertura
        $openingBalance = $previousSession ? $previousSession->total_balance : 0;
        
        // Calcular balance de cierre (balance actual)
        $closingBalance = $latest->total_balance;
        
        // Determinar si la caja está abierta: si hay un balance de hoy que no esté cerrado
        $isOpen = $latest && $latest->date === $todayString && !($latest->is_closed ?? false);
        
        // \Log::info('Fecha del balance coincide con hoy: ' . ($latest->date === now()->toDateString() ? 'SÍ' : 'NO'));
        // \Log::info('Caja cerrada en BD: ' . ($latest->is_closed ?? false ? 'SÍ' : 'NO'));
        // \Log::info('Resultado final - Caja abierta: ' . ($isOpen ? 'SÍ' : 'NO'));
        // \Log::info('=== FIN DEBUG CAJA ===');
        
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
        
        // Buscar el balance más reciente de hoy que NO esté cerrado
        $latest = AccountBalance::where('user_id', $userId)
            ->where('date', now('America/Bogota')->toDateString())
            ->where('is_closed', false)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if (!$latest) {
            return response()->json([
                'success' => false,
                'message' => 'No hay caja abierta para cerrar'
            ], 400);
        }
        
        // Marcar la caja como cerrada
        $latest->update([
            'is_closed' => true,
            'notes' => $request->notes || 'Cierre de caja'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Caja cerrada exitosamente',
            'data' => [
                'id' => $latest->id,
                'closingBalance' => $latest->total_balance,
                'closedAt' => $latest->updated_at
            ]
        ]);
    }
    
    // Método para obtener historial de sesiones de caja
    public function getCashHistory(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $limit = $request->get('limit', 10);
        
        // Obtener las últimas sesiones de caja (abiertas y cerradas)
        $sessions = AccountBalance::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'date' => $session->date,
                    'openingBalance' => $session->cash_balance,
                    'totalBalance' => $session->total_balance,
                    'isClosed' => $session->is_closed,
                    'notes' => $session->notes,
                    'openedAt' => $session->created_at,
                    'closedAt' => $session->is_closed ? $session->updated_at : null,
                    'type' => $session->type
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => $sessions
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
                        'sale_date' => $sale->sale_date ? $sale->sale_date->format('Y-m-d') : null,
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
            'all_recent_balances' => $allBalances
        ];
        
        return response()->json([
            'success' => true,
            'data' => $debugInfo
        ]);
    }

    // Obtener balance mensual
    public function monthly(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $month = $request->query('month');
        

        
        if (!$month) {
            return response()->json([
                'success' => false,
                'message' => 'El parámetro month es requerido'
            ], 400);
        }

        // Parsear el mes (formato: YYYY-MM) usando Carbon
        $startOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        


        // Obtener ventas del mes
        $monthlySales = Sale::where('user_id', $userId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();



        // Calcular totales por método de pago
        $cashTotal = $monthlySales->where('payment_method', 'CASH')->sum('total');
        $cardTotal = $monthlySales->where('payment_method', 'CARD')->sum('total');
        $transferTotal = $monthlySales->where('payment_method', 'TRANSFER')->sum('total');
        $totalSales = $monthlySales->sum('total');

        // Obtener compras del mes
        $monthlyPurchases = Purchase::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        // Obtener costos fijos del mes
        $monthlyFixedCosts = FixedCost::where('user_id', $userId)
            ->where('is_active', true)
            ->where('is_paid', true)
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $totalExpenses = $monthlyPurchases + $monthlyFixedCosts;
        $netProfit = $totalSales - $totalExpenses;

        // Obtener estadísticas de productos
        $totalProducts = \App\Models\Product::where('user_id', $userId)->count();
        $totalBeverages = \App\Models\Ingredient::where('user_id', $userId)->count();
        
        // Productos con stock bajo
        $lowStockProducts = \App\Models\Product::where('user_id', $userId)
            ->where('stock', '<=', 10)
            ->count();
        $lowStockBeverages = \App\Models\Ingredient::where('user_id', $userId)
            ->where('stock', '<=', 10)
            ->count();
        $lowStockItems = $lowStockProducts + $lowStockBeverages;

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'totalSales' => $totalSales,
                'totalPurchases' => $monthlyPurchases,
                'netProfit' => $netProfit,
                'totalProducts' => $totalProducts,
                'totalBeverages' => $totalBeverages,
                'lowStockItems' => $lowStockItems,
                'cashTotal' => $cashTotal,
                'cardTotal' => $cardTotal,
                'transferTotal' => $transferTotal,
                'expenses' => $totalExpenses,
                'startDate' => $startOfMonth->toDateString(),
                'endDate' => $endOfMonth->toDateString()
            ]
        ]);
    }
}
