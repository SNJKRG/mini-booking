<?php

declare(strict_types=1);

namespace App\Providers;

use App\Database\OffsetPostgresConnection;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Подключает к драйверу pgsql соединение, которое передаёт даты в БД со смещением.
     */
    public function register(): void
    {
        Connection::resolverFor(
            'pgsql',
            fn (PDO|Closure $pdo, string $database, string $prefix, array $config): OffsetPostgresConnection => new OffsetPostgresConnection($pdo, $database, $prefix, $config),
        );
    }

    /**
     * Делает now() и даты моделей неизменяемыми, чтобы addDay() и setTime() не меняли общий объект.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
