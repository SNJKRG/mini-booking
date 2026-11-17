<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Message from 'primevue/message';
import SelectButton from 'primevue/selectbutton';
import Skeleton from 'primevue/skeleton';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import BookingDialog from './components/BookingDialog.vue';
import { cancelBooking, fetchSlots } from './api.js';
import { dayKey, formatDay, formatDayLong, formatDayNumber, formatTime, formatWeekday } from './format.js';

const STATUS_LABEL = {
    available: 'Свободен',
    booked: 'Занят',
    past: 'Прошёл',
};
const TOAST_LIFE_MS = 4000;
const SKELETON_ROWS = 6;

const toast = useToast();
const confirm = useConfirm();

const slots = ref([]);
const timezone = ref(null);
const serverNow = ref(null);
const todayKey = ref(null);
const selectedDate = ref(null);
const listLoading = ref(false);
const listError = ref(null);

const dialogSlotId = ref(null);
const cancellingBookingId = ref(null);
const cancelError = ref(null);
const notice = ref(null);

let latestListRequest = 0;

const dayOptions = computed(() => {
    const options = new Map();
    for (const slot of slots.value) {
        const key = dayKey(slot.starts_at, timezone.value);
        if (!options.has(key)) {
            options.set(key, {
                value: key,
                weekday: formatWeekday(slot.starts_at, timezone.value),
                dayNumber: formatDayNumber(slot.starts_at, timezone.value),
                isToday: key === todayKey.value,
                label: formatDay(slot.starts_at, timezone.value),
            });
        }
    }
    return [...options.values()];
});

const visibleSlots = computed(() =>
    slots.value.filter((slot) => dayKey(slot.starts_at, timezone.value) === selectedDate.value),
);

const selectedDayTitle = computed(() => {
    const first = visibleSlots.value[0];
    if (!first) {
        return '';
    }
    const title = formatDayLong(first.starts_at, timezone.value);
    return title.charAt(0).toUpperCase() + title.slice(1);
});

const dayStats = computed(() => {
    const stats = { available: 0, booked: 0, past: 0 };
    for (const slot of visibleSlots.value) {
        stats[slot.status] += 1;
    }
    return stats;
});

/** Индекс в visibleSlots, перед которым рисуется линия «сейчас»; null, если выбран не сегодняшний день. */
const nowLineIndex = computed(() => {
    if (selectedDate.value !== todayKey.value) {
        return null;
    }
    const index = visibleSlots.value.findIndex((slot) => slot.status !== 'past');
    return index === -1 ? visibleSlots.value.length : index;
});

const nextFreeSlot = computed(() => slots.value.find((slot) => slot.status === 'available') ?? null);

const dialogSlot = computed(() => slots.value.find((slot) => slot.id === dialogSlotId.value) ?? null);

function describeDay(iso) {
    return dayKey(iso, timezone.value) === todayKey.value ? 'сегодня' : formatDay(iso, timezone.value);
}

/**
 * Загружает расписание. Состояние обновляет только последний запрос, поэтому медленный устаревший ответ
 * не перезапишет свежие данные. Возвращает true, если список обновился.
 */
async function loadSlots() {
    const requestNumber = ++latestListRequest;
    listLoading.value = true;
    listError.value = null;

    try {
        const { data, meta } = await fetchSlots();
        if (requestNumber !== latestListRequest) {
            return false;
        }
        slots.value = data;
        timezone.value = meta.timezone;
        serverNow.value = meta.server_now;
        todayKey.value = dayKey(meta.server_now, meta.timezone);
        keepSelectedDate();
        return true;
    } catch (error) {
        if (requestNumber === latestListRequest) {
            listError.value = `Не удалось загрузить расписание. ${error.message}`;
        }
        return false;
    } finally {
        if (requestNumber === latestListRequest) {
            listLoading.value = false;
        }
    }
}

function keepSelectedDate() {
    const keys = dayOptions.value.map((option) => option.value);
    if (keys.includes(selectedDate.value)) {
        return;
    }
    selectedDate.value = keys.includes(todayKey.value) ? todayKey.value : (keys[0] ?? null);
}

