<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    public function sales(Request $request)
    {
        $userId = $request->user()->id;
        
        // Obtener ventas a crédito y combinadas (con saldo pendiente) agrupadas por cliente
        $creditSales = Sale::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('payment_method', 'credit')
                      ->orWhere('payment_method', 'combined');
            })
            ->where('remaining_balance', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('customer_document')
            ->map(function ($customerSales, $document) {
                $firstSale = $customerSales->first();
                $totalRemainingBalance = $customerSales->sum('remaining_balance');
                $totalSales = $customerSales->sum('total');
                $totalCashReceived = $customerSales->sum('cash_received');
                
                // Obtener todos los detalles de productos
                $allDetails = [];
                foreach ($customerSales as $sale) {
                    // Asegurar que items sea un array
                    $items = $sale->items;
                    
                    if (is_string($items)) {
                        $items = json_decode($items, true);
                    }
                    
                    if (is_array($items) && !empty($items)) {
                        foreach ($items as $item) {
                            if (is_array($item) && isset($item['productId'])) {
                                $product = \App\Models\Product::find($item['productId'] ?? 0);
                                $allDetails[] = [
                                    'id' => $item['productId'] ?? 0,
                                    'productId' => $item['productId'] ?? 0,
                                    'quantity' => $item['quantity'] ?? 0,
                                    'price' => (float) ($item['price'] ?? 0),
                                    'product' => [
                                        'name' => $product ? $product->name : 'Producto',
                                        'code' => $product ? $product->code : 'PROD'
                                    ]
                                ];
                            }
                        }
                    }
                }
                
                return [
                    'customerName' => $firstSale->customer_name ?? 'Cliente no registrado',
                    'customerDocument' => $document,
                    'customerPhone' => $firstSale->customer_phone,
                    'customerEmail' => $firstSale->customer_email,
                    'totalSales' => (float) $totalSales,
                    'totalRemainingBalance' => (float) $totalRemainingBalance,
                    'totalCashReceived' => (float) $totalCashReceived,
                    'saleCount' => $customerSales->count(),
                    'lastSaleDate' => $customerSales->first()->created_at,
                    'status' => $totalRemainingBalance > 0 ? 'pending' : 'paid',
                    'details' => $allDetails,
                    'sales' => $customerSales->map(function ($sale) {
                        return [
                            'id' => $sale->id,
                            'total' => (float) $sale->total,
                            'remainingBalance' => (float) ($sale->remaining_balance ?? $sale->total),
                            'paymentMethod' => $sale->payment_method,
                            'cashReceived' => $sale->payment_method === 'combined' ? (float) ($sale->cash_received ?? 0) : 0,
                            'createdAt' => $sale->created_at,
                            'transactionNumber' => $sale->transaction_number
                        ];
                    })
                ];
            })
            ->values();

        // Calcular total de créditos pendientes
        $totalPendingCredits = Sale::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('payment_method', 'credit')
                      ->orWhere('payment_method', 'combined');
            })
            ->where('remaining_balance', '>', 0)
            ->sum('remaining_balance');

        return response()->json([
            'data' => $creditSales,
            'totalPendingCredits' => (float) $totalPendingCredits
        ]);
    }

    public function payments(Request $request)
    {
        $userId = $request->user()->id;
        
        // Obtener pagos de crédito
        $creditPayments = DB::table('credit_payments')
            ->join('sales', 'credit_payments.sale_id', '=', 'sales.id')
            ->where('sales.user_id', $userId)
            ->select([
                'credit_payments.id',
                'credit_payments.sale_id',
                'credit_payments.amount',
                'credit_payments.payment_method',
                'credit_payments.transaction_number',
                'credit_payments.created_at',
                'sales.customer_name'
            ])
            ->orderBy('credit_payments.created_at', 'desc')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'saleId' => $payment->sale_id,
                    'amount' => $payment->amount,
                    'paymentMethod' => $payment->payment_method,
                    'transactionNumber' => $payment->transaction_number,
                    'createdAt' => $payment->created_at,
                    'customerName' => $payment->customer_name
                ];
            });

        return response()->json(['data' => $creditPayments]);
    }

    public function storePayment(Request $request)
    {
        $userId = $request->user()->id;
        
        $request->validate([
            'customerDocument' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'paymentMethod' => 'required|in:cash,card,transfer',
            'transactionNumber' => 'nullable|string',
            'cashReceived' => 'nullable|numeric|min:0'
        ]);

        // Obtener todas las ventas a crédito del cliente ordenadas por fecha (más antigua primero)
        $customerSales = Sale::where('user_id', $userId)
            ->where('customer_document', $request->customerDocument)
            ->where(function ($query) {
                $query->where('payment_method', 'credit')
                      ->orWhere('payment_method', 'combined');
            })
            ->where('remaining_balance', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($customerSales->isEmpty()) {
            return response()->json(['error' => 'No se encontraron ventas pendientes para este cliente'], 400);
        }

        // Calcular el total pendiente del cliente
        $totalRemainingBalance = $customerSales->sum('remaining_balance');
        
        // Verificar que el monto no exceda el total pendiente
        if ($request->amount > $totalRemainingBalance) {
            return response()->json([
                'error' => 'El monto excede el saldo total pendiente',
                'totalRemainingBalance' => $totalRemainingBalance
            ], 400);
        }

        DB::beginTransaction();
        try {
            $remainingAmount = $request->amount;
            $processedSales = [];
            $totalProcessed = 0;

            // Distribuir el pago entre las ventas (orden cronológico)
            foreach ($customerSales as $sale) {
                if ($remainingAmount <= 0) break;

                $saleRemainingBalance = $sale->remaining_balance ?? $sale->total;
                $amountToPay = min($remainingAmount, $saleRemainingBalance);

                // Crear el registro de pago
                DB::table('credit_payments')->insert([
                    'sale_id' => $sale->id,
                    'amount' => $amountToPay,
                    'payment_method' => $request->paymentMethod,
                    'transaction_number' => $request->transactionNumber,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Actualizar el saldo pendiente de la venta
                $newRemainingBalance = $saleRemainingBalance - $amountToPay;
                $sale->update([
                    'remaining_balance' => $newRemainingBalance
                ]);

                $processedSales[] = [
                    'saleId' => $sale->id,
                    'amountPaid' => $amountToPay,
                    'remainingBalance' => $newRemainingBalance
                ];

                $totalProcessed += $amountToPay;
                $remainingAmount -= $amountToPay;
            }

            DB::commit();

            // Calcular el nuevo total pendiente del cliente
            $newTotalRemainingBalance = $totalRemainingBalance - $totalProcessed;

            return response()->json([
                'success' => true,
                'message' => 'Pago procesado exitosamente',
                'amountProcessed' => $totalProcessed,
                'totalRemainingBalance' => $newTotalRemainingBalance,
                'processedSales' => $processedSales,
                'customerDocument' => $request->customerDocument
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Error al procesar el pago: ' . $e->getMessage()], 500);
        }
    }
}
