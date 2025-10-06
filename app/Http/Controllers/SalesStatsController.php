<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\FixedCost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SalesStatsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id ?? 1; // Temporal: userId 1 si no hay auth
        $month = $request->query('month');
        
        // Si no se proporciona un mes específico, usar el mes actual
        if (!$month) {
            $now = now(); // Ahora usa la zona horaria de Colombia configurada en app.php
            $startOfMonth = $now->copy()->startOfMonth();
            $endOfMonth = $now->copy()->endOfMonth();
            $startOfWeek = $now->copy()->startOfWeek();
            $today = $now->copy()->startOfDay();
        } else {
            // Parsear el mes proporcionado (formato: YYYY-MM)
            $startOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            $startOfWeek = $startOfMonth->copy()->startOfWeek();
            $today = now()->copy()->startOfDay(); // Para ventas de hoy siempre usar la fecha actual
        }

        // Generar claves de cache únicas
        $cacheKey = "sales_stats_{$userId}_{$month}_" . ($month ?: 'current');
        $cacheDuration = 900; // 15 minutos (más agresivo)

        // Intentar obtener datos del cache primero
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            return response()->json([
                'success' => true,
                'data' => $cachedData,
                'cached' => true
            ]);
        }

        // Si no hay cache, calcular los datos
        $stats = $this->calculateStats($userId, $startOfMonth, $endOfMonth, $startOfWeek, $today, $month);

        // Guardar en cache
        Cache::put($cacheKey, $stats, $cacheDuration);

        return response()->json([
            'success' => true,
            'data' => $stats,
            'cached' => false
        ]);
    }

    /**
     * Calcular estadísticas de ventas de forma optimizada
     */
    private function calculateStats($userId, $startOfMonth, $endOfMonth, $startOfWeek, $today, $month)
    {
        // Ventas del mes seleccionado - OPTIMIZADO solo con sale_date (más rápido)
        $monthlySales = Sale::where('user_id', $userId)
            ->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
            ->selectRaw('SUM(total - COALESCE(remaining_balance, 0)) as paid_amount')
            ->value('paid_amount') ?? 0;
            
        // Ventas de la semana (siempre de la semana actual) - OPTIMIZADO solo con sale_date
        $weeklySales = Sale::where('user_id', $userId)
            ->whereBetween('sale_date', [now()->copy()->startOfWeek(), now()->copy()->endOfWeek()])
            ->selectRaw('SUM(total - COALESCE(remaining_balance, 0)) as paid_amount')
            ->value('paid_amount') ?? 0;
            
        // Ventas de hoy (siempre de hoy) - OPTIMIZADO solo con sale_date
        $todaySales = Sale::where('user_id', $userId)
            ->whereDate('sale_date', now()->toDateString())
            ->selectRaw('SUM(total - COALESCE(remaining_balance, 0)) as paid_amount')
            ->value('paid_amount') ?? 0;
            
        // Costos fijos del mes seleccionado - OPTIMIZADO con índices
        $monthlyFixedCosts = FixedCost::where('user_id', $userId)
            ->where('is_active', true)
            ->where('frequency', 'MONTHLY')
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');
            
        // Compras del mes seleccionado - OPTIMIZADO con índices
        $monthlyPurchases = Purchase::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');
            
        // Ganancia o pérdida - Basada en DINERO REALMENTE RECIBIDO
        $profitLoss = $monthlySales - $monthlyPurchases - $monthlyFixedCosts;
        
        // Obtener estadísticas de productos - OPTIMIZADO con índices
        $totalProducts = \App\Models\Product::where('user_id', $userId)->count();
        $totalBeverages = \App\Models\Ingredient::where('user_id', $userId)->count();
        
        // Productos con stock bajo - OPTIMIZADO con índices
        $lowStockProducts = \App\Models\Product::where('user_id', $userId)
            ->where('stock', '<=', 10)
            ->count();
        $lowStockBeverages = \App\Models\Ingredient::where('user_id', $userId)
            ->where('stock', '<=', 10)
            ->count();
        $lowStockItems = $lowStockProducts + $lowStockBeverages;
        
        return [
            'monthlySales' => $monthlySales,
            'weeklySales' => $weeklySales,
            'todaySales' => $todaySales,
            'monthlyPurchases' => $monthlyPurchases,
            'monthlyFixedCosts' => $monthlyFixedCosts,
            'profitLoss' => $profitLoss,
            'selectedMonth' => $month,
            'startDate' => $startOfMonth->toDateString(),
            'endDate' => $endOfMonth->toDateString(),
            'totalProducts' => $totalProducts,
            'totalBeverages' => $totalBeverages,
            'lowStockItems' => $lowStockItems
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
}
