<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ANÁLISIS COMPLETO DE COMPRAS ===" . PHP_EOL . PHP_EOL;

try {
    $now = now('America/Bogota');
    $lastMonth = $now->copy()->subMonth();
    $startOfLastMonth = $lastMonth->copy()->startOfMonth();
    $endOfLastMonth = $lastMonth->copy()->endOfMonth();
    $startOfCurrentMonth = $now->copy()->startOfMonth();
    $endOfCurrentMonth = $now->copy()->endOfMonth();

    // Compras mes pasado
    $lastMonthPurchases = DB::table('purchases')
        ->where('user_id', 1)
        ->whereBetween('date', [$startOfLastMonth->toDateString(), $endOfLastMonth->toDateString()])
        ->get();
    
    // Compras mes actual
    $currentMonthPurchases = DB::table('purchases')
        ->where('user_id', 1)
        ->whereBetween('date', [$startOfCurrentMonth->toDateString(), $endOfCurrentMonth->toDateString()])
        ->get();
    
    echo "📊 RESUMEN COMPARATIVO" . PHP_EOL;
    echo "=====================" . PHP_EOL;
    
    $lastTotal = $lastMonthPurchases->sum('amount');
    $currentTotal = $currentMonthPurchases->sum('amount');
    $variation = $currentTotal - $lastTotal;
    $percentageChange = $lastTotal > 0 ? (($variation / $lastTotal) * 100) : 0;
    
    echo "MES PASADO (Julio 2025):" . PHP_EOL;
    echo "  Total gastado: $" . number_format($lastTotal, 2) . PHP_EOL;
    echo "  Número de compras: " . $lastMonthPurchases->count() . PHP_EOL;
    echo "  Promedio por compra: $" . number_format($lastMonthPurchases->avg('amount'), 2) . PHP_EOL . PHP_EOL;
    
    echo "MES ACTUAL (Agosto 2025):" . PHP_EOL;
    echo "  Total gastado: $" . number_format($currentTotal, 2) . PHP_EOL;
    echo "  Número de compras: " . $currentMonthPurchases->count() . PHP_EOL;
    echo "  Promedio por compra: $" . number_format($currentMonthPurchases->avg('amount'), 2) . PHP_EOL . PHP_EOL;
    
    echo "VARIACIÓN:" . PHP_EOL;
    echo "  Cambio en gasto: $" . number_format($variation, 2) . " (" . number_format($percentageChange, 1) . "%)" . PHP_EOL;
    echo "  Cambio en compras: " . ($currentMonthPurchases->count() - $lastMonthPurchases->count()) . PHP_EOL . PHP_EOL;
    
    // TOP productos mes pasado
    echo "🏆 TOP 10 PRODUCTOS MÁS COMPRADOS (MES PASADO)" . PHP_EOL;
    echo "===============================================" . PHP_EOL;
    
    $lastMonthByConcept = $lastMonthPurchases->groupBy('concept')->map(function($items, $concept) {
        return [
            'concept' => $concept ?: 'Sin concepto',
            'total' => $items->sum('amount'),
            'count' => $items->count(),
            'average' => $items->avg('amount'),
            'category' => $items->first()->category ?? 'Sin categoría'
        ];
    })->sortByDesc('total');
    
    $rank = 1;
    foreach ($lastMonthByConcept->take(10) as $concept => $data) {
        echo sprintf("%d. %s" . PHP_EOL, $rank, $data['concept']);
        echo sprintf("   Categoría: %s" . PHP_EOL, $data['category']);
        echo sprintf("   Gasto total: $%s" . PHP_EOL, number_format($data['total'], 2));
        echo sprintf("   Compras realizadas: %d veces" . PHP_EOL, $data['count']);
        echo sprintf("   Promedio por compra: $%s" . PHP_EOL, number_format($data['average'], 2));
        echo sprintf("   %% del total: %.1f%%" . PHP_EOL, ($data['total'] / $lastTotal) * 100);
        echo PHP_EOL;
        $rank++;
    }
    
    // Análisis por categorías
    echo "📈 ANÁLISIS POR CATEGORÍAS (MES PASADO)" . PHP_EOL;
    echo "=======================================" . PHP_EOL;
    
    $categoryAnalysis = $lastMonthPurchases->groupBy('category')->map(function($items, $category) {
        return [
            'category' => $category ?: 'Sin categoría',
            'total' => $items->sum('amount'),
            'count' => $items->count(),
            'average' => $items->avg('amount'),
            'products' => $items->groupBy('concept')->count()
        ];
    })->sortByDesc('total');
    
    foreach ($categoryAnalysis as $category => $data) {
        echo sprintf("🏷️  %s:" . PHP_EOL, $data['category']);
        echo sprintf("   Gasto total: $%s (%.1f%% del total)" . PHP_EOL, 
            number_format($data['total'], 2), 
            ($data['total'] / $lastTotal) * 100
        );
        echo sprintf("   Compras: %d | Productos diferentes: %d" . PHP_EOL, 
            $data['count'], 
            $data['products']
        );
        echo sprintf("   Promedio por compra: $%s" . PHP_EOL, number_format($data['average'], 2));
        echo PHP_EOL;
    }
    
    // Comparativa de productos principales
    echo "🔄 COMPARATIVA PRODUCTOS PRINCIPALES" . PHP_EOL;
    echo "====================================" . PHP_EOL;
    
    $currentMonthByConcept = $currentMonthPurchases->groupBy('concept')->map(function($items, $concept) {
        return [
            'concept' => $concept ?: 'Sin concepto',
            'total' => $items->sum('amount'),
            'count' => $items->count()
        ];
    })->sortByDesc('total');
    
    $mainProducts = ['Pollo', 'Alitas', 'Pollo y alitas', 'Coca-cola pedido', 'Andrey'];
    
    foreach ($mainProducts as $product) {
        $lastData = $lastMonthByConcept->get($product);
        $currentData = $currentMonthByConcept->get($product);
        
        if ($lastData || $currentData) {
            $lastAmount = $lastData ? $lastData['total'] : 0;
            $currentAmount = $currentData ? $currentData['total'] : 0;
            $change = $currentAmount - $lastAmount;
            $changePercent = $lastAmount > 0 ? (($change / $lastAmount) * 100) : 0;
            
            echo sprintf("%-20s:", $product) . PHP_EOL;
            echo sprintf("  Mes pasado: $%s", number_format($lastAmount, 0));
            if ($lastData) echo sprintf(" (%d compras)", $lastData['count']);
            echo PHP_EOL;
            
            echo sprintf("  Mes actual: $%s", number_format($currentAmount, 0));
            if ($currentData) echo sprintf(" (%d compras)", $currentData['count']);
            echo PHP_EOL;
            
            if ($change > 0) {
                echo sprintf("  📈 Incremento: $%s (+%.1f%%)" . PHP_EOL, number_format($change, 0), $changePercent);
            } elseif ($change < 0) {
                echo sprintf("  📉 Reducción: $%s (%.1f%%)" . PHP_EOL, number_format($change, 0), $changePercent);
            } else {
                echo "  ➡️  Sin cambios" . PHP_EOL;
            }
            echo PHP_EOL;
        }
    }
    
    // Recomendaciones
    echo "💡 RECOMENDACIONES ESTRATÉGICAS" . PHP_EOL;
    echo "===============================" . PHP_EOL;
    
    $topSpending = $categoryAnalysis->first();
    echo "1. CONTROL DE GASTOS:" . PHP_EOL;
    echo "   - Tu categoría de mayor gasto es: " . $topSpending['category'] . PHP_EOL;
    echo "   - Representa el " . number_format(($topSpending['total'] / $lastTotal) * 100, 1) . "% del total" . PHP_EOL;
    echo "   - Considera negociar mejores precios con proveedores" . PHP_EOL . PHP_EOL;
    
    echo "2. PRODUCTOS PRINCIPALES:" . PHP_EOL;
    echo "   - Pollo y Alitas son tus compras más costosas" . PHP_EOL;
    echo "   - Evalúa comprar en mayor volumen para descuentos" . PHP_EOL;
    echo "   - Considera proveedores alternativos" . PHP_EOL . PHP_EOL;
    
    echo "3. GESTIÓN FINANCIERA:" . PHP_EOL;
    if ($percentageChange > 0) {
        echo "   - Tus gastos han aumentado " . number_format($percentageChange, 1) . "%" . PHP_EOL;
        echo "   - Establece presupuestos máximos por categoría" . PHP_EOL;
    } else {
        echo "   - ¡Buen trabajo! Has reducido gastos " . number_format(abs($percentageChange), 1) . "%" . PHP_EOL;
        echo "   - Mantén esta tendencia de optimización" . PHP_EOL;
    }
    echo "   - Revisa semanalmente para control temprano" . PHP_EOL . PHP_EOL;
    
    echo "4. DIVERSIFICACIÓN:" . PHP_EOL;
    echo "   - Categoría 'Varios' puede indicar falta de control" . PHP_EOL;
    echo "   - Define categorías más específicas" . PHP_EOL;
    echo "   - Registra detalles en 'concept' para mejor seguimiento" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error durante el análisis: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== FIN DEL ANÁLISIS ===" . PHP_EOL;
?>












