<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * 409 если слот уже начался или у него есть активная бронь
 */
class SlotUnavailableException extends RuntimeException
{
    public static function alreadyStarted(): self
    {
        return new self('Слот уже начался, забронировать его нельзя.');
    }

    public static function alreadyBooked(): self
    {
        return new self('Слот уже занят другим бронированием.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
