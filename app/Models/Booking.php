<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Бронь слота. Отмена заполняет cancelled_at, строка остаётся для истории.
 *
 * @property int $id
 * @property int $slot_id
 * @property string $customer_name
 * @property string $customer_email
 * @property CarbonImmutable|null $cancelled_at
 */
class Booking extends Model
{
    protected $fillable = ['slot_id', 'customer_name', 'customer_email'];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
