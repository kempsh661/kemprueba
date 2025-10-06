<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        
        // OPTIMIZACIÓN: Cache para búsquedas frecuentes
        $cacheKey = "sales_search_{$userId}_" . md5(serialize($request->only(['date', 'customerDocument', 'customerName', 'per_page'])));
        $cacheDuration = 300; // 5 minutos
        
        // Intentar obtener del cache
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            return response()->json([
                'success' => true,
                'data' => $cachedData['data'],
                'meta' => $cachedData['meta'],
                'cached' => true
            ]);
        }
        
        $query = Sale::where('user_id', $userId);
        
        // Búsqueda por fecha (solo en sale_date)
        if ($request->has('date') && $request->date) {
            $date = $request->date;
            $query->whereDate('sale_date', $date);
        }
        
        // Búsqueda por documento del cliente
        if ($request->has('customerDocument') && $request->customerDocument) {
            $query->where('customer_document', 'like', '%' . $request->customerDocument . '%');
        }
        
        // Búsqueda por nombre del cliente
        if ($request->has('customerName') && $request->customerName) {
            $query->where('customer_name', 'like', '%' . $request->customerName . '%');
        }
        
        // OPTIMIZACIÓN: Paginación para evitar cargar todas las ventas
        $perPage = $request->get('per_page', 50); // Por defecto 50 registros
        $sales = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        // OPTIMIZACIÓN: Cargar todos los productos de una vez para evitar N+1 queries
        $allProducts = \App\Models\Product::where('user_id', $userId)->get()->keyBy('id');
        
        // Transformar las ventas para que coincidan con la estructura esperada por el frontend
        $transformedSales = $sales->map(function ($sale) use ($allProducts) {
            // Calcular montos de efectivo y crédito para facturas
            $cashAmount = 0;
            $creditAmount = 0;
            
            if ($sale->payment_method === 'combined') {
                $cashAmount = (float) ($sale->cash_received ?? 0);
                $creditAmount = (float) ($sale->remaining_balance ?? 0);
            } elseif ($sale->payment_method === 'credit') {
                $creditAmount = (float) $sale->total;
            } else {
                $cashAmount = (float) $sale->total;
            }
            
            // Generar descripción descriptiva del pago
            $paymentDescription = '';
            if ($sale->payment_method === 'combined') {
                if ($cashAmount > 0 && $creditAmount > 0) {
                    $paymentDescription = "Pagado: $" . number_format($cashAmount, 2) . " en efectivo, $" . number_format($creditAmount, 2) . " a crédito";
                } elseif ($cashAmount > 0) {
                    $paymentDescription = "Pagado: $" . number_format($cashAmount, 2) . " en efectivo";
                } elseif ($creditAmount > 0) {
                    $paymentDescription = "Pagado: $" . number_format($creditAmount, 2) . " a crédito";
                }
            } elseif ($sale->payment_method === 'credit') {
                $paymentDescription = "Pagado: $" . number_format($creditAmount, 2) . " a crédito";
            } else {
                $paymentDescription = "Pagado: $" . number_format($cashAmount, 2) . " en efectivo";
            }
            
            return [
                'id' => $sale->id,
                'customerName' => $sale->customer_name,
                'customerDocument' => $sale->customer_document,
                'customerPhone' => $sale->customer_phone,
                'customerEmail' => $sale->customer_email,
                'total' => (float) $sale->total,
                'subtotal' => (float) $sale->subtotal,
                'tax' => (float) $sale->tax,
                'discount' => (float) $sale->discount,
                'paymentMethod' => $sale->payment_method,
                'paymentDescription' => $paymentDescription,
                'transactionNumber' => $sale->transaction_number,
                'cashReceived' => $sale->cash_received ? (float) $sale->cash_received : null,
                'change' => $sale->change ? (float) $sale->change : null,
                'cashAmount' => $cashAmount,
                'creditAmount' => $creditAmount,
                'remainingBalance' => (float) ($sale->remaining_balance ?? 0),
                'status' => $sale->status ?? 'COMPLETED',
                'saleDate' => $sale->sale_date
                    ? $sale->sale_date->setTimezone('America/Bogota')->toISOString()
                    : $sale->created_at->setTimezone('America/Bogota')->toISOString(),
                'createdAt' => $sale->created_at->setTimezone('America/Bogota')->toISOString(),
                'reversalReason' => $sale->reversal_reason,
                'reversedAt' => $sale->reversed_at ? $sale->reversed_at->toISOString() : null,
                'user' => [
                    'name' => $sale->user->name ?? 'Usuario'
                ],
                'details' => $sale->items ? array_map(function ($item) use ($allProducts) {
                    // OPTIMIZACIÓN: Usar productos ya cargados en lugar de consulta individual
                    $product = $allProducts->get($item['productId'] ?? 0);
                    return [
                        'id' => $item['productId'] ?? 0,
                        'productId' => $item['productId'] ?? 0,
                        'quantity' => $item['quantity'] ?? 0,
                        'price' => (float) ($item['price'] ?? 0),
                        'product' => [
                            'name' => $product ? $product->name : 'Producto',
                            'code' => $product ? $product->code : 'PROD'
                        ]
                    ];
                }, $sale->items) : []
            ];
        });
        
        // Preparar respuesta con metadatos de paginación
        $responseData = [
            'data' => $transformedSales,
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
                'from' => $sales->firstItem(),
                'to' => $sales->lastItem()
            ]
        ];
        
        // Guardar en cache
        Cache::put($cacheKey, $responseData, $cacheDuration);
        
        return response()->json([
            'success' => true,
            'data' => $transformedSales,
            'meta' => $responseData['meta'],
            'cached' => false
        ]);
    }

    public function store(Request $request)
    {
        try {
            \Log::info('DEBUG: Entró al método store de SaleController', $request->all());
            \Log::info('DEBUG: Valor recibido en $request->date', ['date' => $request->date]);
            
            $userId = $request->user()->id;
            
            // Validar datos requeridos
            $request->validate([
                'customerName' => 'required|string|max:255',
                'customerDocument' => 'required|string|max:255',
                'items' => 'required|array|min:1',
                'items.*.productId' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'total' => 'required|numeric|min:0',
                'subtotal' => 'required|numeric|min:0',
                'paymentMethod' => 'required|string|in:cash,card,transfer,credit,combined',
                'date' => 'nullable|string',
            ]);
            
            \Log::info('DEBUG: Validación pasada exitosamente');
            
            // Verificar si el cliente ya existe, si no, crearlo automáticamente
            $customer = \App\Models\Customer::where('user_id', $userId)
                ->where('document', $request->customerDocument)
                ->first();
                
            if (!$customer) {
                \Log::info('DEBUG: Cliente no encontrado, creando nuevo cliente');
                $customer = \App\Models\Customer::create([
                    'user_id' => $userId,
                    'name' => $request->customerName,
                    'document' => $request->customerDocument,
                    'email' => $request->customerEmail,
                    'phone' => $request->customerPhone,
                    'address' => null, // Se puede agregar después si es necesario
                ]);
                \Log::info('DEBUG: Cliente creado exitosamente', ['customer_id' => $customer->id]);
            } else {
                \Log::info('DEBUG: Cliente encontrado', ['customer_id' => $customer->id]);
            }
            
            // Validar formato de fecha si viene en el request
            if ($request->filled('date')) {
                $date = $request->date;
                \Log::info('DEBUG: Procesando fecha personalizada', ['date' => $date]);
                
                // Si la fecha viene en formato Y-m-d, convertirla a Carbon
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $date = \Carbon\Carbon::parse($date, 'America/Bogota');
                    \Log::info('DEBUG: Fecha convertida a Carbon', ['parsed_date' => $date->toISOString()]);
                } else {
                    \Log::info('DEBUG: Fecha no es formato Y-m-d, usando as-is', ['date' => $date]);
                }
            }
            
            // Preparar datos para la venta
            $saleData = [
                'user_id' => $userId,
                'customer_name' => $request->customerName,
                'customer_document' => $request->customerDocument,
                'customer_phone' => $request->customerPhone,
                'customer_email' => $request->customerEmail,
                'total' => $request->total,
                'subtotal' => $request->subtotal,
                'tax' => $request->tax ?? 0,
                'discount' => $request->discount ?? 0,
                'payment_method' => $request->paymentMethod,
                'cash_received' => $request->cashReceived,
                'change' => $request->change,
                'transaction_number' => $request->transactionNumber,
                'sale_date' => $request->filled('date')
                    ? \Carbon\Carbon::parse($request->date, 'America/Bogota')
                    : now('America/Bogota'),
                'items' => $request->items,
                'status' => 'COMPLETED',
            ];
            
            // Solo agregar campos si existen en la base de datos
            if (Schema::hasColumn('sales', 'payments')) {
                $saleData['payments'] = $request->payments;
            }
            
            \Log::info('DEBUG: Datos de venta preparados', $saleData);
            
            // Manejar diferentes tipos de pago
            if ($request->paymentMethod === 'credit') {
                \Log::info('DEBUG: Procesando venta a crédito');
                // Venta completamente a crédito
                if (Schema::hasColumn('sales', 'remaining_balance')) {
                    $saleData['remaining_balance'] = $request->total;
                }
                
                // Actualizar el saldo del cliente
                $customer->increment('credit_balance', $request->total);
            } elseif ($request->paymentMethod === 'combined') {
                \Log::info('DEBUG: Procesando venta combinada', ['payments' => $request->payments]);
                // Venta combinada: parte en efectivo/transferencia, parte a crédito
                if ($request->has('payments') && is_array($request->payments)) {
                    // Calcular totales por método de pago
                    $totalPaid = 0;
                    $totalCash = 0;
                    $totalCard = 0;
                    $totalTransfer = 0;
                    $totalCredit = 0;
                    
                    foreach ($request->payments as $payment) {
                        $amount = (float) ($payment['amount'] ?? 0);
                        $method = $payment['method'] ?? 'cash';
                        
                        switch ($method) {
                            case 'cash':
                                $totalCash += $amount;
                                $totalPaid += $amount;
                                break;
                            case 'card':
                                $totalCard += $amount;
                                $totalPaid += $amount;
                                break;
                            case 'transfer':
                                $totalTransfer += $amount;
                                $totalPaid += $amount;
                                break;
                            case 'credit':
                                $totalCredit += $amount;
                                break;
                        }
                    }
                    
                    \Log::info('DEBUG: Totales calculados', [
                        'totalPaid' => $totalPaid,
                        'totalCash' => $totalCash,
                        'totalCard' => $totalCard,
                        'totalTransfer' => $totalTransfer,
                        'totalCredit' => $totalCredit
                    ]);
                    
                    // Calcular saldo pendiente (total de la venta - total pagado)
                    $creditAmount = $request->total - $totalPaid;
                    
                    if ($creditAmount > 0) {
                        if (Schema::hasColumn('sales', 'remaining_balance')) {
                            $saleData['remaining_balance'] = $creditAmount;
                        }
                        $saleData['cash_received'] = $totalPaid;
                        
                        // Actualizar el saldo del cliente
                        $customer->increment('credit_balance', $creditAmount);
                        \Log::info('DEBUG: Saldo pendiente calculado', ['creditAmount' => $creditAmount]);
                    } else {
                        // Si se pagó todo, no hay saldo pendiente
                        if (Schema::hasColumn('sales', 'remaining_balance')) {
                            $saleData['remaining_balance'] = 0;
                        }
                        $saleData['cash_received'] = $request->total;
                        \Log::info('DEBUG: Venta completamente pagada');
                    }
                } else {
                    \Log::info('DEBUG: Usando fallback para pagos combinados');
                    // Fallback: usar cashReceived si no hay payments
                    $paidAmount = $request->cashReceived ?? 0;
                    $creditAmount = $request->total - $paidAmount;
                    
                    if ($creditAmount > 0) {
                        if (Schema::hasColumn('sales', 'remaining_balance')) {
                            $saleData['remaining_balance'] = $creditAmount;
                        }
                        $saleData['cash_received'] = $paidAmount;
                        
                        // Actualizar el saldo del cliente
                        $customer->increment('credit_balance', $creditAmount);
                    }
                }
            }
            
            \Log::info('DEBUG: Datos finales de venta antes de crear', $saleData);
            
            // Crear la venta
            $sale = Sale::create($saleData);
            \Log::info('DEBUG: Venta creada exitosamente', ['sale_id' => $sale->id]);
            
            // Actualizar stock de productos si es necesario
            foreach ($request->items as $item) {
                $product = \App\Models\Product::find($item['productId']);
                if ($product && $product->stock !== null) {
                    $product->decrement('stock', $item['quantity']);
                    \Log::info('DEBUG: Stock actualizado', [
                        'product_id' => $product->id,
                        'quantity_decreased' => $item['quantity']
                    ]);
                }
            }
            
            // Transformar la venta para la respuesta (igual que en index)
            $transformedSale = [
                'id' => $sale->id,
                'customerName' => $sale->customer_name,
                'customerDocument' => $sale->customer_document,
                'customerPhone' => $sale->customer_phone,
                'customerEmail' => $sale->customer_email,
                'total' => (float) $sale->total,
                'subtotal' => (float) $sale->subtotal,
                'tax' => (float) $sale->tax,
                'discount' => (float) $sale->discount,
                'paymentMethod' => $sale->payment_method,
                'paymentDescription' => '', // Puedes calcularlo igual que en index si lo necesitas
                'transactionNumber' => $sale->transaction_number,
                'cashReceived' => $sale->cash_received ? (float) $sale->cash_received : null,
                'change' => $sale->change ? (float) $sale->change : null,
                'cashAmount' => $sale->payment_method === 'credit' ? 0 : (float) $sale->total,
                'creditAmount' => $sale->payment_method === 'credit' ? (float) $sale->total : 0,
                'status' => $sale->status ?? 'COMPLETED',
                'saleDate' => $sale->sale_date ? $sale->sale_date->setTimezone('America/Bogota')->toISOString() : null,
                'createdAt' => $sale->created_at->setTimezone('America/Bogota')->toISOString(),
                'user' => [
                    'name' => $sale->user->name ?? 'Usuario'
                ],
                'details' => $sale->items ? array_map(function ($item) {
                    $product = \App\Models\Product::find($item['productId'] ?? 0);
                    return [
                        'id' => $item['productId'] ?? 0,
                        'productId' => $item['productId'] ?? 0,
                        'quantity' => $item['quantity'] ?? 0,
                        'price' => (float) ($item['price'] ?? 0),
                        'product' => [
                            'name' => $product ? $product->name : 'Producto',
                            'code' => $product ? $product->code : 'PROD'
                        ]
                    ];
                }, $sale->items) : []
            ];
            
            // Agregar campos opcionales solo si existen
            if (Schema::hasColumn('sales', 'remaining_balance')) {
                $transformedSale['remainingBalance'] = (float) ($sale->remaining_balance ?? 0);
            }
            
            if (Schema::hasColumn('sales', 'reversal_reason')) {
                $transformedSale['reversalReason'] = $sale->reversal_reason;
            }
            
            if (Schema::hasColumn('sales', 'reversed_at')) {
                $transformedSale['reversedAt'] = $sale->reversed_at ? $sale->reversed_at->toISOString() : null;
            }
            
            \Log::info('DEBUG: Venta transformada exitosamente');
            
            return response()->json([
                'success' => true,
                'message' => 'Venta creada exitosamente',
                'data' => $transformedSale,
                'customer_created' => $customer->wasRecentlyCreated
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('ERROR en SaleController::store', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la venta: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $sale = Sale::where('user_id', $userId)->findOrFail($id);
        return $sale;
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $sale = Sale::where('user_id', $userId)->findOrFail($id);
        $sale->update($request->only(['total', 'created_at', 'updated_at']));
        return $sale;
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $sale = Sale::where('user_id', $userId)->findOrFail($id);
        $sale->delete();
        return response()->json(['success' => true]);
    }

    public function reverse(Request $request, $id)
    {
        $userId = $request->user()->id;
        $sale = Sale::where('user_id', $userId)->findOrFail($id);
        
        // Validar que la venta esté completada
        if ($sale->status !== 'COMPLETED') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden reversar ventas completadas'
            ], 400);
        }
        
        // Marcar la venta como reversada
        $sale->update([
            'status' => 'REVERSED',
            'reversal_reason' => $request->reason,
            'reversed_at' => now()
        ]);
        
        // Si la venta tenía crédito, revertir el saldo del cliente
        if ($sale->remaining_balance > 0) {
            $customer = \App\Models\Customer::where('user_id', $userId)
                ->where('document', $sale->customer_document)
                ->first();
            
            if ($customer) {
                $customer->decrement('credit_balance', $sale->remaining_balance);
            }
        }
        
        // Revertir el stock de productos
        if ($sale->items) {
            foreach ($sale->items as $item) {
                $product = \App\Models\Product::find($item['productId']);
                if ($product && $product->stock !== null) {
                    $product->increment('stock', $item['quantity']);
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Venta reversada exitosamente'
        ]);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        
        // Estadísticas de ventas - SOLO DINERO REALMENTE RECIBIDO
        $monthlySales = Sale::where('user_id', $userId)
            ->whereMonth('sale_date', Carbon::now()->month)
            ->selectRaw('SUM(total - COALESCE(remaining_balance, 0)) as paid_amount')
            ->value('paid_amount');
            
        $weeklySales = Sale::where('user_id', $userId)
            ->whereBetween('sale_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->selectRaw('SUM(total - COALESCE(remaining_balance, 0)) as paid_amount')
            ->value('paid_amount');
            
        // Estadísticas de compras
        $monthlyPurchases = Purchase::where('user_id', $userId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('amount'); // Cambiado de 'total' a 'amount' (campo correcto en Purchase)
            
        // Cálculo de ganancia/pérdida - Basado en dinero realmente recibido
        $profitLoss = $monthlySales - $monthlyPurchases;
        
        return response()->json([
            'data' => [
                'monthlySales' => $monthlySales,
                'weeklySales' => $weeklySales,
                'monthlyPurchases' => $monthlyPurchases,
                'profitLoss' => $profitLoss
            ]
        ]);
    }
}
