<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->text('delivery_location');
            $table->text('notes')->nullable();
            $table->string('status')->index();
            $table->foreignId('representative_id')->constrained('users')->restrictOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->timestamp('purchasing_confirmed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
