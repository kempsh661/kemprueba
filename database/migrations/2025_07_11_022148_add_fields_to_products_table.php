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
        Schema::table('products', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->unsignedBigInteger('category_id')->nullable()->after('code');
            $table->decimal('cost', 12, 2)->default(0)->after('price');
            $table->decimal('profit_margin', 5, 2)->default(30)->after('cost');
            
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['code', 'category_id', 'cost', 'profit_margin']);
        });
    }
};
