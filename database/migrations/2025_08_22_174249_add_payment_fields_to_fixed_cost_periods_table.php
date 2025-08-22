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
        Schema::table('fixed_cost_periods', function (Blueprint $table) {
            $table->decimal('partial_amount', 12, 2)->nullable()->after('is_paid');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('partial_amount');
            $table->text('notes')->nullable()->after('paid_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_cost_periods', function (Blueprint $table) {
            $table->dropColumn(['partial_amount', 'paid_amount', 'notes']);
        });
    }
};
