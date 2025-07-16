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
        Schema::table('fixed_costs', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
            $table->text('description')->nullable()->after('amount');
            $table->integer('due_date')->nullable()->after('frequency');
            $table->string('category')->nullable()->after('due_date');
            $table->boolean('is_paid')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_costs', function (Blueprint $table) {
            $table->dropColumn(['name', 'description', 'due_date', 'category', 'is_paid']);
        });
    }
};
