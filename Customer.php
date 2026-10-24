<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    public function customerNumber(): string
    {
        return 'CUS-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    protected $fillable = [
        'name',
        'phone',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
