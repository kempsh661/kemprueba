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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->text('description')->nullable()->after('code');
            $table->decimal('quantity_purchased', 10, 2)->default(0)->after('unit');
            $table->decimal('purchase_value', 12, 2)->default(0)->after('quantity_purchased');
            $table->decimal('portion_quantity', 10, 2)->default(0)->after('purchase_value');
            $table->decimal('portion_cost', 10, 2)->default(0)->after('portion_quantity');
            $table->decimal('price', 12, 2)->default(0)->after('portion_cost');
            
            // Renombrar el campo cost existente si existe
            if (Schema::hasColumn('ingredients', 'cost')) {
                $table->dropColumn('cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['code', 'description', 'quantity_purchased', 'purchase_value', 'portion_quantity', 'portion_cost', 'price']);
            $table->decimal('cost', 12, 2)->default(0);
        });
    }
};
