<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware()
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            $message = [
                404 => 'Не найдено.',
                405 => 'Метод не поддерживается.',
                429 => 'Слишком много запросов. Попробуйте через минуту.',
            ][$exception->getStatusCode()] ?? null;
            if ($message === null || ! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => $message], $exception->getStatusCode(), $exception->getHeaders());
        });
    })->create();
