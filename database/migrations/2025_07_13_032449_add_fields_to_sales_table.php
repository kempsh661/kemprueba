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
            $table->unsignedBigInteger('customer_id')->nullable()->after('user_id');
            $table->enum('payment_method', ['cash', 'card', 'credit', 'transfer'])->default('cash')->after('total');
            $table->decimal('remaining_balance', 12, 2)->nullable()->after('payment_method');
            $table->json('details')->nullable()->after('remaining_balance');
            
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'payment_method', 'remaining_balance', 'details']);
        });
    }
};
