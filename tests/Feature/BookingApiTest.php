<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Два обязательных сценария: бронирование свободного будущего слота и последовательный конфликт на уже занятом слоте.
 */
class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-03-10 12:00:00', 'Europe/Istanbul'));
    }

    public function test_free_future_slot_is_booked(): void
    {
        $slot = $this->createSlotStartingAt(now()->addDay()->setTime(10, 0));

        $response = $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'customer_name' => 'Alice',
            'customer_email' => 'alice@example.com',
        ]);

        $response->assertCreated()->assertExactJsonStructure(['id']);
        $bookingId = $response->json('id');
        $this->assertIsInt($bookingId);

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'slot_id' => $slot->id,
            'customer_name' => 'Alice',
            'customer_email' => 'alice@example.com',
            'cancelled_at' => null,
        ]);
        $this->assertSame(1, $this->activeBookingsCount($slot));
    }

    public function test_second_booking_of_booked_slot_gets_conflict_sequentially(): void
    {
        $slot = $this->createSlotStartingAt(now()->addDay()->setTime(11, 0));
        $firstId = $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'customer_name' => 'Alice',
            'customer_email' => 'alice@example.com',
        ])->assertCreated()->json('id');

        $response = $this->postJson('/api/bookings', [
            'slot_id' => $slot->id,
            'customer_name' => 'Bob',
            'customer_email' => 'bob@example.com',
        ]);

        $response->assertConflict()->assertExactJson(['message' => 'Слот уже занят другим бронированием.']);
        $this->assertSame(1, $this->activeBookingsCount($slot));
        $this->assertSame(1, Booking::query()->count());

        $first = Booking::query()->findOrFail($firstId);
        $this->assertSame('Alice', $first->customer_name);
        $this->assertSame('alice@example.com', $first->customer_email);
        $this->assertNull($first->cancelled_at);
    }

    private function createSlotStartingAt(CarbonImmutable $startsAt): Slot
    {
        return Slot::query()->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->addHour()]);
    }

    private function activeBookingsCount(Slot $slot): int
    {
        return Booking::query()->where('slot_id', $slot->id)->whereNull('cancelled_at')->count();
    }
}