async function refreshAfterChange(successText) {
    toast.add({ severity: 'success', summary: successText, life: TOAST_LIFE_MS });
    if (!(await loadSlots())) {
        notice.value = {
            severity: 'warn',
            text: `${successText} Расписание обновить не удалось, нажмите «Обновить».`,
        };
    }
}

function openBooking(slot) {
    notice.value = null;
    dialogSlotId.value = slot.id;
}

function onBooked(bookingId) {
    dialogSlotId.value = null;
    refreshAfterChange(`Бронирование №${bookingId} создано.`);
}

function onConflict(message) {
    dialogSlotId.value = null;
    notice.value = { severity: 'error', text: `${message} Расписание обновлено.` };
    loadSlots();
}

function confirmCancel(slot) {
    const when = `${formatDay(slot.starts_at, timezone.value)}, ${formatTime(slot.starts_at, timezone.value)}`;
    confirm.require({
        header: 'Отмена бронирования',
        message: `Отменить бронирование №${slot.active_booking_id} на ${when}?`,
        acceptLabel: 'Отменить бронь',
        rejectLabel: 'Не отменять',
        acceptProps: { severity: 'danger' },
        rejectProps: { severity: 'secondary', outlined: true },
        accept: () => cancel(slot.active_booking_id),
    });
}

async function cancel(bookingId) {
    if (cancellingBookingId.value !== null) {
        return;
    }
    cancellingBookingId.value = bookingId;
    cancelError.value = null;
    notice.value = null;

    try {
        await cancelBooking(bookingId);
    } catch (error) {
        if (error.status === 404) {
            notice.value = { severity: 'warn', text: `Бронирование №${bookingId} не найдено. Расписание обновлено.` };
            loadSlots();
        } else {
            cancelError.value = { bookingId, text: `Не удалось отменить бронирование №${bookingId}. ${error.message}` };
        }
        return;
    } finally {
        cancellingBookingId.value = null;
    }

    refreshAfterChange(`Бронирование №${bookingId} отменено.`);
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
        loadSlots();
    }
}

onMounted(() => {
    loadSlots();
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisibilityChange));
</script>

