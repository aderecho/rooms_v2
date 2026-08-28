<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/ScheduleModal/StatusBadge.vue';
import { confirmDialog } from '@/Composables/useAppDialog.js';

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    totals: { type: Object, default: () => ({ all: 0, pending: 0, approved: 0, rejected: 0 }) },
    filters: { type: Object, default: () => ({ search: '', status: 'pending', date: '' }) },
});

const page = usePage();
const search = ref(props.filters.search || '');
const date = ref(props.filters.date || '');
const rejectionTarget = ref(null);
const approvingId = ref(null);
const rejectForm = useForm({ admin_response: '' });
const requestsList = computed(() => props.requests?.data || []);

const tabs = computed(() => [
    { key: 'pending', label: 'Pending', count: props.totals.pending, classes: 'border-amber-400 bg-amber-50 text-amber-900' },
    { key: 'approved', label: 'Approved', count: props.totals.approved, classes: 'border-emerald-500 bg-emerald-50 text-emerald-900' },
    { key: 'rejected', label: 'Rejected', count: props.totals.rejected, classes: 'border-red-500 bg-red-50 text-red-900' },
    { key: 'all', label: 'All', count: props.totals.all, classes: 'border-slate-300 bg-slate-50 text-slate-800' },
]);

const formatDate = (value, includeTime = false) => {
    if (!value) return '—';
    const parsed = includeTime ? new Date(value) : new Date(`${value}T00:00:00`);
    return parsed.toLocaleDateString('en-US', includeTime
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

const visitFilters = (status = props.filters.status) => {
    router.get('/ReservationRequests', {
        status,
        search: search.value || undefined,
        date: date.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const clearFilters = () => {
    search.value = '';
    date.value = '';
    router.get('/ReservationRequests', { status: props.filters.status }, { replace: true });
};

const approve = async (requestItem) => {
    const confirmed = await confirmDialog(
        `Approve ${requestItem.student?.name}'s reservation for ${requestItem.room?.room_name}? The approved time will be added to the room calendar.`,
        { title: 'Approve reservation request', confirmLabel: 'Approve request' },
    );
    if (!confirmed) return;
    approvingId.value = requestItem.id;
    router.patch(`/ReservationRequests/${requestItem.id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => { approvingId.value = null; },
    });
};

const openReject = (requestItem) => {
    rejectionTarget.value = requestItem;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const closeReject = () => {
    if (rejectForm.processing) return;
    rejectionTarget.value = null;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const reject = () => {
    if (!rejectionTarget.value) return;
    rejectForm.patch(`/ReservationRequests/${rejectionTarget.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: closeReject,
    });
};
</script>

<template>
    <AppLayout>
        <header class="app-page-header">
            <div>
                <span class="app-breadcrumb">Administration</span>
                <h1 class="app-page-title">Reservation Requests</h1>
                <p class="mt-2 text-sm text-slate-600">Review student requests, verify schedules, and record an explicit decision.</p>
            </div>
        </header>

        <div v-if="page.props.flash?.success" role="status" class="mb-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors || {}).length" role="alert" class="mb-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900">
            <strong>The request could not be updated.</strong>
            <ul class="mt-1 list-disc pl-5"><li v-for="error in page.props.errors" :key="error">{{ error }}</li></ul>
        </div>

        <nav class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Reservation status filters">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-xl border-l-4 p-4 text-left shadow-sm transition hover:-translate-y-0.5"
                :class="[tab.classes, filters.status === tab.key ? 'ring-2 ring-[#005740] ring-offset-2' : '']"
                :aria-pressed="filters.status === tab.key"
                @click="visitFilters(tab.key)"
            >
                <span class="text-xs font-extrabold uppercase tracking-[0.12em]">{{ tab.label }}</span>
                <strong class="mt-2 block text-3xl">{{ tab.count }}</strong>
            </button>
        </nav>

        <section class="app-card mt-6 p-4 sm:p-5" aria-label="Reservation request filters">
            <form class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto_auto] md:items-end" @submit.prevent="visitFilters()">
                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                    Search student, room, or purpose
                    <input v-model="search" type="search" class="app-field" placeholder="Name, email, room code, purpose…" />
                </label>
                <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                    Reservation date
                    <input v-model="date" type="date" class="app-field" />
                </label>
                <button type="submit" class="app-button-primary">Apply filters</button>
                <button type="button" class="app-button-secondary" @click="clearFilters">Clear</button>
            </form>
        </section>

        <section class="mt-6 space-y-4" aria-live="polite">
            <article
                v-for="requestItem in requestsList"
                :key="requestItem.id"
                class="app-card overflow-hidden border-l-4"
                :class="{
                    'border-l-amber-500': requestItem.status === 'pending',
                    'border-l-emerald-600': requestItem.status === 'approved',
                    'border-l-red-600': requestItem.status === 'rejected',
                }"
            >
                <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.42fr)] lg:p-6">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <StatusBadge :status="requestItem.status" size="md" />
                            <span class="text-xs font-bold uppercase tracking-[0.1em] text-slate-500">Request #{{ requestItem.id }}</span>
                        </div>
                        <h2 class="mt-3 text-xl font-extrabold text-slate-900">{{ requestItem.room?.room_code }} — {{ requestItem.room?.room_name }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ requestItem.room?.building || requestItem.room?.location || 'Campus facility' }}</p>

                        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Student</dt><dd class="mt-1 font-semibold text-slate-900">{{ requestItem.student?.name }}<br><span class="font-normal text-slate-500">{{ requestItem.student?.email }}</span></dd></div>
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Schedule</dt><dd class="mt-1 font-semibold text-slate-900">{{ formatDate(requestItem.reservation_date) }}<br>{{ formatTime(requestItem.start_time) }}–{{ formatTime(requestItem.end_time) }}</dd></div>
                            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Attendees</dt><dd class="mt-1 font-semibold text-slate-900">{{ requestItem.attendees }} <span class="font-normal text-slate-500">/ capacity {{ requestItem.room?.capacity || 'not specified' }}</span></dd></div>
                            <div class="sm:col-span-2 xl:col-span-3"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Purpose</dt><dd class="mt-1 font-semibold text-slate-900">{{ requestItem.purpose }}</dd></div>
                            <div v-if="requestItem.remarks" class="sm:col-span-2 xl:col-span-3"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Remarks</dt><dd class="mt-1 text-slate-700">{{ requestItem.remarks }}</dd></div>
                        </dl>

                        <div v-if="requestItem.admin_response" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                            <strong>Administrator response:</strong> {{ requestItem.admin_response }}
                        </div>
                    </div>

                    <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Submitted</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatDate(requestItem.created_at, true) }}</p>
                        <p v-if="requestItem.reviewer" class="mt-4 text-xs text-slate-600">Reviewed by <strong>{{ requestItem.reviewer.name }}</strong></p>
                        <div class="mt-5 grid gap-2">
                            <Link :href="`/ReservationRequests/${requestItem.id}`" class="app-button-secondary w-full">View complete details</Link>
                            <template v-if="requestItem.status === 'pending'">
                                <button type="button" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-emerald-800 disabled:opacity-60" :disabled="approvingId === requestItem.id" @click="approve(requestItem)">
                                    {{ approvingId === requestItem.id ? 'Approving…' : 'Approve request' }}
                                </button>
                                <button type="button" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-red-800" @click="openReject(requestItem)">Reject with message</button>
                            </template>
                        </div>
                    </aside>
                </div>
            </article>

            <div v-if="!requestsList.length" class="app-card p-12 text-center">
                <h2 class="text-lg font-extrabold text-slate-900">No {{ filters.status === 'all' ? '' : filters.status }} reservation requests</h2>
                <p class="mt-2 text-sm text-slate-500">Try another status or clear the search and date filters.</p>
            </div>
        </section>

        <nav v-if="requests.links?.length > 3" aria-label="Reservation request pages" class="mt-5 flex flex-wrap gap-2">
            <Link
                v-for="link in requests.links"
                :key="link.label"
                :href="link.url || '#'"
                class="modern-page-button"
                :class="{ 'modern-page-button-active': link.active, 'pointer-events-none opacity-50': !link.url }"
                v-html="link.label"
            />
        </nav>

        <div v-if="rejectionTarget" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="reject-title" @click.self="closeReject">
            <form class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl" @submit.prevent="reject">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="text-xs font-extrabold uppercase tracking-[0.12em] text-red-700">Rejection required</span>
                        <h2 id="reject-title" class="mt-1 text-2xl font-extrabold text-slate-900">Compose rejection message</h2>
                    </div>
                    <button type="button" class="text-2xl text-slate-500" aria-label="Close rejection form" @click="closeReject">×</button>
                </div>
                <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                    <strong>{{ rejectionTarget.student?.name }}</strong> · {{ rejectionTarget.room?.room_name }} · {{ formatDate(rejectionTarget.reservation_date) }}
                </div>
                <label class="mt-5 grid gap-1.5 text-sm font-semibold text-slate-700">
                    Message to student <span class="text-red-700">*</span>
                    <textarea v-model="rejectForm.admin_response" class="app-field min-h-36" maxlength="2000" required placeholder="Explain why the request cannot be approved and, when possible, suggest an alternative."></textarea>
                </label>
                <p v-if="rejectForm.errors.admin_response" class="mt-1 text-sm font-semibold text-red-700">{{ rejectForm.errors.admin_response }}</p>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="app-button-secondary" :disabled="rejectForm.processing" @click="closeReject">Cancel</button>
                    <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-red-800 disabled:opacity-60" :disabled="rejectForm.processing || !rejectForm.admin_response.trim()">
                        {{ rejectForm.processing ? 'Rejecting…' : 'Confirm rejection' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
