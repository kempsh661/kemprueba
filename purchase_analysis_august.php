<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ANÁLISIS DE COMPRAS - AGOSTO 2025 (LO QUE LLEVAMOS) ===" . PHP_EOL . PHP_EOL;

try {
    $now = Carbon\Carbon::now('America/Bogota');
    $startOfAugust = Carbon\Carbon::create(2025, 8, 1)->startOfMonth();
    $today = $now->copy();
    
    // También obtener datos de julio para comparación
    $startOfJuly = Carbon\Carbon::create(2025, 7, 1)->startOfMonth();
    $endOfJuly = Carbon\Carbon::create(2025, 7, 31)->endOfMonth();
    
    echo "Período analizado: " . $startOfAugust->format('Y-m-d') . " al " . $today->format('Y-m-d') . PHP_EOL;
    echo "Días transcurridos en agosto: " . $startOfAugust->diffInDays($today) + 1 . " de 31 días" . PHP_EOL;
    echo "Porcentaje del mes completado: " . number_format((($startOfAugust->diffInDays($today) + 1) / 31) * 100, 1) . "%" . PHP_EOL . PHP_EOL;

    // Obtener compras de agosto hasta hoy
    $augustPurchases = DB::table('purchases')
        ->where('user_id', 1)
        ->whereBetween('date', [$startOfAugust->toDateString(), $today->toDateString()])
        ->orderBy('date')
        ->get();
    
    // Obtener compras de julio completo para comparación
    $julyPurchases = DB::table('purchases')
        ->where('user_id', 1)
        ->whereBetween('date', [$startOfJuly->toDateString(), $endOfJuly->toDateString()])
        ->get();
    
    if ($augustPurchases->isEmpty()) {
        echo "❌ No se encontraron compras en agosto 2025 hasta la fecha." . PHP_EOL;
        exit;
    }
    
    $augustTotal = $augustPurchases->sum('amount');
    $julyTotal = $julyPurchases->sum('amount');
    $daysInAugust = $startOfAugust->diffInDays($today) + 1;
    $dailyAverageAugust = $augustTotal / $daysInAugust;
    $dailyAverageJuly = $julyTotal / 31;
    
    echo "📊 RESUMEN GENERAL - AGOSTO VS JULIO" . PHP_EOL;
    echo "====================================" . PHP_EOL;
    echo "AGOSTO (hasta hoy):" . PHP_EOL;
    echo "  Total gastado: $" . number_format($augustTotal, 2) . PHP_EOL;
    echo "  Número de compras: " . $augustPurchases->count() . PHP_EOL;
    echo "  Promedio por compra: $" . number_format($augustPurchases->avg('amount'), 2) . PHP_EOL;
    echo "  Promedio diario: $" . number_format($dailyAverageAugust, 2) . PHP_EOL . PHP_EOL;
    
    echo "JULIO (mes completo):" . PHP_EOL;
    echo "  Total gastado: $" . number_format($julyTotal, 2) . PHP_EOL;
    echo "  Número de compras: " . $julyPurchases->count() . PHP_EOL;
    echo "  Promedio diario: $" . number_format($dailyAverageJuly, 2) . PHP_EOL . PHP_EOL;
    
    // Proyección para el mes completo
    $projectedAugustTotal = $dailyAverageAugust * 31;
    $projectedDifference = $projectedAugustTotal - $julyTotal;
    $projectedPercentageChange = $julyTotal > 0 ? (($projectedDifference / $julyTotal) * 100) : 0;
    
    echo "📈 PROYECCIÓN PARA AGOSTO COMPLETO" . PHP_EOL;
    echo "=================================" . PHP_EOL;
    echo "Proyección total agosto: $" . number_format($projectedAugustTotal, 2) . PHP_EOL;
    echo "Diferencia vs julio: $" . number_format($projectedDifference, 2) . 
         " (" . ($projectedDifference >= 0 ? "+" : "") . number_format($projectedPercentageChange, 1) . "%)" . PHP_EOL;
    
    if ($projectedDifference > 0) {
        echo "⚠️  Tendencia: Gastos superiores a julio" . PHP_EOL;
    } elseif ($projectedDifference < 0) {
        echo "✅ Tendencia: Gastos menores a julio" . PHP_EOL;
    } else {
        echo "➡️  Tendencia: Gastos similares a julio" . PHP_EOL;
    }
    echo PHP_EOL;
    
    // Función para agrupar conceptos similares (misma que julio)
    function groupSimilarConcepts($purchases) {
        $groups = [];
        
        foreach ($purchases as $purchase) {
            $concept = trim($purchase->concept ?: 'Sin concepto');
            $conceptLower = strtolower($concept);
            $grouped = false;
            
            foreach ($groups as $groupKey => &$group) {
                $groupKeyLower = strtolower($groupKey);
                
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
        
        foreach ($groups as &$group) {
            $group['average_amount'] = $group['total_amount'] / $group['count'];
        }
        
        return $groups;
    }
    
    // Agrupar compras de agosto
    $augustConceptGroups = groupSimilarConcepts($augustPurchases);
    uasort($augustConceptGroups, function($a, $b) {
        return $b['total_amount'] <=> $a['total_amount'];
    });
    
    // Agrupar compras de julio para comparación
    $julyConceptGroups = groupSimilarConcepts($julyPurchases);
    
    echo "🛒 PRODUCTOS/CONCEPTOS MÁS COMPRADOS EN AGOSTO" . PHP_EOL;
    echo "==============================================" . PHP_EOL;
    
    $rank = 1;
    foreach ($augustConceptGroups as $concept => $group) {
        $percentage = ($group['total_amount'] / $augustTotal) * 100;
        
        echo sprintf("%d. %s" . PHP_EOL, $rank, $concept);
        echo sprintf("   Categoría: %s" . PHP_EOL, $group['category']);
        echo sprintf("   Total gastado: $%s (%.1f%% del total)" . PHP_EOL, 
            number_format($group['total_amount'], 2), $percentage);
        echo sprintf("   Número de compras: %d veces" . PHP_EOL, $group['count']);
        echo sprintf("   Promedio por compra: $%s" . PHP_EOL, 
            number_format($group['average_amount'], 2));
        
        // Comparar con julio
        $julyData = $julyConceptGroups[$concept] ?? null;
        if ($julyData) {
            $monthlyProjection = ($group['total_amount'] / $daysInAugust) * 31;
            $julySame = $julyData['total_amount'];
            $difference = $monthlyProjection - $julySame;
            $percentChange = $julySame > 0 ? (($difference / $julySame) * 100) : 0;
            
            echo sprintf("   📊 Vs Julio: $%s (proyectado) vs $%s (real)", 
                number_format($monthlyProjection, 0), number_format($julySame, 0));
            
            if ($difference > 0) {
                echo sprintf(" [+%.1f%%]", $percentChange);
            } elseif ($difference < 0) {
                echo sprintf(" [%.1f%%]", $percentChange);
            }
            echo PHP_EOL;
        } else {
            echo "   🆕 Producto nuevo (no estaba en julio)" . PHP_EOL;
        }
        
        if ($group['count'] > 1) {
            $dates = array_map(function($p) {
                return Carbon\Carbon::parse($p->date)->format('d/m');
            }, $group['purchases']);
            echo sprintf("   Fechas: %s" . PHP_EOL, implode(', ', $dates));
        }
        
        echo PHP_EOL;
        $rank++;
        
        if ($rank > 15) break;
    }
    
    // Productos que se repiten en agosto
    echo "🔄 PRODUCTOS QUE SE REPITEN EN AGOSTO" . PHP_EOL;
    echo "====================================" . PHP_EOL;
    
    $repeatedProducts = array_filter($augustConceptGroups, function($group) {
        return $group['count'] > 1;
    });
    
    uasort($repeatedProducts, function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    
    if (empty($repeatedProducts)) {
        echo "No hay productos que se repitan en agosto hasta la fecha." . PHP_EOL . PHP_EOL;
    } else {
        foreach ($repeatedProducts as $concept => $group) {
            echo sprintf("📦 %s" . PHP_EOL, $concept);
            echo sprintf("   Se compró %d veces en %d días" . PHP_EOL, $group['count'], $daysInAugust);
            echo sprintf("   Gasto total: $%s" . PHP_EOL, number_format($group['total_amount'], 2));
            echo sprintf("   Gasto promedio por compra: $%s" . PHP_EOL, 
                number_format($group['average_amount'], 2));
            echo sprintf("   Frecuencia: cada %.1f días aproximadamente" . PHP_EOL, 
                $daysInAugust / $group['count']);
            
            // Proyección mensual para este producto
            $monthlyProjection = ($group['total_amount'] / $daysInAugust) * 31;
            echo sprintf("   📈 Proyección mensual: $%s" . PHP_EOL, number_format($monthlyProjection, 2));
            
            echo PHP_EOL;
        }
    }
    
    // Comparativa detallada con julio
    echo "🔍 COMPARATIVA DETALLADA AGOSTO vs JULIO" . PHP_EOL;
    echo "========================================" . PHP_EOL;
    
    $mainProducts = ['Pollo', 'Alitas', 'Pago de Andrey', 'carne', 'panes', 'internet', 'Gasolina'];
    
    foreach ($mainProducts as $product) {
        $augustData = $augustConceptGroups[$product] ?? null;
        $julyData = $julyConceptGroups[$product] ?? null;
        
        if ($augustData || $julyData) {
            echo sprintf("🏷️  %s:" . PHP_EOL, $product);
            
            if ($augustData) {
                $monthlyProjection = ($augustData['total_amount'] / $daysInAugust) * 31;
                echo sprintf("   Agosto (proyectado): $%s (%d compras hasta hoy)" . PHP_EOL, 
                    number_format($monthlyProjection, 0), $augustData['count']);
            } else {
                echo "   Agosto: $0 (no comprado aún)" . PHP_EOL;
                $monthlyProjection = 0;
            }
            
            if ($julyData) {
                echo sprintf("   Julio (real): $%s (%d compras)" . PHP_EOL, 
                    number_format($julyData['total_amount'], 0), $julyData['count']);
            } else {
                echo "   Julio: $0" . PHP_EOL;
            }
            
            if ($augustData && $julyData) {
                $difference = $monthlyProjection - $julyData['total_amount'];
                $percentChange = $julyData['total_amount'] > 0 ? (($difference / $julyData['total_amount']) * 100) : 0;
                
                if ($difference > 0) {
                    echo sprintf("   📈 Tendencia: +$%s (+%.1f%%)" . PHP_EOL, 
                        number_format($difference, 0), $percentChange);
                } elseif ($difference < 0) {
                    echo sprintf("   📉 Tendencia: $%s (%.1f%%)" . PHP_EOL, 
                        number_format($difference, 0), $percentChange);
                } else {
                    echo "   ➡️  Tendencia: Sin cambios significativos" . PHP_EOL;
                }
            }
            echo PHP_EOL;
        }
    }
    
    // Análisis de ritmo de gasto
    echo "⏱️  ANÁLISIS DE RITMO DE GASTO" . PHP_EOL;
    echo "==============================" . PHP_EOL;
    
    $weeklySpending = [];
    foreach ($augustPurchases as $purchase) {
        $date = Carbon\Carbon::parse($purchase->date);
        $week = $date->weekOfMonth;
        $weekLabel = "Semana " . $week;
        
        if (!isset($weeklySpending[$weekLabel])) {
            $weeklySpending[$weekLabel] = 0;
        }
        $weeklySpending[$weekLabel] += $purchase->amount;
    }
    
    foreach ($weeklySpending as $week => $total) {
        $percentage = ($total / $augustTotal) * 100;
        echo sprintf("📅 %s: $%s (%.1f%%)" . PHP_EOL, 
            $week, number_format($total, 2), $percentage);
    }
    echo PHP_EOL;
    
    // Alertas y recomendaciones
    echo "🚨 ALERTAS Y RECOMENDACIONES" . PHP_EOL;
    echo "============================" . PHP_EOL;
    
    if ($projectedPercentageChange > 20) {
        echo "⚠️  ALERTA: Gastos proyectados " . number_format($projectedPercentageChange, 1) . "% superiores a julio" . PHP_EOL;
        echo "   - Revisa los gastos más altos de este mes" . PHP_EOL;
        echo "   - Considera posponer compras no esenciales" . PHP_EOL . PHP_EOL;
    } elseif ($projectedPercentageChange > 10) {
        echo "⚠️  PRECAUCIÓN: Gastos ligeramente superiores a julio (+" . number_format($projectedPercentageChange, 1) . "%)" . PHP_EOL;
        echo "   - Mantén control sobre los gastos restantes del mes" . PHP_EOL . PHP_EOL;
    } elseif ($projectedPercentageChange < -10) {
        echo "✅ EXCELENTE: Gastos " . number_format(abs($projectedPercentageChange), 1) . "% menores que julio" . PHP_EOL;
        echo "   - ¡Buen control de gastos!" . PHP_EOL;
        echo "   - Mantén esta tendencia" . PHP_EOL . PHP_EOL;
    } else {
        echo "✅ ESTABLE: Gastos similares a julio" . PHP_EOL;
        echo "   - Comportamiento de gastos consistente" . PHP_EOL . PHP_EOL;
    }
    
    // Próximas compras recomendadas basadas en patrones de julio
    echo "🔮 PRÓXIMAS COMPRAS PROBABLES (basado en patrones de julio)" . PHP_EOL;
    echo "==========================================================" . PHP_EOL;
    
    $lastPurchaseDates = [];
    foreach ($augustConceptGroups as $concept => $group) {
        if (isset($julyConceptGroups[$concept])) {
            $lastPurchase = end($group['purchases']);
            $lastDate = Carbon\Carbon::parse($lastPurchase->date);
            $julySame = $julyConceptGroups[$concept];
            $avgFrequency = 31 / $julySame['count']; // días promedio entre compras en julio
            
            $nextExpected = $lastDate->copy()->addDays($avgFrequency);
            if ($nextExpected->lte($today->copy()->addDays(7))) { // próximos 7 días
                $lastPurchaseDates[$concept] = [
                    'last_purchase' => $lastDate,
                    'next_expected' => $nextExpected,
                    'average_amount' => $julySame['average_amount'],
                    'days_since' => $lastDate->diffInDays($today)
                ];
            }
        }
    }
    
    uasort($lastPurchaseDates, function($a, $b) {
        return $a['next_expected'] <=> $b['next_expected'];
    });
    
    if (!empty($lastPurchaseDates)) {
        foreach (array_slice($lastPurchaseDates, 0, 5) as $concept => $data) {
            echo sprintf("📅 %s" . PHP_EOL, $concept);
            echo sprintf("   Última compra: %s (hace %d días)" . PHP_EOL, 
                $data['last_purchase']->format('d/m/Y'), $data['days_since']);
            echo sprintf("   Próxima esperada: %s" . PHP_EOL, 
                $data['next_expected']->format('d/m/Y'));
            echo sprintf("   Monto esperado: ~$%s" . PHP_EOL, 
                number_format($data['average_amount'], 0));
            echo PHP_EOL;
        }
    } else {
        echo "No hay compras próximas predecibles basadas en los patrones de julio." . PHP_EOL . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error durante el análisis: " . $e->getMessage() . PHP_EOL;
    echo "Línea: " . $e->getLine() . PHP_EOL;
}

echo PHP_EOL . "=== FIN DEL ANÁLISIS DE AGOSTO ===" . PHP_EOL;
?>




