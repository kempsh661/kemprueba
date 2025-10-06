<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ANÁLISIS DE COMPRAS - JULIO 2025 ===" . PHP_EOL . PHP_EOL;

try {
    // Definir fechas para julio 2025
    $startOfJuly = Carbon\Carbon::create(2025, 7, 1)->startOfMonth();
    $endOfJuly = Carbon\Carbon::create(2025, 7, 31)->endOfMonth();
    
    echo "Período analizado: " . $startOfJuly->format('Y-m-d') . " al " . $endOfJuly->format('Y-m-d') . PHP_EOL;
    echo "Mes: Julio 2025" . PHP_EOL . PHP_EOL;

    // Obtener todas las compras de julio
    $purchases = DB::table('purchases')
        ->where('user_id', 1)
        ->whereBetween('date', [$startOfJuly, $endOfJuly])
        ->orderBy('date')
        ->get();
    
    if ($purchases->isEmpty()) {
        echo "❌ No se encontraron compras en julio 2025." . PHP_EOL;
        exit;
    }
    
    echo "📊 RESUMEN GENERAL DE COMPRAS" . PHP_EOL;
    echo "=============================" . PHP_EOL;
    echo "Total de compras realizadas: " . $purchases->count() . PHP_EOL;
    echo "Valor total gastado: $" . number_format($purchases->sum('amount'), 2) . PHP_EOL;
    echo "Promedio por compra: $" . number_format($purchases->avg('amount'), 2) . PHP_EOL;
    echo "Compra más alta: $" . number_format($purchases->max('amount'), 2) . PHP_EOL;
    echo "Compra más baja: $" . number_format($purchases->min('amount'), 2) . PHP_EOL . PHP_EOL;
    
    // Función para agrupar conceptos similares
    function groupSimilarConcepts($purchases) {
        $groups = [];
        
        foreach ($purchases as $purchase) {
            $concept = trim($purchase->concept ?: 'Sin concepto');
            $conceptLower = strtolower($concept);
            $grouped = false;
            
            // Buscar grupo existente con concepto similar
            foreach ($groups as $groupKey => &$group) {
                $groupKeyLower = strtolower($groupKey);
                
                // Verificar similitud exacta o contenido
                if ($conceptLower === $groupKeyLower || 
                    strpos($conceptLower, $groupKeyLower) !== false || 
                    strpos($groupKeyLower, $conceptLower) !== false ||
                    levenshtein($conceptLower, $groupKeyLower) <= 2) {
                    
                    $group['purchases'][] = $purchase;
                    $group['total_amount'] += $purchase->amount;
                    $group['count']++;
                    $grouped = true;
                    break;
                }
            }
            
            // Si no se agrupó, crear nuevo grupo
            if (!$grouped) {
                $groups[$concept] = [
                    'purchases' => [$purchase],
                    'total_amount' => $purchase->amount,
                    'count' => 1,
                    'category' => $purchase->category ?: 'Sin categoría',
                    'average_amount' => $purchase->amount
                ];
            }
        }
        
        // Calcular promedios
        foreach ($groups as &$group) {
            $group['average_amount'] = $group['total_amount'] / $group['count'];
        }
        
        return $groups;
    }
    
    // Agrupar compras por concepto
    $conceptGroups = groupSimilarConcepts($purchases);
    
    // Ordenar por total gastado descendente
    uasort($conceptGroups, function($a, $b) {
        return $b['total_amount'] <=> $a['total_amount'];
    });
    
    echo "🛒 PRODUCTOS/CONCEPTOS MÁS COMPRADOS" . PHP_EOL;
    echo "====================================" . PHP_EOL;
    
    $totalSpent = $purchases->sum('amount');
    $rank = 1;
    
    foreach ($conceptGroups as $concept => $group) {
        $percentage = ($group['total_amount'] / $totalSpent) * 100;
        
        echo sprintf("%d. %s" . PHP_EOL, $rank, $concept);
        echo sprintf("   Categoría: %s" . PHP_EOL, $group['category']);
        echo sprintf("   Total gastado: $%s (%.1f%% del total)" . PHP_EOL, 
            number_format($group['total_amount'], 2), $percentage);
        echo sprintf("   Número de compras: %d veces" . PHP_EOL, $group['count']);
        echo sprintf("   Promedio por compra: $%s" . PHP_EOL, 
            number_format($group['average_amount'], 2));
        
        // Mostrar fechas si se compró múltiples veces
        if ($group['count'] > 1) {
            $dates = array_map(function($p) {
                return Carbon\Carbon::parse($p->date)->format('d/m');
            }, $group['purchases']);
            echo sprintf("   Fechas de compra: %s" . PHP_EOL, implode(', ', $dates));
        }
        
        // Mostrar variaciones si las hay
        if ($group['count'] > 1) {
            $amounts = array_map(function($p) { return $p->amount; }, $group['purchases']);
            $minAmount = min($amounts);
            $maxAmount = max($amounts);
            if ($minAmount != $maxAmount) {
                echo sprintf("   Rango de precios: $%s - $%s" . PHP_EOL, 
                    number_format($minAmount, 2), number_format($maxAmount, 2));
            }
        }
        
        echo PHP_EOL;
        $rank++;
        
        if ($rank > 15) break; // Limitar a top 15
    }
    
    // Análisis de productos que se repiten
    echo "🔄 PRODUCTOS QUE SE REPITEN MÁS" . PHP_EOL;
    echo "===============================" . PHP_EOL;
    
    $repeatedProducts = array_filter($conceptGroups, function($group) {
        return $group['count'] > 1;
    });
    
    // Ordenar por cantidad de repeticiones
    uasort($repeatedProducts, function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    
    if (empty($repeatedProducts)) {
        echo "No hay productos que se repitan en julio." . PHP_EOL . PHP_EOL;
    } else {
        foreach ($repeatedProducts as $concept => $group) {
            echo sprintf("📦 %s" . PHP_EOL, $concept);
            echo sprintf("   Se compró %d veces" . PHP_EOL, $group['count']);
            echo sprintf("   Gasto total: $%s" . PHP_EOL, number_format($group['total_amount'], 2));
            echo sprintf("   Gasto promedio por compra: $%s" . PHP_EOL, 
                number_format($group['average_amount'], 2));
            echo sprintf("   Frecuencia: cada %.1f días aproximadamente" . PHP_EOL, 
                31 / $group['count']);
            echo PHP_EOL;
        }
    }
    
    // Análisis por categorías
    echo "📊 ANÁLISIS POR CATEGORÍAS" . PHP_EOL;
    echo "==========================" . PHP_EOL;
    
    $categoryTotals = [];
    foreach ($purchases as $purchase) {
        $category = $purchase->category ?: 'Sin categoría';
        if (!isset($categoryTotals[$category])) {
            $categoryTotals[$category] = [
                'total' => 0,
                'count' => 0,
                'products' => []
            ];
        }
        $categoryTotals[$category]['total'] += $purchase->amount;
        $categoryTotals[$category]['count']++;
        
        $concept = $purchase->concept ?: 'Sin concepto';
        if (!in_array($concept, $categoryTotals[$category]['products'])) {
            $categoryTotals[$category]['products'][] = $concept;
        }
    }
    
    // Ordenar categorías por total
    uasort($categoryTotals, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    
    foreach ($categoryTotals as $category => $data) {
        $percentage = ($data['total'] / $totalSpent) * 100;
        echo sprintf("🏷️  %s:" . PHP_EOL, $category);
        echo sprintf("   Gasto total: $%s (%.1f%% del total)" . PHP_EOL, 
            number_format($data['total'], 2), $percentage);
        echo sprintf("   Número de compras: %d" . PHP_EOL, $data['count']);
        echo sprintf("   Productos diferentes: %d" . PHP_EOL, count($data['products']));
        echo sprintf("   Promedio por compra: $%s" . PHP_EOL, 
            number_format($data['total'] / $data['count'], 2));
        echo PHP_EOL;
    }
    
    // Análisis temporal
    echo "📅 ANÁLISIS TEMPORAL - DISTRIBUCIÓN POR SEMANAS" . PHP_EOL;
    echo "===============================================" . PHP_EOL;
    
    $weeklySpending = [];
    foreach ($purchases as $purchase) {
        $date = Carbon\Carbon::parse($purchase->date);
        $week = $date->weekOfMonth;
        $weekLabel = "Semana " . $week . " (" . $date->startOfWeek()->format('d/m') . " - " . 
                     $date->endOfWeek()->format('d/m') . ")";
        
        if (!isset($weeklySpending[$weekLabel])) {
            $weeklySpending[$weekLabel] = 0;
        }
        $weeklySpending[$weekLabel] += $purchase->amount;
    }
    
    foreach ($weeklySpending as $week => $total) {
        $percentage = ($total / $totalSpent) * 100;
        echo sprintf("📆 %s: $%s (%.1f%%)" . PHP_EOL, 
            $week, number_format($total, 2), $percentage);
    }
    echo PHP_EOL;
    
    // Resumen ejecutivo
    echo "📈 RESUMEN EJECUTIVO - GASTO MENSUAL POR PRODUCTO" . PHP_EOL;
    echo "=================================================" . PHP_EOL;
    
    $topProducts = array_slice($conceptGroups, 0, 5, true);
    echo "Los 5 productos con mayor gasto mensual en julio:" . PHP_EOL . PHP_EOL;
    
    foreach ($topProducts as $concept => $group) {
        echo sprintf("• %s: $%s/mes" . PHP_EOL, 
            $concept, number_format($group['total_amount'], 0));
        if ($group['count'] > 1) {
            echo sprintf("  (Comprado %d veces, promedio $%s por compra)" . PHP_EOL, 
                $group['count'], number_format($group['average_amount'], 0));
        }
    }
    
    echo PHP_EOL;
    echo "💡 RECOMENDACIONES:" . PHP_EOL;
    echo "• Los productos que más se repiten pueden beneficiarse de compras al por mayor" . PHP_EOL;
    echo "• Considera negociar descuentos para los productos de mayor gasto mensual" . PHP_EOL;
    echo "• Evalúa proveedores alternativos para optimizar costos" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error durante el análisis: " . $e->getMessage() . PHP_EOL;
    echo "Línea: " . $e->getLine() . PHP_EOL;
}

echo PHP_EOL . "=== FIN DEL ANÁLISIS DE COMPRAS DE JULIO ===" . PHP_EOL;
?>




