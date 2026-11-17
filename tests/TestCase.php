<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private const TEST_DATABASE_SUFFIX = '_test';

    /**
     * Отказывается запускаться (и очищать таблицы) на любой БД, кроме отдельной *_test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with($database, self::TEST_DATABASE_SUFFIX)) {
            throw new RuntimeException("Тесты должны запускаться на БД *_test, а получена [{$database}].");
        }
    }
}
