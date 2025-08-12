<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $query = Purchase::where('user_id', $userId)->with('user');

        // Log para debugging
        \Log::info('PurchaseController index called', [
            'userId' => $userId,
            'search' => $request->get('search'),
            'category' => $request->get('category'),
            'month' => $request->get('month'),
            'startDate' => $request->get('startDate'),
            'endDate' => $request->get('endDate')
        ]);

        // Filtros
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('concept', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Filtro por mes (YYYY-MM) o por rango de fechas (startDate, endDate)
        if ($request->has('month') && $request->month) {
            try {
                $startOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();
                $endOfMonth = \Carbon\Carbon::createFromFormat('Y-m', $request->month)->endOfMonth();
                
                \Log::info('Month filter applied', [
                    'month' => $request->month,
                    'startOfMonth' => $startOfMonth->toDateTimeString(),
                    'endOfMonth' => $endOfMonth->toDateTimeString()
                ]);
                
                $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
            } catch (\Exception $e) {
                \Log::error('Error applying month filter', [
                    'month' => $request->month,
                    'error' => $e->getMessage()
                ]);
                // Si el formato no es válido, ignorar el filtro de mes
            }
        } elseif ($request->has('startDate') && $request->has('endDate') && $request->startDate && $request->endDate) {
            try {
                $startDate = \Carbon\Carbon::parse($request->startDate)->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->endDate)->endOfDay();
                $query->whereBetween('date', [$startDate, $endDate]);
            } catch (\Exception $e) {
                // Ignorar si las fechas no son válidas
            }
        }

        // Ordenamiento
        $query->orderBy('date', 'desc');

        // Log de la query final
        \Log::info('Final query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        // Paginación
        $limit = $request->get('limit', 10);
        $purchases = $query->paginate($limit);

        \Log::info('Query results', [
            'total' => $purchases->total(),
            'perPage' => $purchases->perPage(),
            'currentPage' => $purchases->currentPage(),
            'lastPage' => $purchases->lastPage()
        ]);

        return response()->json([
            'data' => $purchases->items(),
            'pagination' => [
                'currentPage' => $purchases->currentPage(),
                'totalPages' => $purchases->lastPage(),
                'totalItems' => $purchases->total(),
                'perPage' => $purchases->perPage(),
                'hasNextPage' => $purchases->hasMorePages(),
                'hasPrevPage' => $purchases->previousPageUrl() !== null,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->only(['amount', 'date', 'category', 'concept', 'notes']);
        $data['user_id'] = $userId;
        
        // Validar fecha
        if (isset($data['date'])) {
            $data['date'] = Carbon::parse($data['date']);
        }
        
        $purchase = Purchase::create($data);
        $purchase->load('user');
        
        return response()->json($purchase, 201);
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;
        $purchase = Purchase::where('user_id', $userId)->with('user')->findOrFail($id);
        return $purchase;
    }

    public function update(Request $request, $id)
    {
        $userId = $request->user()->id;
        $purchase = Purchase::where('user_id', $userId)->findOrFail($id);
        
        $data = $request->only(['amount', 'date', 'category', 'concept', 'notes']);
        
        // Validar fecha
        if (isset($data['date'])) {
            $data['date'] = Carbon::parse($data['date']);
        }
        
        $purchase->update($data);
        $purchase->load('user');
        
        return $purchase;
    }

    public function destroy(Request $request, $id)
    {
        $userId = $request->user()->id;
        $purchase = Purchase::where('user_id', $userId)->findOrFail($id);
        $purchase->delete();
        return response()->json(['success' => true]);
    }

    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $today = \Carbon\Carbon::today();
        $month = $request->query('month'); // formato YYYY-MM
        $startDate = null;
        $endDate = null;
        
        if ($month) {
            try {
                $startDate = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $endDate = \Carbon\Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            } catch (\Exception $e) {
                $startDate = null;
                $endDate = null;
            }
        } elseif ($request->has('startDate') && $request->has('endDate')) {
            try {
                $startDate = \Carbon\Carbon::parse($request->query('startDate'))->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->query('endDate'))->endOfDay();
            } catch (\Exception $e) {
                $startDate = null;
                $endDate = null;
            }
        }

        $baseQuery = \App\Models\Purchase::where('user_id', $userId);
        if ($startDate && $endDate) {
            $baseQuery = $baseQuery->whereBetween('date', [$startDate, $endDate]);
        }

        // Métricas principales
        $stats = [
            // Si hay periodo seleccionado (mes/rango) se limita al periodo; si no, es global
            'totalPurchases' => (int) (clone $baseQuery)->count(),
            'totalAmount' => (float) (clone $baseQuery)->sum('amount'),
            // Hoy siempre respecto a la fecha actual
            'todayPurchases' => (float) \App\Models\Purchase::where('user_id', $userId)
                ->whereDate('date', $today)
                ->sum('amount'),
            'todayCount' => (int) \App\Models\Purchase::where('user_id', $userId)
                ->whereDate('date', $today)
                ->count(),
            'purchasesByCategory' => (clone $baseQuery)
                ->select('category', \DB::raw('SUM(amount) as totalAmount'), \DB::raw('COUNT(*) as count'))
                ->whereNotNull('category')
                ->groupBy('category')
                ->orderBy(\DB::raw('SUM(amount)'), 'desc')
                ->get(),
        ];

        // Promedio por compra
        $stats['averageAmount'] = $stats['totalPurchases'] > 0
            ? round($stats['totalAmount'] / $stats['totalPurchases'], 2)
            : 0.0;

        // Top conceptos (artículos) del periodo
        $stats['topConcepts'] = (clone $baseQuery)
            ->select('concept', \DB::raw('SUM(amount) as totalAmount'), \DB::raw('COUNT(*) as count'))
            ->whereNotNull('concept')
            ->groupBy('concept')
            ->orderBy(\DB::raw('SUM(amount)'), 'desc')
            ->limit(10)
            ->get();

        // Totales diarios y semanales (compatibles entre motores) calculados en PHP
        $periodPurchases = (clone $baseQuery)->get(['date', 'amount']);
        $dailyTotals = [];
        $weeklyTotals = [];
        foreach ($periodPurchases as $p) {
            $d = $p->date instanceof \Carbon\Carbon ? $p->date : \Carbon\Carbon::parse($p->date);
            $dayKey = $d->toDateString();
            if (!isset($dailyTotals[$dayKey])) {
                $dailyTotals[$dayKey] = ['day' => $dayKey, 'totalAmount' => 0.0, 'count' => 0];
            }
            $dailyTotals[$dayKey]['totalAmount'] += (float) $p->amount;
            $dailyTotals[$dayKey]['count'] += 1;

            // Índice de semana del mes: 1..5
            $weekIndex = intdiv($d->day - 1, 7) + 1;
            if (!isset($weeklyTotals[$weekIndex])) {
                $weeklyTotals[$weekIndex] = ['week' => $weekIndex, 'totalAmount' => 0.0, 'count' => 0];
            }
            $weeklyTotals[$weekIndex]['totalAmount'] += (float) $p->amount;
            $weeklyTotals[$weekIndex]['count'] += 1;
        }
        ksort($weeklyTotals);
        ksort($dailyTotals);
        $stats['weeklyTotals'] = array_values($weeklyTotals);
        $stats['dailyTotals'] = array_values($dailyTotals);

        // Día pico
        $stats['peakDay'] = null;
        if (!empty($dailyTotals)) {
            $maxDay = null;
            foreach ($dailyTotals as $dt) {
                if ($maxDay === null || $dt['totalAmount'] > $maxDay['totalAmount']) {
                    $maxDay = $dt;
                }
            }
            if ($maxDay) {
                $stats['peakDay'] = ['date' => $maxDay['day'], 'amount' => (float) $maxDay['totalAmount']];
            }
        }

        if ($startDate && $endDate) {
            $stats['startDate'] = $startDate->toDateString();
            $stats['endDate'] = $endDate->toDateString();
        }

        return response()->json(['data' => $stats]);
    }

    public function categories(Request $request)
    {
        $userId = $request->user()->id;
        $categories = \App\Models\Purchase::where('user_id', $userId)
            ->whereNotNull('category')
            ->select('category as name')
            ->distinct()
            ->get();

        return response()->json(['data' => $categories]);
    }
}
