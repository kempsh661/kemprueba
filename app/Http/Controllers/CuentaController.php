<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use Illuminate\Http\Request;

class CuentaController extends Controller
{
    // Listar cuentas manuales del usuario autenticado
    public function index(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $limit = $request->query('limit', 50);
        $offset = $request->query('offset', 0);
        $cuentas = Cuenta::where('user_id', $userId)
            ->orderBy('date', 'desc') // El más reciente primero
            ->skip($offset)
            ->take($limit)
            ->get();
        $total = Cuenta::where('user_id', $userId)->count();

        $transformed = $cuentas->map(function ($cuenta) {
            return [
                'id' => $cuenta->id,
                'date' => $cuenta->date,
                'bankBalance' => (float) $cuenta->bank_balance,
                'nequiAleja' => (float) $cuenta->nequi_aleja,
                'nequiKem' => (float) $cuenta->nequi_kem,
                'cashBalance' => (float) $cuenta->cash_balance,
                'totalBalance' => (float) $cuenta->total_balance,
                'notes' => $cuenta->notes,
                'created_at' => $cuenta->created_at,
                'updated_at' => $cuenta->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformed,
            'pagination' => [
                'total' => $total,
                'limit' => (int)$limit,
                'offset' => (int)$offset
            ]
        ]);
    }

    // Guardar una nueva cuenta manual
    public function store(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $data = $request->only(['bank_balance', 'nequi_aleja', 'nequi_kem', 'cash_balance', 'notes']);
        $data['user_id'] = $userId;
        $data['bank_balance'] = (float)($data['bank_balance'] ?? 0);
        $data['nequi_aleja'] = (float)($data['nequi_aleja'] ?? 0);
        $data['nequi_kem'] = (float)($data['nequi_kem'] ?? 0);
        $data['cash_balance'] = (float)($data['cash_balance'] ?? 0);
        $data['total_balance'] = $data['bank_balance'] + $data['nequi_aleja'] + $data['nequi_kem'] + $data['cash_balance'];
        $data['date'] = now(); // Guardar fecha y hora actual
        $cuenta = Cuenta::create($data);

        $transformed = [
            'id' => $cuenta->id,
            'date' => $cuenta->date,
            'bankBalance' => (float) $cuenta->bank_balance,
            'nequiAleja' => (float) $cuenta->nequi_aleja,
            'nequiKem' => (float) $cuenta->nequi_kem,
            'cashBalance' => (float) $cuenta->cash_balance,
            'totalBalance' => (float) $cuenta->total_balance,
            'notes' => $cuenta->notes,
            'created_at' => $cuenta->created_at,
            'updated_at' => $cuenta->updated_at
        ];

        return response()->json([
            'success' => true,
            'data' => $transformed
        ], 201);
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
