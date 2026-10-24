<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $orders = DB::table('orders')
            ->whereNull('customer_id')
            ->whereNotNull('customer_name')
            ->orderBy('id')
            ->get(['id', 'customer_name', 'customer_phone']);

        foreach ($orders as $order) {
            $customerQuery = DB::table('customers')->where('name', $order->customer_name);
            if ($order->customer_phone === null) {
                $customerQuery->whereNull('phone');
            } else {
                $customerQuery->where('phone', $order->customer_phone);
            }

            $customerId = $customerQuery->value('id');
            if ($customerId === null) {
                $customerId = DB::table('customers')->insertGetId([
                    'name' => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('orders')->where('id', $order->id)->update(['customer_id' => $customerId]);
        }
    }

    public function down(): void
    {
        DB::table('orders')->whereNotNull('customer_id')->update(['customer_id' => null]);
    }
};
