<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private const TEST_DATABASE_SUFFIX = '_test';

    /**
     * Создаёт приложение и отказывается продолжать, если тесты смотрят не в отдельную БД *_test.
     *
     * Проверка стоит здесь, а не в setUp(): RefreshDatabase запускает migrate:fresh внутри
     * parent::setUp(), и проверка после него сработала бы уже после очистки чужой базы.
     * Имя берётся у соединения, поэтому учитывается и DB_URL.
     *
     * @throws RuntimeException если имя БД не оканчивается на _test
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $database = (string) $app['db']->connection()->getDatabaseName();
        if (! str_ends_with($database, self::TEST_DATABASE_SUFFIX)) {
            throw new RuntimeException("Тесты должны запускаться на БД *_test, а получена [{$database}].");
        }

        return $app;
    }
}
