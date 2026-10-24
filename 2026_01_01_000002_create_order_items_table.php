<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('product_name');
            $table->string('unit', 50);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('supplier_unit_cost', 12, 2)->default(0);
            $table->decimal('missing_quantity', 12, 3)->default(0);
            $table->string('shortage_resolution', 30)->nullable();
            $table->string('replacement_supplier')->nullable();
            $table->decimal('replacement_unit_cost', 12, 2)->nullable();
            $table->decimal('replacement_unit_price', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
