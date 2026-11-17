<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Slot;
use Illuminate\Http\JsonResponse;

class SlotController
{
    /**
     * Возвращает все слоты по времени начала со статусом, вычисленным для одного зафиксированного сейчас.
     */
    public function index(): JsonResponse
    {
        $now = now();

        $slots = Slot::query()
            ->with('activeBooking:id,slot_id')
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $slots->map(fn (Slot $slot): array => [
                'id' => $slot->id,
                'starts_at' => $slot->starts_at->toIso8601ZuluString(),
                'ends_at' => $slot->ends_at->toIso8601ZuluString(),
                'status' => $slot->statusAt($now)->value,
                'active_booking_id' => $slot->activeBooking?->id,
            ]),
            'meta' => [
                'timezone' => config('app.timezone'),
                'server_now' => $now->toIso8601ZuluString(),
            ],
        ]);
    }
}
