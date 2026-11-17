<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Нет авторизации т.к. бронировать может любой.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Глобальные middleware обрезают пробелы и превращают пустые строки в null, поэтому "   " не проходит required.
     * bail не пускает нечисловой slot_id в запрос exists.
     */
    public function rules(): array
    {
        return [
            'slot_id' => ['bail', 'required', 'integer', 'exists:slots,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Сообщения валидации
     */
    public function messages(): array
    {
        return [
            'slot_id.required' => 'Не выбран слот.',
            'slot_id.integer' => 'Некорректный идентификатор слота.',
            'slot_id.exists' => 'Такого слота нет в расписании.',
            'customer_name.required' => 'Укажите имя.',
            'customer_name.string' => 'Имя должно быть строкой.',
            'customer_name.max' => 'Имя не длиннее :max символов.',
            'customer_email.required' => 'Укажите электронную почту.',
            'customer_email.string' => 'Адрес почты должен быть строкой.',
            'customer_email.email' => 'Укажите корректный адрес электронной почты.',
            'customer_email.max' => 'Адрес почты не длиннее :max символов.',
        ];
    }
}
