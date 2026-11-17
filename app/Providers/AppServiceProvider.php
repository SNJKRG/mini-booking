<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Делает now() и даты моделей неизменяемыми, чтобы addDay() и setTime() не меняли общий объект.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
