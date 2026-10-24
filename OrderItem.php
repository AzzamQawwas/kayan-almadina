<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'product_name',
        'unit',
        'quantity',
        'unit_price',
        'supplier_unit_cost',
        'missing_quantity',
        'shortage_resolution',
        'replacement_supplier',
        'replacement_unit_cost',
        'replacement_unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'supplier_unit_cost' => 'decimal:2',
            'missing_quantity' => 'decimal:3',
            'replacement_unit_cost' => 'decimal:2',
            'replacement_unit_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function revenue(): float
    {
        $quantity = (float) $this->quantity;
        $missing = (float) $this->missing_quantity;

        if ($this->shortage_resolution === 'customer') {
            return round(max(0, $quantity - $missing) * (float) $this->unit_price, 2);
        }

        if (in_array($this->shortage_resolution, ['same_supplier', 'alternate_supplier'], true)) {
            return round(($quantity - $missing) * (float) $this->unit_price + $missing * (float) $this->replacement_unit_price, 2);
        }

        return round($quantity * (float) $this->unit_price, 2);
    }

    public function cost(): float
    {
        $quantity = (float) $this->quantity;
        $missing = (float) $this->missing_quantity;

        if ($this->shortage_resolution === 'customer') {
            return round(max(0, $quantity - $missing) * (float) $this->supplier_unit_cost, 2);
        }

        if (in_array($this->shortage_resolution, ['same_supplier', 'alternate_supplier'], true)) {
            return round(($quantity - $missing) * (float) $this->supplier_unit_cost + $missing * (float) $this->replacement_unit_cost, 2);
        }

        return round($quantity * (float) $this->supplier_unit_cost, 2);
    }
}
