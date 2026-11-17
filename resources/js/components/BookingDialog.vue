<script setup>
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Message from 'primevue/message';
import { createBooking } from '../api.js';
import { formatDayLong, formatTime } from '../format.js';

const FIELD_MAX_LENGTH = 255;
const FORM_FIELDS = ['customer_name', 'customer_email'];

const props = defineProps({
    bookingSlot: { type: Object, default: null },
    timezone: { type: String, default: null },
    refreshing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'booked', 'conflict', 'refresh']);

const name = ref('');
const email = ref('');
const fieldErrors = ref({});
const formError = ref(null);
const submitting = ref(false);
const outcomeUnknown = ref(false);

const isOpen = computed(() => props.bookingSlot !== null);
const isSlotAvailable = computed(() => props.bookingSlot?.status === 'available');
const slotView = computed(() => {
    if (!props.bookingSlot) {
        return null;
    }
    const { starts_at: startsAt, ends_at: endsAt } = props.bookingSlot;
    return {
        range: `${formatTime(startsAt, props.timezone)}-${formatTime(endsAt, props.timezone)}`,
        day: formatDayLong(startsAt, props.timezone),
    };
});

watch(() => props.bookingSlot?.id, (slotId, previousSlotId) => {
    if (slotId !== undefined && slotId !== previousSlotId) {
        resetForm();
    }
});

function resetForm() {
    name.value = '';
    email.value = '';
    fieldErrors.value = {};
    formError.value = null;
    outcomeUnknown.value = false;
}

function close() {
    if (!submitting.value) {
        emit('close');
    }
}

function applyValidationErrors(error) {
    const errors = {};
    for (const field of FORM_FIELDS) {
        if (error.errors[field]?.length) {
            errors[field] = error.errors[field][0];
        }
    }
    fieldErrors.value = errors;
    formError.value = error.errors.slot_id?.[0] ?? (Object.keys(errors).length ? null : error.message);
}

async function submit() {
    if (submitting.value || !isSlotAvailable.value) {
        return;
    }

    fieldErrors.value = {};
    formError.value = null;
    outcomeUnknown.value = false;

    if (!name.value.trim()) {
        fieldErrors.value = { customer_name: 'Укажите имя.' };
        return;
    }

    submitting.value = true;
    try {
        const { id } = await createBooking({
            slot_id: props.bookingSlot.id,
            customer_name: name.value.trim(),
            customer_email: email.value.trim(),
        });
        emit('booked', id);
    } catch (error) {
        if (error.status === 422) {
            applyValidationErrors(error);
        } else if (error.status === 409) {
            emit('conflict', error.message);
        } else if (error.network) {
            outcomeUnknown.value = true;
            formError.value = 'Ответ сервера не получен, бронирование могло создаться. Обновите расписание перед повтором.';
        } else {
            formError.value = error.message;
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Dialog
        :visible="isOpen"
        modal
        header="Бронирование слота"
        :closable="!submitting"
        :style="{ width: '28rem' }"
        :breakpoints="{ '575px': 'calc(100vw - 2rem)' }"
        @update:visible="(visible) => !visible && close()"
    >
        <p v-if="slotView" class="dialog-slot">
            <span class="display-time dialog-slot-time">{{ slotView.range }}</span>
            <span class="dialog-slot-details">{{ slotView.day }}</span>
            <span class="dialog-slot-zone">{{ timezone }}</span>
        </p>

        <Message v-if="bookingSlot && !isSlotAvailable" severity="warn" class="form-message">
            Этот слот больше недоступен для бронирования.
        </Message>

        <form class="booking-form" @submit.prevent="submit">
            <div class="field">
                <label for="customer-name">Имя</label>
                <InputText
                    id="customer-name"
                    autofocus
                    v-model="name"
                    required
                    :maxlength="FIELD_MAX_LENGTH"
                    autocomplete="name"
                    :invalid="Boolean(fieldErrors.customer_name)"
                    :aria-describedby="fieldErrors.customer_name ? 'customer-name-error' : undefined"
                    fluid
                />
                <small v-if="fieldErrors.customer_name" id="customer-name-error" class="field-error">
                    {{ fieldErrors.customer_name }}
                </small>
            </div>

            <div class="field">
                <label for="customer-email">Электронная почта</label>
                <InputText
                    id="customer-email"
                    v-model="email"
                    type="email"
                    required
                    :maxlength="FIELD_MAX_LENGTH"
                    autocomplete="email"
                    :invalid="Boolean(fieldErrors.customer_email)"
                    :aria-describedby="fieldErrors.customer_email ? 'customer-email-error' : undefined"
                    fluid
                />
                <small v-if="fieldErrors.customer_email" id="customer-email-error" class="field-error">
                    {{ fieldErrors.customer_email }}
                </small>
            </div>

            <Message v-if="formError" severity="error" class="form-message">{{ formError }}</Message>

            <div class="dialog-actions">
                <Button
                    label="Закрыть"
                    severity="secondary"
                    text
                    class="dialog-close"
                    :disabled="submitting"
                    @click="close"
                />
                <Button
                    v-if="outcomeUnknown"
                    label="Обновить расписание"
                    severity="secondary"
                    outlined
                    :loading="refreshing"
                    :disabled="refreshing"
                    @click="emit('refresh')"
                />
                <Button
                    type="submit"
                    label="Забронировать"
                    :loading="submitting"
                    :disabled="submitting || !isSlotAvailable"
                />
            </div>
        </form>
    </Dialog>
</template>
