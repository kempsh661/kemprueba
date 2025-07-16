<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $sales = Sale::where('user_id', $userId)->get();
        
        // Transformar las ventas para que coincidan con la estructura esperada por el frontend
        $transformedSales = $sales->map(function ($sale) {
            // Calcular montos de efectivo y crédito para facturas
            $cashAmount = 0;
            $creditAmount = 0;
            
            if ($sale->payment_method === 'combined') {
                $cashAmount = (float) ($sale->cash_received ?? 0);
                $creditAmount = (float) ($sale->remaining_balance ?? 0);
            } elseif ($sale->payment_method === 'credit') {
                $creditAmount = (float) ($sale->total);
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
                'status' => 'COMPLETED', // Por defecto, las ventas están completadas
                'createdAt' => $sale->created_at,
                'user' => [
                    'name' => $sale->user->name ?? 'Usuario'
                ],
                'details' => $sale->items ? array_map(function ($item) {
                    // Buscar el producto real para obtener su nombre
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
        });
        
        return response()->json([
            'success' => true,
            'data' => $transformedSales
        ]);
    }

    public function store(Request $request)
    {
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
        ]);
        
        // Verificar si el cliente ya existe, si no, crearlo automáticamente
        $customer = \App\Models\Customer::where('user_id', $userId)
            ->where('document', $request->customerDocument)
            ->first();
            
        if (!$customer) {
            $customer = \App\Models\Customer::create([
                'user_id' => $userId,
                'name' => $request->customerName,
                'document' => $request->customerDocument,
                'email' => $request->customerEmail,
                'phone' => $request->customerPhone,
                'address' => null, // Se puede agregar después si es necesario
            ]);
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
            'payments' => $request->payments,
            'sale_date' => $request->date ?? now()->toDateString(),
            'items' => $request->items,
        ];
        
        // Manejar diferentes tipos de pago
        if ($request->paymentMethod === 'credit') {
            // Venta completamente a crédito
            $saleData['remaining_balance'] = $request->total;
            
            // Actualizar el saldo del cliente
            $customer->increment('credit_balance', $request->total);
        } elseif ($request->paymentMethod === 'combined') {
            // Venta combinada: parte en efectivo/transferencia, parte a crédito
            $paidAmount = $request->cashReceived ?? 0;
            $creditAmount = $request->total - $paidAmount;
            
            if ($creditAmount > 0) {
                $saleData['remaining_balance'] = $creditAmount;
                $saleData['cash_received'] = $paidAmount;
                
                // Actualizar el saldo del cliente
                $customer->increment('credit_balance', $creditAmount);
            }
        }
        
        // Crear la venta
        $sale = Sale::create($saleData);
        
        // Actualizar stock de productos si es necesario
        foreach ($request->items as $item) {
            $product = \App\Models\Product::find($item['productId']);
            if ($product && $product->stock !== null) {
                $product->decrement('stock', $item['quantity']);
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Venta creada exitosamente',
            'data' => $sale,
            'customer_created' => $customer->wasRecentlyCreated
        ], 201);
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

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        
        // Estadísticas de ventas
        $monthlySales = Sale::where('user_id', $userId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('total');
            
        $weeklySales = Sale::where('user_id', $userId)
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->sum('total');
            
        // Estadísticas de compras
        $monthlyPurchases = Purchase::where('user_id', $userId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('total');
            
        // Cálculo de ganancia/pérdida
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
