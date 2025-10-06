<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ANÁLISIS DE VENTAS - JULIO 2025 ===" . PHP_EOL . PHP_EOL;

try {
    // Definir fechas para julio 2025
    $startOfJuly = Carbon\Carbon::create(2025, 7, 1)->startOfMonth();
    $endOfJuly = Carbon\Carbon::create(2025, 7, 31)->endOfMonth();
    
    echo "Período analizado: " . $startOfJuly->format('Y-m-d') . " al " . $endOfJuly->format('Y-m-d') . PHP_EOL;
    echo "Mes: Julio 2025" . PHP_EOL . PHP_EOL;

    // Obtener todas las ventas de julio
    $sales = DB::table('sales')
        ->where('user_id', 1)
        ->whereBetween('created_at', [$startOfJuly, $endOfJuly])
        ->whereNotNull('items')
        ->get();
    
    if ($sales->isEmpty()) {
        echo "❌ No se encontraron ventas en julio 2025." . PHP_EOL;
        exit;
    }
    
    echo "📊 RESUMEN GENERAL DE VENTAS" . PHP_EOL;
    echo "===========================" . PHP_EOL;
    echo "Total de ventas: " . $sales->count() . PHP_EOL;
    echo "Valor total vendido: $" . number_format($sales->sum('total'), 2) . PHP_EOL;
    echo "Promedio por venta: $" . number_format($sales->avg('total'), 2) . PHP_EOL . PHP_EOL;
    
    // Extraer y procesar todos los items
    $allItems = [];
    $salesWithoutItems = 0;
    
    foreach ($sales as $sale) {
        $items = null;
        
        // Intentar decodificar los items (pueden estar en diferentes formatos)
        if ($sale->items) {
            if (is_string($sale->items)) {
                $items = json_decode($sale->items, true);
            } else {
                $items = $sale->items;
            }
        }
        
        if ($items && is_array($items)) {
            foreach ($items as $item) {
                $itemName = '';
                $quantity = 0;
                $price = 0;
                $total = 0;
                
                // Manejar diferentes estructuras de items
                if (is_array($item)) {
                    $itemName = $item['name'] ?? $item['product_name'] ?? $item['item_name'] ?? 'Producto sin nombre';
                    $quantity = floatval($item['quantity'] ?? $item['qty'] ?? 1);
                    $price = floatval($item['price'] ?? $item['unit_price'] ?? 0);
                    $total = floatval($item['total'] ?? $item['subtotal'] ?? ($quantity * $price));
                } else {
                    $itemName = 'Item: ' . $item;
                    $quantity = 1;
                    $total = 0;
                }
                
                $allItems[] = [
                    'name' => $itemName,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total,
                    'sale_id' => $sale->id,
                    'sale_date' => $sale->created_at,
                    'sale_total' => $sale->total
                ];
            }
        } else {
            $salesWithoutItems++;
        }
    }
    
    if (empty($allItems)) {
        echo "❌ No se encontraron items en las ventas de julio." . PHP_EOL;
        echo "Ventas sin items detectados: " . $salesWithoutItems . PHP_EOL;
        exit;
    }
    
    echo "📦 ANÁLISIS DE PRODUCTOS" . PHP_EOL;
    echo "========================" . PHP_EOL;
    echo "Total de items encontrados: " . count($allItems) . PHP_EOL;
    echo "Ventas sin items: " . $salesWithoutItems . PHP_EOL . PHP_EOL;
    
    // Función para agrupar productos similares
    function groupSimilarProducts($items) {
        $groups = [];
        
        foreach ($items as $item) {
            $name = strtolower(trim($item['name']));
            $grouped = false;
            
            // Buscar grupo existente con nombre similar
            foreach ($groups as $groupKey => &$group) {
                $groupName = strtolower($groupKey);
                
                // Verificar similitud (contiene palabras clave)
                $similarity = 0;
                $nameWords = explode(' ', $name);
                $groupWords = explode(' ', $groupName);
                
                foreach ($nameWords as $word) {
                    if (strlen($word) > 2) { // Ignorar palabras muy cortas
                        foreach ($groupWords as $groupWord) {
                            if (strlen($groupWord) > 2 && 
                                (strpos($groupWord, $word) !== false || strpos($word, $groupWord) !== false)) {
                                $similarity++;
                                break;
                            }
                        }
                    }
                }
                
                // Si hay suficiente similitud o nombres casi idénticos
                if ($similarity >= 1 || 
                    levenshtein($name, $groupName) <= 2 || 
                    strpos($name, $groupName) !== false || 
                    strpos($groupName, $name) !== false) {
                    
                    $group['items'][] = $item;
                    $group['total_quantity'] += $item['quantity'];
                    $group['total_value'] += $item['total'];
                    $group['count']++;
                    $grouped = true;
                    break;
                }
            }
            
            // Si no se agrupó, crear nuevo grupo
            if (!$grouped) {
                $groups[$item['name']] = [
                    'items' => [$item],
                    'total_quantity' => $item['quantity'],
                    'total_value' => $item['total'],
                    'count' => 1,
                    'average_price' => $item['price']
                ];
            }
        }
        
        return $groups;
    }
    
    // Agrupar productos similares
    $productGroups = groupSimilarProducts($allItems);
    
    // Ordenar por valor total descendente
    uasort($productGroups, function($a, $b) {
        return $b['total_value'] <=> $a['total_value'];
    });
    
    echo "🏆 TOP PRODUCTOS/GRUPOS MÁS VENDIDOS" . PHP_EOL;
    echo "====================================" . PHP_EOL;
    
    $rank = 1;
    foreach ($productGroups as $productName => $group) {
        if ($group['total_value'] > 0) {
            echo sprintf("%d. %s" . PHP_EOL, $rank, $productName);
            echo sprintf("   Cantidad total vendida: %.1f unidades" . PHP_EOL, $group['total_quantity']);
            echo sprintf("   Valor total: $%s" . PHP_EOL, number_format($group['total_value'], 2));
            echo sprintf("   Número de ventas: %d" . PHP_EOL, $group['count']);
            echo sprintf("   Precio promedio: $%s" . PHP_EOL, number_format($group['average_price'], 2));
            
            // Mostrar variaciones del nombre si hay múltiples
            if (count($group['items']) > 1) {
                $variations = array_unique(array_column($group['items'], 'name'));
                if (count($variations) > 1) {
                    echo "   Variaciones: " . implode(', ', array_slice($variations, 0, 3));
                    if (count($variations) > 3) echo " y " . (count($variations) - 3) . " más";
                    echo PHP_EOL;
                }
            }
            echo PHP_EOL;
            $rank++;
            
            if ($rank > 20) break; // Limitar a top 20
        }
    }
    
    // Análisis por categorías inferidas
    echo "📊 ANÁLISIS POR CATEGORÍAS INFERIDAS" . PHP_EOL;
    echo "====================================" . PHP_EOL;
    
    $categories = [
        'Pollo' => ['pollo', 'chicken', 'pechuga', 'muslo'],
        'Alitas' => ['alitas', 'alas', 'wings'],
        'Bebidas' => ['coca', 'pepsi', 'agua', 'jugo', 'gaseosa', 'refresco', 'bebida'],
        'Acompañantes' => ['papa', 'yuca', 'platano', 'ensalada', 'arroz'],
        'Salsas' => ['salsa', 'ajo', 'bbq', 'mostaza', 'mayonesa'],
        'Otros' => []
    ];
    
    $categoryTotals = [];
    foreach ($categories as $catName => $keywords) {
        $categoryTotals[$catName] = ['value' => 0, 'quantity' => 0, 'count' => 0];
    }
    
    foreach ($productGroups as $productName => $group) {
        $categorized = false;
        $lowerName = strtolower($productName);
        
        foreach ($categories as $catName => $keywords) {
            if ($catName === 'Otros') continue;
            
            foreach ($keywords as $keyword) {
                if (strpos($lowerName, $keyword) !== false) {
                    $categoryTotals[$catName]['value'] += $group['total_value'];
                    $categoryTotals[$catName]['quantity'] += $group['total_quantity'];
                    $categoryTotals[$catName]['count'] += $group['count'];
                    $categorized = true;
                    break 2;
                }
            }
        }
        
        if (!$categorized) {
            $categoryTotals['Otros']['value'] += $group['total_value'];
            $categoryTotals['Otros']['quantity'] += $group['total_quantity'];
            $categoryTotals['Otros']['count'] += $group['count'];
        }
    }
    
    // Ordenar categorías por valor
    uasort($categoryTotals, function($a, $b) {
        return $b['value'] <=> $a['value'];
    });
    
    $totalValue = array_sum(array_column($categoryTotals, 'value'));
    
    foreach ($categoryTotals as $catName => $totals) {
        if ($totals['value'] > 0) {
            $percentage = $totalValue > 0 ? ($totals['value'] / $totalValue) * 100 : 0;
            echo sprintf("🏷️  %s:" . PHP_EOL, $catName);
            echo sprintf("   Valor total: $%s (%.1f%% del total)" . PHP_EOL, 
                number_format($totals['value'], 2), $percentage);
            echo sprintf("   Cantidad: %.1f unidades en %d ventas" . PHP_EOL, 
                $totals['quantity'], $totals['count']);
            echo PHP_EOL;
        }
    }
    
    // Estadísticas adicionales
    echo "📈 ESTADÍSTICAS DETALLADAS" . PHP_EOL;
    echo "==========================" . PHP_EOL;
    
    $allValues = array_column($allItems, 'total');
    $allQuantities = array_column($allItems, 'quantity');
    
    echo sprintf("Producto más caro vendido: $%s" . PHP_EOL, number_format(max($allValues), 2));
    echo sprintf("Producto más barato: $%s" . PHP_EOL, number_format(min(array_filter($allValues)), 2));
    echo sprintf("Precio promedio por item: $%s" . PHP_EOL, number_format(array_sum($allValues) / count($allValues), 2));
    echo sprintf("Cantidad total de productos vendidos: %.1f unidades" . PHP_EOL, array_sum($allQuantities));
    echo sprintf("Promedio de items por venta: %.1f" . PHP_EOL, count($allItems) / $sales->count());
    
} catch (Exception $e) {
    echo "Error durante el análisis: " . $e->getMessage() . PHP_EOL;
    echo "Línea: " . $e->getLine() . PHP_EOL;
}

echo PHP_EOL . "=== FIN DEL ANÁLISIS DE VENTAS ===" . PHP_EOL;
?>












