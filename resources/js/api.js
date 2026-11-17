/**
 * HTTP-слой API бронирования. Любой сбой выбрасывается как ApiError:
 * `network: true` значит, что HTTP-ответа не было вовсе, иначе в `status` лежит HTTP-код.
 */
export class ApiError extends Error {
    constructor(message, { status = null, errors = {}, network = false } = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
        this.network = network;
    }
}

const HTTP_NO_CONTENT = 204;
const HTTP_SERVER_ERROR = 500;

async function readJson(response) {
    const contentType = response.headers.get('content-type') ?? '';
    if (!contentType.includes('application/json')) {
        return null;
    }
    try {
        return await response.json();
    } catch {
        return null;
    }
}

async function request(path, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    let response;
    try {
        response = await fetch(`/api${path}`, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError('Нет связи с сервером.', { network: true });
    }

    if (response.status === HTTP_NO_CONTENT) {
        return null;
    }

    const data = await readJson(response);

    if (!response.ok) {
        const isServerError = response.status >= HTTP_SERVER_ERROR;
        const message = !isServerError && data?.message
            ? data.message
            : `Ошибка сервера (${response.status}). Попробуйте ещё раз.`;
        throw new ApiError(message, { status: response.status, errors: data?.errors ?? {} });
    }

    if (data === null) {
        throw new ApiError('Сервер вернул неожиданный ответ.', { status: response.status });
    }

    return data;
}

export const fetchSlots = () => request('/slots');

export const createBooking = (payload) => request('/bookings', { method: 'POST', body: payload });

export const cancelBooking = (bookingId) => request(`/bookings/${bookingId}`, { method: 'DELETE' });
