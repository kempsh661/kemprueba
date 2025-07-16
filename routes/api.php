<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountBalanceController;
use App\Http\Controllers\SalesStatsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\FixedCostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\UserController;

// Rutas de autenticación
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('api.auth:sanctum')->post('logout', [AuthController::class, 'logout']);



// Proteger las rutas de balances y stats
Route::middleware('api.auth:sanctum')->group(function () {
    Route::get('account-balances', [AccountBalanceController::class, 'index']);
    Route::post('account-balances', [AccountBalanceController::class, 'store']);
    Route::get('account-balances/latest', [AccountBalanceController::class, 'latest']);
    Route::post('account-balances/close', [AccountBalanceController::class, 'closeCash']);
    Route::get('account-balances/debug-dates', [AccountBalanceController::class, 'debugDates']);
    Route::get('account-balances/debug-cash', [AccountBalanceController::class, 'debugCash']);
    Route::get('sales-stats', [SalesStatsController::class, 'index']);
    // Compras
    Route::get('purchases/stats', [PurchaseController::class, 'stats']);
    Route::get('purchases/categories', [PurchaseController::class, 'categories']);
    Route::apiResource('purchases', PurchaseController::class);
    // Ventas
    Route::apiResource('sales', SaleController::class);
    // Estadísticas de costos fijos
    Route::get('fixed-costs/stats', [FixedCostController::class, 'stats']);
    // Costos fijos
    Route::patch('fixed-costs/{id}/toggle-payment', [FixedCostController::class, 'togglePayment']);
    Route::apiResource('fixed-costs', FixedCostController::class);
    // Productos
    Route::apiResource('products', ProductController::class);
    // Categorías
    Route::apiResource('categories', CategoryController::class);
    // Clientes
    Route::apiResource('customers', CustomerController::class);
    Route::get('customers/{id}/sales', [CustomerController::class, 'customerSales']);
    // Inventario
    Route::apiResource('inventories', InventoryController::class);
    // Ingredientes
    Route::apiResource('ingredients', IngredientController::class);
    Route::get('ingredients/low-stock', [IngredientController::class, 'lowStock']);
    Route::post('ingredients/{id}/add-stock', [IngredientController::class, 'addStock']);
    Route::post('ingredients/{id}/reduce-stock', [IngredientController::class, 'reduceStock']);
    // Movimientos de stock
    Route::apiResource('stock-movements', StockMovementController::class);
    // Movimientos de inventario por producto
    Route::get('inventory/movements/product/{id}', [StockMovementController::class, 'productMovements']);
    // Créditos
    Route::get('credits/sales', [CreditController::class, 'sales']);
    Route::get('credits/payments', [CreditController::class, 'payments']);
    // Cuentas
    Route::apiResource('cuentas', CuentaController::class);
    // Cálculo manual de costos fijos para productos
    Route::post('product-costs/calculate-fixed-costs-manual', [ProductController::class, 'calculateFixedCostsManual']);
    // Usuarios
    Route::apiResource('users', UserController::class);
});
