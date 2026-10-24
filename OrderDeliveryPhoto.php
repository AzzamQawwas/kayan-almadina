<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDeliveryPhoto extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'path',
        'mime_type',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'shortage_reported' => 'إبلاغ عن نقص',
            'customer_shortage_closed' => 'إغلاق الطلب بنقص تكفّل به العميل',
            'order_closed' => 'تأكيد التسليم الكامل',
            default => 'إجراء تسليم',
        };
    }
}
