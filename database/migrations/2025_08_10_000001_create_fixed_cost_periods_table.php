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
        Schema::create('fixed_cost_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('fixed_cost_id');
            $table->string('month', 7); // YYYY-MM
            $table->boolean('is_active')->default(true);
            $table->boolean('is_paid')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'fixed_cost_id', 'month']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('fixed_cost_id')->references('id')->on('fixed_costs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_cost_periods');
    }
};