<template>
    <Toast :breakpoints="{ '575px': { width: 'calc(100vw - 2rem)', left: '1rem', right: '1rem' } }" />
    <ConfirmDialog :breakpoints="{ '575px': 'calc(100vw - 2rem)' }" />

    <div class="layout">
        <aside class="intro">
            <h1 class="intro-title">Переговорная</h1>
            <p class="intro-text">Почасовые слоты с 9:00 до 18:00, каждый день, включая выходные.</p>
            <p v-if="timezone" class="intro-zone">
                Часовой пояс расписания: <strong>{{ timezone }}</strong>
            </p>

            <section v-if="nextFreeSlot" class="next-free" aria-labelledby="next-free-title">
                <h2 id="next-free-title" class="next-free-label">Ближайшее свободное время</h2>
                <div class="next-free-body">
                    <p class="next-free-time">
                        <span class="display-time">{{ formatTime(nextFreeSlot.starts_at, timezone) }}</span>
                        <span class="next-free-date">
                            {{ describeDay(nextFreeSlot.starts_at) }}, до {{ formatTime(nextFreeSlot.ends_at, timezone) }}
                        </span>
                    </p>
                    <Button label="Забронировать" @click="openBooking(nextFreeSlot)" />
                </div>
            </section>
            <p v-else-if="slots.length" class="next-free next-free--empty">
                Свободного времени на ближайшие дни нет.
            </p>
        </aside>

        <main class="schedule">
            <SelectButton
                v-if="dayOptions.length"
                v-model="selectedDate"
                :options="dayOptions"
                option-value="value"
                option-label="label"
                :allow-empty="false"
                aria-label="Дата"
                class="day-strip"
            >
                <template #option="{ option }">
                    <span class="day-chip" :class="{ 'day-chip--today': option.isToday }">
                        <span class="day-chip-weekday">{{ option.weekday }}</span>
                        <span class="day-chip-number">{{ option.dayNumber }}</span>
                        <span v-if="option.isToday" class="visually-hidden">, сегодня</span>
                    </span>
                </template>
            </SelectButton>

            <header v-if="visibleSlots.length" class="day-header">
                <h2 class="day-title">{{ selectedDayTitle }}</h2>
                <dl class="day-stats">
                    <div><dt>свободно</dt><dd>{{ dayStats.available }}</dd></div>
                    <div><dt>занято</dt><dd>{{ dayStats.booked }}</dd></div>
                    <div><dt>прошло</dt><dd>{{ dayStats.past }}</dd></div>
                </dl>
                <Button
                    label="Обновить"
                    severity="secondary"
                    text
                    size="small"
                    class="day-refresh"
                    :loading="listLoading"
                    :disabled="listLoading"
                    @click="loadSlots"
                />
            </header>

            <Message v-if="notice" :severity="notice.severity" closable class="page-message" @close="notice = null">
                {{ notice.text }}
            </Message>

            <Message v-if="cancelError" severity="error" class="page-message">
                <div class="message-row">
                    <span>{{ cancelError.text }}</span>
                    <Button
                        label="Повторить отмену"
                        size="small"
                        severity="danger"
                        outlined
                        :loading="cancellingBookingId === cancelError.bookingId"
                        :disabled="cancellingBookingId !== null"
                        @click="cancel(cancelError.bookingId)"
                    />
                </div>
            </Message>

            <Message v-if="listError" severity="error" class="page-message">
                <div class="message-row">
                    <span>{{ listError }}</span>
                    <Button label="Повторить" size="small" severity="danger" outlined :disabled="listLoading" @click="loadSlots" />
                </div>
            </Message>

            <ul v-if="listLoading && !slots.length" class="timeline" aria-busy="true" aria-label="Загрузка расписания">
                <li v-for="row in SKELETON_ROWS" :key="row" class="slot-row slot-row--skeleton">
                    <Skeleton width="4.5rem" height="1.5rem" />
                    <Skeleton width="6rem" height="1rem" />
                    <Skeleton width="7.5rem" height="2rem" />
                </li>
            </ul>

            <div v-else-if="!listError && !visibleSlots.length" class="empty">
                <p class="empty-title">Расписание ещё не опубликовано</p>
                <p class="empty-text">Слоты на эти дни появятся после обновления расписания.</p>
                <Button label="Обновить" severity="secondary" outlined size="small" :loading="listLoading" @click="loadSlots" />
            </div>

            <ul v-else :key="selectedDate" class="timeline" :aria-busy="listLoading">
                <template v-for="(slot, index) in visibleSlots" :key="slot.id">
                    <li v-if="index === nowLineIndex" class="now-line">
                        Сейчас {{ formatTime(serverNow, timezone) }}
                    </li>
                    <li class="slot-row" :class="`slot-row--${slot.status}`" :style="{ '--row-index': index }">
                        <p class="slot-time">
                            <span class="display-time">{{ formatTime(slot.starts_at, timezone) }}</span>
                            <span class="slot-until">до {{ formatTime(slot.ends_at, timezone) }}</span>
                        </p>
                        <p class="slot-status">
                            <span class="slot-status-label">{{ STATUS_LABEL[slot.status] }}</span>
                            <span v-if="slot.active_booking_id" class="slot-booking">бронь №{{ slot.active_booking_id }}</span>
                        </p>
                        <div class="slot-action">
                            <Button
                                v-if="slot.status === 'available'"
                                label="Забронировать"
                                size="small"
                                outlined
                                @click="openBooking(slot)"
                            />
                            <Button
                                v-else-if="slot.active_booking_id"
                                label="Отменить"
                                size="small"
                                severity="danger"
                                text
                                :loading="cancellingBookingId === slot.active_booking_id"
                                :disabled="cancellingBookingId !== null"
                                @click="confirmCancel(slot)"
                            />
                        </div>
                    </li>
                </template>
                <li v-if="nowLineIndex === visibleSlots.length" class="now-line">
                    Сейчас {{ formatTime(serverNow, timezone) }}
                </li>
            </ul>
        </main>
    </div>

    <BookingDialog
        :booking-slot="dialogSlot"
        :timezone="timezone"
        :refreshing="listLoading"
        @close="dialogSlotId = null"
        @booked="onBooked"
        @conflict="onConflict"
        @refresh="loadSlots"
    />
</template>
