<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public static function numberForId(int $id): string
    {
        return 'SUP-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public const STATUS_PURCHASING = 'purchasing';
    public const STATUS_AWAITING_DELIVERY = 'awaiting_delivery';
    public const STATUS_SHORTAGE = 'shortage';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'delivery_location',
        'notes',
        'status',
        'representative_id',
        'supplier_name',
        'supplier_reference',
        'purchasing_confirmed_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'purchasing_confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest();
    }

    public function deliveryPhotos(): HasMany
    {
        return $this->hasMany(OrderDeliveryPhoto::class)->with('user')->oldest();
    }

    public function getRevenueAttribute(): float
    {
        return round($this->items->sum(fn (OrderItem $item): float => $item->revenue()), 2);
    }

    public function getCostAttribute(): float
    {
        return round($this->items->sum(fn (OrderItem $item): float => $item->cost()), 2);
    }

    public function getProfitAttribute(): float
    {
        return round($this->revenue - $this->cost, 2);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PURCHASING => 'بانتظار المشتريات',
            self::STATUS_AWAITING_DELIVERY => 'لدى المندوب للتسليم',
            self::STATUS_SHORTAGE => 'نقص — بانتظار المشتريات',
            self::STATUS_CLOSED => 'مغلق',
            default => 'غير معروف',
        };
    }

    public function statusDetailLabel(): string
    {
        if ($this->status === self::STATUS_CLOSED) {
            $shortageItems = $this->items->filter(
                fn (OrderItem $item): bool => (float) $item->missing_quantity > 0,
            );
            $outcomes = [];

            if ($shortageItems->contains(fn (OrderItem $item): bool => $item->shortage_resolution === 'alternate_supplier')) {
                $outcomes[] = 'أُغلق بعد توريد النواقص';
            }
            if ($shortageItems->contains(fn (OrderItem $item): bool => $item->shortage_resolution === 'same_supplier')) {
                $outcomes[] = 'أُغلق بعد توريد النواقص من نفس المورد';
            }
            if ($shortageItems->contains(fn (OrderItem $item): bool => $item->shortage_resolution === 'customer')) {
                $outcomes[] = 'أُغلق بنواقص دبّرها العميل';
            }

            return $outcomes ? implode('، ', $outcomes) : 'أُغلق بعد التسليم الكامل';
        }

        if ($this->status === self::STATUS_SHORTAGE) {
            return 'محوّل للمشتريات لمعالجة النقص';
        }

        if ($this->status === self::STATUS_AWAITING_DELIVERY) {
            $hasReplacementShortage = $this->items->contains(
                fn (OrderItem $item): bool => in_array($item->shortage_resolution, ['same_supplier', 'alternate_supplier'], true),
            );

            return $hasReplacementShortage
                ? 'محوّل للمندوب لاستلام النواقص'
                : 'محوّل للمندوب للتسليم';
        }

        return 'بانتظار تأكيد المشتريات';
    }
}
