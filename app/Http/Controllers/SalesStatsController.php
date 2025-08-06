<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\FixedCost;
use Illuminate\Support\Facades\Auth;

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

        // Ventas del mes seleccionado
        $monthlySales = Sale::where('user_id', $userId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total');
            
        // Ventas de la semana (siempre de la semana actual)
        $weeklySales = Sale::where('user_id', $userId)
            ->where('created_at', '>=', now()->copy()->startOfWeek())
            ->sum('total');
            
        // Ventas de hoy (siempre de hoy)
        $todaySales = Sale::where('user_id', $userId)
            ->whereDate('created_at', now()->toDateString())
            ->sum('total');
            
        // Costos fijos del mes seleccionado
        $monthlyFixedCosts = FixedCost::where('user_id', $userId)
            ->where('is_active', true)
            ->where('frequency', 'MONTHLY')
            ->whereBetween('updated_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');
            
        // Compras del mes seleccionado
        $monthlyPurchases = Purchase::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');
            
        // Ganancia o pérdida
        $profitLoss = $monthlySales - $monthlyPurchases - $monthlyFixedCosts;
        
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
            ]
        ]);
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
