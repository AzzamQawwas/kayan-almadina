<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $names = ['قطعة', 'كيس', 'طن', 'متر', 'متر مربع', 'متر مكعب', 'كجم', 'شاحنة'];

        if (Schema::hasTable('order_items')) {
            $existingNames = DB::table('order_items')->distinct()->pluck('unit');
            foreach ($existingNames as $name) {
                $name = trim((string) $name);
                if ($name !== '' && ! in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        DB::table('product_units')->insertOrIgnore(array_map(
            fn (string $name): array => [
                'name' => $name,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $names,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
