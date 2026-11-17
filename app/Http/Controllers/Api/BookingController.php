<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BookingController
{
    public function __construct(private readonly BookingService $bookingService) {}

    /**
     * Создаёт бронь 201 с её ID, 422 при неверных данных, 409 если слот недоступен
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $booking = $this->bookingService->book(
            (int) $data['slot_id'],
            $data['customer_name'],
            $data['customer_email'],
        );

        return response()->json(['id' => $booking->id], 201);
    }

    /**
     * Отменяет бронь: 204, в том числе при повторной отмене, 404 для неизвестного ID
     */
    public function destroy(Booking $booking): Response
    {
        $this->bookingService->cancel($booking);

        return response()->noContent();
    }
}
