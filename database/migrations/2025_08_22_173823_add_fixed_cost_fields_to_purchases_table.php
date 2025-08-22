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
        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('fixed_cost_id')->nullable()->after('notes');
            $table->boolean('is_partial_payment')->default(false)->after('fixed_cost_id');
            
            $table->foreign('fixed_cost_id')->references('id')->on('fixed_costs')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['fixed_cost_id']);
            $table->dropColumn(['fixed_cost_id', 'is_partial_payment']);
        });
    }
};
