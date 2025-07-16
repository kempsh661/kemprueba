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

        // Ordenamiento
        $query->orderBy('date', 'desc');

        // Paginación
        $limit = $request->get('limit', 10);
        $purchases = $query->paginate($limit);

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

        $stats = [
            'totalPurchases' => (int) \App\Models\Purchase::where('user_id', $userId)->count(),
            'totalAmount' => (float) \App\Models\Purchase::where('user_id', $userId)->sum('amount'),
            'todayPurchases' => (float) \App\Models\Purchase::where('user_id', $userId)
                ->whereDate('date', $today)
                ->sum('amount'),
            'todayCount' => (int) \App\Models\Purchase::where('user_id', $userId)
                ->whereDate('date', $today)
                ->count(),
            'purchasesByCategory' => \App\Models\Purchase::where('user_id', $userId)
                ->select('category', \DB::raw('SUM(amount) as totalAmount'), \DB::raw('COUNT(*) as count'))
                ->whereNotNull('category')
                ->groupBy('category')
                ->get()
        ];

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
