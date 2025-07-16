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
        $now = now(); // Ahora usa la zona horaria de Colombia configurada en app.php
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfWeek = $now->copy()->startOfWeek();
        $today = $now->copy()->startOfDay();

        // Ventas del mes
        $monthlySales = Sale::where('user_id', $userId)
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total');
        // Ventas de la semana
        $weeklySales = Sale::where('user_id', $userId)
            ->where('created_at', '>=', $startOfWeek)
            ->sum('total');
        // Ventas de hoy
        $todaySales = Sale::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->sum('total');
        // Costos fijos del mes
        $monthlyFixedCosts = FixedCost::where('user_id', $userId)
            ->where('is_active', true)
            ->where('frequency', 'MONTHLY')
            ->sum('amount');
        // Compras del mes
        $monthlyPurchases = Purchase::where('user_id', $userId)
            ->where('date', '>=', $startOfMonth)
            ->sum('amount');
        // Ganancia o pérdida
        $profitLoss = $monthlySales - $monthlyPurchases;
        return response()->json([
            'success' => true,
            'data' => [
                'monthlySales' => $monthlySales,
                'weeklySales' => $weeklySales,
                'todaySales' => $todaySales,
                'monthlyPurchases' => $monthlyPurchases,
                'monthlyFixedCosts' => $monthlyFixedCosts,
                'profitLoss' => $profitLoss
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
