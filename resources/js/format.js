/**
 * Форматирование дат. Всё форматируется в часовом поясе расписания (meta.timezone),
 * а не в поясе браузера, поэтому день и часы не сдвигаются.
 */

const LOCALE = 'ru-RU';

function formatInZone(iso, timeZone, options) {
    return new Intl.DateTimeFormat(LOCALE, { timeZone, ...options }).format(new Date(iso));
}

/** Календарный день ISO-момента в заданном поясе в формате YYYY-MM-DD. */
export function dayKey(iso, timeZone) {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date(iso));
    const part = (type) => parts.find((item) => item.type === type).value;

    return `${part('year')}-${part('month')}-${part('day')}`;
}

/** "09:00" */
export const formatTime = (iso, timeZone) => formatInZone(iso, timeZone, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });

/** "вт, 17 ноября" */
export const formatDay = (iso, timeZone) => formatInZone(iso, timeZone, { weekday: 'short', day: 'numeric', month: 'long' });

/** "вторник, 17 ноября" */
export const formatDayLong = (iso, timeZone) => formatInZone(iso, timeZone, { weekday: 'long', day: 'numeric', month: 'long' });

/** "вт" */
export const formatWeekday = (iso, timeZone) => formatInZone(iso, timeZone, { weekday: 'short' });

/** "17" */
export const formatDayNumber = (iso, timeZone) => formatInZone(iso, timeZone, { day: 'numeric' });
