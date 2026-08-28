<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/ScheduleModal/StatusBadge.vue';

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    totals: { type: Object, default: () => ({ all: 0, pending: 0, approved: 0, rejected: 0 }) },
    filters: { type: Object, default: () => ({ status: 'all' }) },
    operatingHours: { type: Object, default: () => ({ opening: '07:00', closing: '21:00' }) },
});

const page = usePage();
const today = new Date().toLocaleDateString('en-CA');
const availabilityLoading = ref(false);
const availabilityError = ref('');
const rooms = ref([]);
let availabilitySequence = 0;

const form = useForm({
    room_id: '',
    reservation_date: '',
    start_time: '08:00',
    end_time: '09:00',
    purpose: '',
    attendees: 1,
    remarks: '',
});

const requestsList = computed(() => props.requests?.data || []);
const availableRooms = computed(() => rooms.value.filter((room) => room.is_available));
const unavailableRooms = computed(() => rooms.value.filter((room) => !room.is_available));
const selectedRoom = computed(() => rooms.value.find((room) => String(room.id) === String(form.room_id)));
const canCheckAvailability = computed(() => (
    form.reservation_date && form.start_time && form.end_time && form.end_time > form.start_time
));

const statusCards = computed(() => [
    { key: 'all', label: 'All requests', value: props.totals.all, classes: 'border-slate-300 bg-slate-50 text-slate-800' },
    { key: 'pending', label: 'Pending', value: props.totals.pending, classes: 'border-amber-400 bg-amber-50 text-amber-900' },
    { key: 'approved', label: 'Approved', value: props.totals.approved, classes: 'border-emerald-500 bg-emerald-50 text-emerald-900' },
    { key: 'rejected', label: 'Rejected', value: props.totals.rejected, classes: 'border-red-500 bg-red-50 text-red-900' },
]);

