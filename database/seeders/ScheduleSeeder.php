<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет часовые слоты с 09:00 до 18:00 (в часовом поясе приложения) на сегодня и следующие 6 дней.
 * Идемпотентен: существующие слоты и брони не трогает, вставляет только недостающие слоты.
 */
class ScheduleSeeder extends Seeder
{
    public const DAYS = 7;
    public const FIRST_HOUR = 9;
    public const LAST_HOUR = 18;

    public function run(): void
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $createdAt = CarbonImmutable::now()->format(Slot::DATE_FORMAT);
        $rows = [];

        for ($day = 0; $day < self::DAYS; $day++) {
            for ($hour = self::FIRST_HOUR; $hour < self::LAST_HOUR; $hour++) {
                $startsAt = $today->addDays($day)->setTime($hour, 0);

                $rows[] = [
                    'starts_at' => $startsAt->format(Slot::DATE_FORMAT),
                    'ends_at' => $startsAt->addHour()->format(Slot::DATE_FORMAT),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        DB::table('slots')->insertOrIgnore($rows);
    }
}
