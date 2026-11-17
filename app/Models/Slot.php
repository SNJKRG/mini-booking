<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SlotStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Часовой слот единственной переговорной.
 *
 * @property int $id
 * @property \Carbon\CarbonImmutable $starts_at
 * @property \Carbon\CarbonImmutable $ends_at
 * @property-read Booking|null $activeBooking
 */
class Slot extends Model
{
    /**
     * Даты передаются в БД с явным смещением: сессия БД работает в UTC, и локальное «09:00» без смещения сохранилось бы как 09:00 UTC.
     */
    public const DATE_FORMAT = 'Y-m-d H:i:sP';

    protected $dateFormat = self::DATE_FORMAT;

    protected $fillable = ['starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBooking(): HasOne
    {
        return $this->hasOne(Booking::class)->whereNull('cancelled_at');
    }

    /**
     * Слот считается прошедшим с момента своего начала.
     */
    public function hasStartedAt(CarbonInterface $now): bool
    {
        return $this->starts_at->lessThanOrEqualTo($now);
    }

    /**
     * Приоритет статусов: past, затем booked, затем available. Требует загруженного activeBooking.
     */
    public function statusAt(CarbonInterface $now): SlotStatus
    {
        if ($this->hasStartedAt($now)) {
            return SlotStatus::Past;
        }

        return $this->activeBooking === null ? SlotStatus::Available : SlotStatus::Booked;
    }
}
