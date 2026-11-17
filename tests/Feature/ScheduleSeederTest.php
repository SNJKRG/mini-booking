<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Database\Seeders\ScheduleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Пятница 2026-03-13, поздний вечер: весь рабочий день уже прошёл. */
    private const SEED_MOMENT = '2026-03-13 20:00:00';

    public function test_seeds_63_slots_9_per_day_including_weekend_in_app_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::SEED_MOMENT, 'Europe/Istanbul'));

        $this->seed(ScheduleSeeder::class);

        $this->assertSame(63, Slot::query()->count());
        $perDay = Slot::query()->orderBy('starts_at')->get()
            ->groupBy(fn (Slot $slot): string => $slot->starts_at->setTimezone('Europe/Istanbul')->toDateString())
            ->map->count();
        $this->assertSame(array_fill_keys(
            ['2026-03-13', '2026-03-14', '2026-03-15', '2026-03-16', '2026-03-17', '2026-03-18', '2026-03-19'],
            9,
        ), $perDay->all());

        $first = Slot::query()->orderBy('starts_at')->firstOrFail();
        $this->assertSame('2026-03-13T06:00:00Z', $first->starts_at->toIso8601ZuluString());
        $this->assertSame(
            '2026-03-13 06:00:00',
            DB::scalar("select to_char(starts_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') from slots where id = ?", [$first->id]),
        );
    }

    public function test_rerun_keeps_ids_and_bookings_and_next_day_adds_nine_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::SEED_MOMENT, 'Europe/Istanbul'));
        $this->seed(ScheduleSeeder::class);
        $idsBefore = Slot::query()->orderBy('id')->pluck('id', 'starts_at')->all();
        $slot = Slot::query()->orderByDesc('starts_at')->firstOrFail();
        $booking = Booking::query()->create(['slot_id' => $slot->id, 'customer_name' => 'A', 'customer_email' => 'a@example.com']);

        $this->seed(ScheduleSeeder::class);

        $this->assertSame($idsBefore, Slot::query()->orderBy('id')->pluck('id', 'starts_at')->all());
        $this->assertSame(1, Booking::query()->count());

        $this->travelTo(CarbonImmutable::parse(self::SEED_MOMENT, 'Europe/Istanbul')->addDay());
        $this->seed(ScheduleSeeder::class);

        $this->assertSame(72, Slot::query()->count());
        $idsAfter = Slot::query()->orderBy('id')->pluck('id', 'starts_at')->all();
        $this->assertSame($idsBefore, array_intersect_key($idsAfter, $idsBefore));
        $this->assertSame(9, Slot::query()->where('starts_at', '>=', '2026-03-20 00:00:00+03:00')->count());
        $this->assertTrue($booking->fresh()->slot->is($slot));
    }
}
