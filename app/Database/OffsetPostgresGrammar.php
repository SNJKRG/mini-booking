<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * Грамматика PostgreSQL, которая передаёт даты в запросы со смещением от UTC.
 *
 * Сессия БД работает в UTC, а даты в PHP живут в часовом поясе расписания. Без смещения
 * локальное «09:00» записалось бы в timestamptz как 09:00 UTC. Формат действует везде:
 * и в query builder, и в моделях, потому что Eloquent берёт формат дат у грамматики.
 */
class OffsetPostgresGrammar extends PostgresGrammar
{
    public const DATE_FORMAT = 'Y-m-d H:i:sP';

    /**
     * Формат, в котором даты подставляются в запросы.
     */
    public function getDateFormat(): string
    {
        return self::DATE_FORMAT;
    }
}
