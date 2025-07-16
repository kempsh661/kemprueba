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
                    if ($sale->items) {
                        foreach ($sale->items as $item) {
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
            'saleId' => 'required|exists:sales,id',
            'amount' => 'required|numeric|min:0.01',
            'paymentMethod' => 'required|in:cash,card,transfer',
            'transactionNumber' => 'nullable|string',
            'cashReceived' => 'nullable|numeric|min:0'
        ]);

        // Verificar que la venta pertenece al usuario
        $sale = Sale::where('user_id', $userId)
            ->where('id', $request->saleId)
            ->firstOrFail();

        // Verificar que hay saldo pendiente
        $remainingBalance = $sale->remaining_balance ?? $sale->total;
        if ($remainingBalance <= 0) {
            return response()->json(['error' => 'Esta venta ya está pagada'], 400);
        }

        // Verificar que el monto no exceda el saldo pendiente
        if ($request->amount > $remainingBalance) {
            return response()->json(['error' => 'El monto excede el saldo pendiente'], 400);
        }

        DB::beginTransaction();
        try {
            // Crear el registro de pago
            DB::table('credit_payments')->insert([
                'sale_id' => $request->saleId,
                'amount' => $request->amount,
                'payment_method' => $request->paymentMethod,
                'transaction_number' => $request->transactionNumber,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Actualizar el saldo pendiente de la venta
            $newRemainingBalance = $remainingBalance - $request->amount;
            $sale->update([
                'remaining_balance' => $newRemainingBalance
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'remainingBalance' => $newRemainingBalance
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Error al procesar el pago'], 500);
        }
    }
}
