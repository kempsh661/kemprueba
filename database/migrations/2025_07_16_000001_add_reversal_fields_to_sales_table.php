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
            $table->string('status')->default('COMPLETED')->after('remaining_balance');
            $table->text('reversal_reason')->nullable()->after('status');
            $table->timestamp('reversed_at')->nullable()->after('reversal_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['status', 'reversal_reason', 'reversed_at']);
        });
    }
}; 