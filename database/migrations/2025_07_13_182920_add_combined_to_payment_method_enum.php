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
            // Cambiar payment_method de enum a string para permitir 'combined'
            $table->string('payment_method')->default('cash')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Revertir a enum sin 'combined'
            $table->enum('payment_method', ['cash', 'card', 'credit', 'transfer'])->default('cash')->change();
        });
    }
};
