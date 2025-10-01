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

        // Ventas del mes seleccionado - SOLO DINERO REALMENTE RECIBIDO
        // Incluye: ventas completas pagadas + cantidad pagada de ventas a crédito
        $monthlySales = Sale::where('user_id', $userId)
            ->where(function($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('sale_date', [$startOfMonth, $endOfMonth])
                      ->orWhere(function($subQuery) use ($startOfMonth, $endOfMonth) {
                          $subQuery->whereNull('sale_date')
                                   ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                      });
            })
            ->selectRaw('SUM(total - COALESCE(remaining_balance, 0)) as paid_amount')
            ->value('paid_amount');
            
        // Ventas de la semana (siempre de la semana actual) - SOLO DINERO REALMENTE RECIBIDO
        $weeklySales = Sale::where('user_id', $userId)
            ->where(function($query) {
                $query->where('sale_date', '>=', now()->copy()->startOfWeek())
                      ->orWhere(function($subQuery) {
                          $subQuery->whereNull('sale_date')
                                   ->where('created_at', '>=', now()->copy()->startOfWeek());
                      });
            })
            ->selectRaw('SUM(total - COALESCE(remaining_balance, 0)) as paid_amount')
            ->value('paid_amount');
            
        // Ventas de hoy (siempre de hoy) - SOLO DINERO REALMENTE RECIBIDO
        $todaySales = Sale::where('user_id', $userId)
            ->where(function($query) {
                $query->whereDate('sale_date', now()->toDateString())
                      ->orWhere(function($subQuery) {
                          $subQuery->whereNull('sale_date')
                                   ->whereDate('created_at', now()->toDateString());
                      });
            })
            ->selectRaw('SUM(total - COALESCE(remaining_balance, 0)) as paid_amount')
            ->value('paid_amount');
            
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
            
        // Ganancia o pérdida - Basada en DINERO REALMENTE RECIBIDO
        // No incluye ventas a crédito pendientes de pago
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
