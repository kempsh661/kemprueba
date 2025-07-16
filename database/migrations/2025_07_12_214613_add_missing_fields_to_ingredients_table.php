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
            $table->string('portion_unit', 50)->nullable()->after('portion_quantity');
            $table->integer('min_stock')->default(0)->after('stock');
            $table->integer('max_stock')->nullable()->after('min_stock');
            $table->string('supplier')->nullable()->after('max_stock');
            $table->string('location')->nullable()->after('supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['portion_unit', 'min_stock', 'max_stock', 'supplier', 'location']);
        });
    }
};
