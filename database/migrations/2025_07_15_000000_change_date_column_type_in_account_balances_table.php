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
        // Cambiar la columna 'date' de timestamp a date
        Schema::table('account_balances', function (Blueprint $table) {
            $table->date('date')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver a timestamp si se revierte
        Schema::table('account_balances', function (Blueprint $table) {
            $table->timestamp('date')->useCurrent()->change();
        });
    }
}; 