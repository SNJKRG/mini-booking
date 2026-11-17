<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Точечные проверки граничных случаев из чек-листа.
 */
class BookingRulesTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-03-10 12:00:00', 'Europe/Istanbul');
        $this->travelTo($this->now);
    }

    public function test_slot_starting_exactly_now_is_past_and_cannot_be_booked(): void
    {
        $slot = $this->createSlotStartingAt($this->now);

        $this->getJson('/api/slots')->assertOk()->assertJsonPath('data.0.status', 'past');
        $this->postJson('/api/bookings', $this->payload($slot))
            ->assertConflict()
            ->assertJsonPath('message', 'Слот уже начался, забронировать его нельзя.');
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_invalid_input_returns_422_with_field_errors(): void
    {
        $this->postJson('/api/bookings', ['slot_id' => 999999, 'customer_name' => 'A', 'customer_email' => 'a@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors(['slot_id']);

        $this->postJson('/api/bookings', ['slot_id' => 'abc', 'customer_name' => '', 'customer_email' => 'a@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors(['slot_id', 'customer_name']);

        $slot = $this->createSlotStartingAt($this->now->addDay());
        $this->postJson('/api/bookings', ['slot_id' => $slot->id, 'customer_name' => '   ', 'customer_email' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonValidationErrors(['customer_name', 'customer_email']);

        $this->postJson('/api/bookings', ['slot_id' => $slot->id, 'customer_name' => str_repeat('a', 256), 'customer_email' => 'a@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors(['customer_name']);
    }

    public function test_cancel_rebook_and_repeated_cancel_of_old_booking(): void
    {
        $slot = $this->createSlotStartingAt($this->now->addDay());
        $oldId = $this->postJson('/api/bookings', $this->payload($slot))->assertCreated()->json('id');

        $this->deleteJson("/api/bookings/{$oldId}")->assertNoContent();
        $this->assertSame('', $this->deleteJson("/api/bookings/{$oldId}")->assertNoContent()->getContent());
        $this->getJson('/api/slots')->assertJsonPath('data.0.status', 'available')->assertJsonPath('data.0.active_booking_id', null);

        $newId = $this->postJson('/api/bookings', $this->payload($slot, 'Bob'))->assertCreated()->json('id');
        $cancelledAt = Booking::query()->findOrFail($oldId)->cancelled_at;

        $this->travel(5)->minutes();
        $this->deleteJson("/api/bookings/{$oldId}")->assertNoContent();

        $this->assertNull(Booking::query()->findOrFail($newId)->cancelled_at);
        $this->assertTrue($cancelledAt->equalTo(Booking::query()->findOrFail($oldId)->cancelled_at));
        $this->getJson('/api/slots')->assertJsonPath('data.0.status', 'booked')->assertJsonPath('data.0.active_booking_id', $newId);
        $this->deleteJson('/api/bookings/999999')->assertNotFound();
    }

    public function test_cancellation_time_is_stored_as_the_real_moment(): void
    {
        $slot = $this->createSlotStartingAt($this->now->addDay());
        $bookingId = $this->postJson('/api/bookings', $this->payload($slot))->assertCreated()->json('id');

        $this->deleteJson("/api/bookings/{$bookingId}")->assertNoContent();

        $this->assertSame(
            '2026-03-10 09:00:00',
            DB::scalar("select to_char(cancelled_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') from bookings where id = ?", [$bookingId]),
        );
        $this->assertTrue($this->now->equalTo(Booking::query()->findOrFail($bookingId)->cancelled_at));
    }

    public function test_slot_list_has_statuses_utc_times_and_no_personal_data(): void
    {
        $past = $this->createSlotStartingAt($this->now->setTime(9, 0));
        $booked = $this->createSlotStartingAt($this->now->setTime(15, 0));
        $this->createSlotStartingAt($this->now->setTime(16, 0));
        $pastBookingId = Booking::query()->create($this->payload($past))->id;
        $this->postJson('/api/bookings', $this->payload($booked))->assertCreated();

        $response = $this->getJson('/api/slots')->assertOk();

        $response->assertJsonPath('meta.timezone', 'Europe/Istanbul')
            ->assertJsonPath('meta.server_now', '2026-03-10T09:00:00Z')
            ->assertJsonPath('data.0.starts_at', '2026-03-10T06:00:00Z')
            ->assertJsonPath('data.0.status', 'past')
            ->assertJsonPath('data.0.active_booking_id', $pastBookingId)
            ->assertJsonPath('data.1.status', 'booked')
            ->assertJsonPath('data.2.status', 'available');
        $this->assertSame(['id', 'starts_at', 'ends_at', 'status', 'active_booking_id'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString('example.com', $response->getContent());
        $this->assertStringNotContainsString('Alice', $response->getContent());
    }

    private function createSlotStartingAt(CarbonImmutable $startsAt): Slot
    {
        return Slot::query()->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->addHour()]);
    }

    private function payload(Slot $slot, string $name = 'Alice'): array
    {
        return ['slot_id' => $slot->id, 'customer_name' => $name, 'customer_email' => strtolower($name).'@example.com'];
    }
}
