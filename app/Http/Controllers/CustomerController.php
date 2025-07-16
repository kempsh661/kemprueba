<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $customers = Customer::where('user_id', $userId)->get();
        
        // Agregar información de total de gastos y créditos pendientes
        $customersWithStats = $customers->map(function ($customer) use ($userId) {
            // Calcular número de ventas del cliente
            $salesCount = \App\Models\Sale::where('user_id', $userId)
                ->where('customer_document', $customer->document)
                ->count();
            
            // Calcular total gastado del cliente
            $totalSpent = \App\Models\Sale::where('user_id', $userId)
                ->where('customer_document', $customer->document)
                ->sum('total');
            
            // Calcular créditos pendientes del cliente
            $pendingCredits = \App\Models\Sale::where('user_id', $userId)
                ->where('customer_document', $customer->document)
                ->where(function ($query) {
                    $query->where('payment_method', 'credit')
                          ->orWhere('payment_method', 'combined');
                })
                ->where('remaining_balance', '>', 0)
                ->sum('remaining_balance');
            
            return [
                'id' => $customer->id,
                'user_id' => $customer->user_id,
                'name' => $customer->name,
                'document' => $customer->document,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'credit_balance' => (float) $customer->credit_balance,
                'sales_count' => (int) $salesCount,
                'total_spent' => (float) $totalSpent,
                'pending_credits' => (float) $pendingCredits,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
            ];
        });
        
        return response()->json([
            'data' => $customersWithStats
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'document' => 'required|string|max:50|unique:customers,document',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $userId = $request->user()->id;
        $data = $request->only(['name', 'document', 'email', 'phone', 'address']);
        $data['user_id'] = $userId;
        
        $customer = Customer::create($data);
        return response()->json($customer, 201);
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $customer = Customer::where('user_id', $userId)->findOrFail($id);
        return $customer;
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $customer = Customer::where('user_id', $userId)->findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'document' => 'required|string|max:50|unique:customers,document,' . $id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);
        
        $customer->update($request->only(['name', 'document', 'email', 'phone', 'address']));
        return $customer;
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $customer = Customer::where('user_id', $userId)->findOrFail($id);
        $customer->delete();
        return response()->json(['success' => true]);
    }

    public function customerSales(Request $request, $id)
    {
        $userId = $request->user()->id;
        $customer = Customer::where('user_id', $userId)->findOrFail($id);
        $sales = \App\Models\Sale::where('user_id', $userId)
            ->where('customer_document', $customer->document)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'total', 'payment_method', 'status', 'created_at', 'details']);

        // También devolver el saldo de crédito actualizado
        return response()->json([
            'data' => $sales,
            'credit_balance' => (float) $customer->credit_balance,
        ]);
    }
}
