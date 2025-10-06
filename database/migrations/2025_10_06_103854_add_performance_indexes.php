<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Índices para consultas de ventas por fecha
            $table->index(['user_id', 'sale_date'], 'idx_sales_user_sale_date');
            $table->index(['user_id', 'created_at'], 'idx_sales_user_created_at');
            
            // Índices para consultas de métodos de pago
            $table->index(['user_id', 'payment_method'], 'idx_sales_user_payment_method');
            
            // Índice para consultas de cliente
            $table->index(['user_id', 'customer_document'], 'idx_sales_user_customer');
            
            // Índice para consultas de saldo pendiente
            $table->index(['user_id', 'remaining_balance'], 'idx_sales_user_remaining_balance');
        });

        Schema::table('products', function (Blueprint $table) {
            // Índice para consultas de stock bajo
            $table->index(['user_id', 'stock'], 'idx_products_user_stock');
            
            // Índice para consultas por categoría
            $table->index(['user_id', 'category_id'], 'idx_products_user_category');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            // Índice para consultas de stock bajo
            $table->index(['user_id', 'stock'], 'idx_ingredients_user_stock');
        });

        Schema::table('purchases', function (Blueprint $table) {
            // Índices para consultas de compras por fecha
            $table->index(['user_id', 'date'], 'idx_purchases_user_date');
            
            // Índice para consultas por categoría
            $table->index(['user_id', 'category'], 'idx_purchases_user_category');
        });

        Schema::table('account_balances', function (Blueprint $table) {
            // Índices para consultas de balances
            $table->index(['user_id', 'date'], 'idx_balances_user_date');
            $table->index(['user_id', 'is_closed'], 'idx_balances_user_closed');
        });

        Schema::table('fixed_costs', function (Blueprint $table) {
            // Índices para consultas de costos fijos
            $table->index(['user_id', 'is_active'], 'idx_fixed_costs_user_active');
            $table->index(['user_id', 'frequency'], 'idx_fixed_costs_user_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_user_sale_date');
            $table->dropIndex('idx_sales_user_created_at');
            $table->dropIndex('idx_sales_user_payment_method');
            $table->dropIndex('idx_sales_user_customer');
            $table->dropIndex('idx_sales_user_remaining_balance');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_user_stock');
            $table->dropIndex('idx_products_user_category');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex('idx_ingredients_user_stock');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('idx_purchases_user_date');
            $table->dropIndex('idx_purchases_user_category');
        });

        Schema::table('account_balances', function (Blueprint $table) {
            $table->dropIndex('idx_balances_user_date');
            $table->dropIndex('idx_balances_user_closed');
        });

        Schema::table('fixed_costs', function (Blueprint $table) {
            $table->dropIndex('idx_fixed_costs_user_active');
            $table->dropIndex('idx_fixed_costs_user_frequency');
        });
    }
};