const formatDate = (value, includeTime = false) => {
    if (!value) return '—';
    const date = includeTime ? new Date(value) : new Date(`${value}T00:00:00`);
    return date.toLocaleDateString('en-US', includeTime
        ? { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }
        : { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
};

const formatTime = (value) => {
    const [hour, minute] = String(value || '').split(':').map(Number);
    if (!Number.isFinite(hour)) return value || '—';
    return new Date(1970, 0, 1, hour, minute).toLocaleTimeString('en-US', {
        hour: 'numeric', minute: '2-digit', hour12: true,
    });
};

const checkAvailability = async () => {
    if (!canCheckAvailability.value) {
        rooms.value = [];
        return;
    }

    const sequence = ++availabilitySequence;
    availabilityLoading.value = true;
    availabilityError.value = '';

    try {
        const response = await axios.get('/api/reservations/availability', {
            params: {
                reservation_date: form.reservation_date,
                start_time: form.start_time,
                end_time: form.end_time,
                attendees: form.attendees || 1,
            },
        });
        if (sequence !== availabilitySequence) return;
        rooms.value = response.data.rooms || [];
        if (!availableRooms.value.some((room) => String(room.id) === String(form.room_id))) {
            form.room_id = '';
        }
    } catch (error) {
        if (sequence !== availabilitySequence) return;
        rooms.value = [];
        availabilityError.value = error.response?.data?.message || 'Unable to check room availability.';
    } finally {
        if (sequence === availabilitySequence) availabilityLoading.value = false;
    }
};

watch(
    () => [form.reservation_date, form.start_time, form.end_time, form.attendees],
    checkAvailability,
);

const submit = () => {
    form.post('/MyReservations', {
        preserveScroll: true,
        onError: () => document.querySelector('[data-reservation-errors]')?.scrollIntoView({ behavior: 'smooth' }),
    });
};

const filterByStatus = (status) => {
    router.get('/MyReservations', { status }, { preserveState: true, preserveScroll: true, replace: true });
};
</script>

<template>
    <AppLayout>
        <header class="app-page-header">
            <div>
                <span class="app-breadcrumb">Student Services</span>
                <h1 class="app-page-title">Room Reservation Dashboard</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Request an available campus room and track every approval decision in one place.</p>
            </div>
        </header>

        <div v-if="page.props.flash?.success" role="status" class="mb-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
            {{ page.props.flash.success }}
        </div>

        <section aria-label="Reservation totals" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button
                v-for="card in statusCards"
                :key="card.key"
                type="button"
                class="rounded-xl border-l-4 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                :class="[card.classes, filters.status === card.key ? 'ring-2 ring-[#005740] ring-offset-2' : '']"
                @click="filterByStatus(card.key)"
            >
                <span class="block text-xs font-extrabold uppercase tracking-[0.12em]">{{ card.label }}</span>
                <strong class="mt-2 block text-3xl">{{ card.value }}</strong>
            </button>
        </section>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <section id="request-form" class="app-card p-5 sm:p-6" aria-labelledby="request-heading">
                <div class="mb-5 flex items-start gap-3">
                    <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#005740] text-white">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v4M17 3v4M4 9h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8"/></svg>
                    </div>
                    <div>
                        <h2 id="request-heading" class="text-xl font-extrabold text-slate-900">Request Reservation</h2>
                        <p class="mt-1 text-sm text-slate-600">Operating hours: {{ formatTime(operatingHours.opening) }}–{{ formatTime(operatingHours.closing) }}</p>
                    </div>
                </div>

                <div v-if="Object.keys(form.errors).length" data-reservation-errors role="alert" class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                    <strong>Please correct the highlighted fields.</strong>
                    <ul class="mt-1 list-disc pl-5"><li v-for="error in form.errors" :key="error">{{ error }}</li></ul>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Reservation date
                            <input v-model="form.reservation_date" class="app-field" type="date" :min="today" required />
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Expected attendees
                            <input v-model.number="form.attendees" class="app-field" type="number" min="1" required />
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Start time
                            <input v-model="form.start_time" class="app-field" type="time" :min="operatingHours.opening" :max="operatingHours.closing" required />
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            End time
                            <input v-model="form.end_time" class="app-field" type="time" :min="form.start_time || operatingHours.opening" :max="operatingHours.closing" required />
                        </label>
                    </div>

                    <div aria-live="polite" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-extrabold text-slate-900">Available room/facility</p>
                                <p class="text-xs text-slate-500">Rooms update when the date, time, or attendees change.</p>
                            </div>
                            <span v-if="availabilityLoading" class="text-xs font-semibold text-[#005740]">Checking…</span>
                            <span v-else-if="canCheckAvailability" class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">{{ availableRooms.length }} available</span>
                        </div>
                        <select v-model="form.room_id" class="app-field mt-3 w-full" :disabled="availabilityLoading || !availableRooms.length" required>
                            <option value="">{{ canCheckAvailability ? 'Select an available room' : 'Choose date and time first' }}</option>
                            <option v-for="room in availableRooms" :key="room.id" :value="room.id">
                                {{ room.room_code }} — {{ room.room_name }} (capacity {{ room.capacity || 'not specified' }})
                            </option>
                        </select>
                        <p v-if="availabilityError" class="mt-2 text-sm font-semibold text-red-700">{{ availabilityError }}</p>
                        <p v-if="selectedRoom" class="mt-2 text-xs text-slate-600">
                            {{ selectedRoom.building || selectedRoom.location || 'Campus facility' }} · Capacity {{ selectedRoom.capacity || 'not specified' }}
                        </p>
                    </div>

                    <div v-if="unavailableRooms.length" class="rounded-xl border border-red-200 bg-red-50/60 p-4">
                        <h3 class="text-sm font-extrabold text-red-900">Unavailable rooms and time slots</h3>
                        <div class="mt-3 max-h-44 space-y-2 overflow-y-auto pr-1">
                            <div v-for="room in unavailableRooms" :key="room.id" class="rounded-lg border border-red-200 bg-white p-3 text-xs text-slate-700">
                                <strong class="text-sm text-slate-900">{{ room.room_code }} — {{ room.room_name }}</strong>
                                <p v-if="room.unavailable_reason" class="mt-1 font-semibold text-red-700">{{ room.unavailable_reason }}</p>
                                <p v-for="conflict in room.conflicts" :key="`${conflict.source}-${conflict.start_time}-${conflict.end_time}`" class="mt-1">
                                    {{ formatTime(conflict.start_time) }}–{{ formatTime(conflict.end_time) }} · {{ conflict.label }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Purpose or activity
                        <input v-model="form.purpose" class="app-field" type="text" maxlength="255" placeholder="e.g. Student organization planning meeting" required />
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Optional remarks
                        <textarea v-model="form.remarks" class="app-field min-h-24" maxlength="2000" placeholder="Accessibility, setup, or other relevant notes"></textarea>
                    </label>

                    <button type="submit" class="app-button-primary w-full py-3" :disabled="form.processing || !form.room_id">
                        {{ form.processing ? 'Submitting request…' : 'Submit reservation request' }}
                    </button>
                </form>
            </section>

            <section class="app-card overflow-hidden" aria-labelledby="my-requests-heading">
                <div class="border-b border-slate-200 p-5 sm:p-6">
                    <h2 id="my-requests-heading" class="text-xl font-extrabold text-slate-900">My Reservation Requests</h2>
                    <p class="mt-1 text-sm text-slate-600">Only requests submitted through your account are shown.</p>
                </div>

                <div v-if="requestsList.length" class="divide-y divide-slate-200">
                    <article v-for="requestItem in requestsList" :key="requestItem.id" class="p-5 transition hover:bg-slate-50 sm:p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-extrabold text-slate-900">{{ requestItem.room?.room_code }} — {{ requestItem.room?.room_name }}</h3>
                                    <StatusBadge :status="requestItem.status" size="md" />
                                </div>
                                <p class="mt-2 text-sm font-semibold text-slate-700">{{ formatDate(requestItem.reservation_date) }} · {{ formatTime(requestItem.start_time) }}–{{ formatTime(requestItem.end_time) }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ requestItem.purpose }} · {{ requestItem.attendees }} attendees</p>
                                <p class="mt-1 text-xs text-slate-500">Submitted {{ formatDate(requestItem.created_at, true) }}</p>
                            </div>
                            <Link :href="`/MyReservations/${requestItem.id}`" class="app-button-secondary shrink-0">View details</Link>
                        </div>
                        <div v-if="requestItem.admin_response" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                            <strong>Administrator response:</strong> {{ requestItem.admin_response }}
                        </div>
                    </article>
                </div>

                <div v-else class="p-10 text-center">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-slate-100 text-slate-500">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v4M17 3v4M4 9h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/></svg>
                    </div>
                    <h3 class="mt-3 font-extrabold text-slate-900">No {{ filters.status === 'all' ? '' : filters.status }} requests</h3>
                    <p class="mt-1 text-sm text-slate-500">Use the form to submit your first reservation request.</p>
                </div>

                <nav v-if="requests.links?.length > 3" aria-label="Reservation request pages" class="flex flex-wrap gap-2 border-t border-slate-200 p-4">
                    <Link
                        v-for="link in requests.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="modern-page-button"
                        :class="{ 'modern-page-button-active': link.active, 'pointer-events-none opacity-50': !link.url }"
                        v-html="link.label"
                    />
                </nav>
            </section>
        </div>
    </AppLayout>
</template>
