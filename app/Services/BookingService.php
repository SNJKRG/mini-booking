<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BookingService
{
    private const UNIQUE_VIOLATION = '23505';

    private const ACTIVE_BOOKING_INDEX = 'bookings_one_active_per_slot';

    /**
     * Бронирует слот. Блокировка строки слота выстраивает конкурирующие запросы в очередь,
     * частичный уникальный индекс остаётся окончательной защитой.
     *
     * Время читается только после получения блокировки: пока ждали её, слот мог начаться.
     * Нарушение уникальности обрабатывается вне замыкания, после отката DB::transaction,
     * поэтому ни один SQL-запрос не выполняется внутри прерванной транзакции PostgreSQL.
     *
     * @throws SlotUnavailableException если слот уже начался или у него есть активная бронь
     * @throws QueryException при любой другой ошибке БД
     */
    public function book(int $slotId, string $customerName, string $customerEmail): Booking
    {
        try {
            return DB::transaction(function () use ($slotId, $customerName, $customerEmail): Booking {
                $slot = Slot::query()->lockForUpdate()->findOrFail($slotId);

                if ($slot->hasStartedAt(now())) {
                    throw SlotUnavailableException::alreadyStarted();
                }

                if ($slot->bookings()->whereNull('cancelled_at')->exists()) {
                    throw SlotUnavailableException::alreadyBooked();
                }

                return $slot->bookings()->create([
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isActiveBookingConflict($exception)) {
                throw SlotUnavailableException::alreadyBooked();
            }

            throw $exception;
        }
    }

    /**
     * Отменяет бронь по её ID. Идемпотентно: у уже отменённой брони cancelled_at не меняется.
     */
    public function cancel(Booking $booking): void
    {
        Booking::query()
            ->whereKey($booking->id)
            ->whereNull('cancelled_at')
            ->update(['cancelled_at' => now()]);
    }

    private function isActiveBookingConflict(QueryException $exception): bool
    {
        return $exception->getCode() === self::UNIQUE_VIOLATION
            && str_contains($exception->getMessage(), self::ACTIVE_BOOKING_INDEX);
    }
}
