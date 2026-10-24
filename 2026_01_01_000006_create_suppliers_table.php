<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255)->unique();
            $table->string('phone', 50)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $names = [];

        foreach (DB::table('orders')->whereNotNull('supplier_name')->distinct()->pluck('supplier_name') as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $names[$name] = $name;
            }
        }

        if (Schema::hasTable('order_items')) {
            foreach (DB::table('order_items')->whereNotNull('replacement_supplier')->distinct()->pluck('replacement_supplier') as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $names[$name] = $name;
                }
            }
        }

        if ($names !== []) {
            DB::table('suppliers')->insertOrIgnore(array_map(
                fn (string $name): array => [
                    'name' => $name,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                array_values($names),
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
