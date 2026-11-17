<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\PostgresConnection;

/**
 * Соединение PostgreSQL с грамматикой, которая передаёт даты со смещением.
 *
 * Грамматика задаётся через getDefaultQueryGrammar(), а не через setQueryGrammar(),
 * потому что Laravel пересоздаёт её при клонировании соединения.
 */
class OffsetPostgresConnection extends PostgresConnection
{
    /**
     * Грамматика запросов по умолчанию для этого соединения.
     */
    protected function getDefaultQueryGrammar(): OffsetPostgresGrammar
    {
        return new OffsetPostgresGrammar($this);
    }
}
