<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['order_number' => 'SUP-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);
            }
        });
    }

    public function down(): void
    {
        // Existing sequential order numbers cannot be restored to their former values.
    }
};
