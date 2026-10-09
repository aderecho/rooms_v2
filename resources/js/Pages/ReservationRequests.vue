<script setup>
import ModalDialog from '@/Components/ModalDialog.vue'
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/ScheduleModal/StatusBadge.vue';
import { confirmDialog, notifyDialog } from '@/Composables/useAppDialog.js';

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    totals: { type: Object, default: () => ({ all: 0, pending: 0, approved: 0, rejected: 0 }) },
    filters: { type: Object, default: () => ({ search: '', status: 'pending', date: '' }) },
});

const page = usePage();
const search = ref(props.filters.search || '');
const date = ref(props.filters.date || '');
const submittedDate = ref(props.filters.submitted_date || '');
const selectedStatus = ref(props.filters.status || 'pending');
const perPage = ref(Number(props.filters.per_page || 10));
const loading = ref(false);
watch(() => props.filters, (filters) => {
    search.value = filters.search || '';
    date.value = filters.date || '';
    submittedDate.value = filters.submitted_date || '';
    selectedStatus.value = filters.status || 'pending';
    perPage.value = Number(filters.per_page || 10);
});
const rejectionTarget = ref(null);
const rejectionConfirming = ref(false);
const approvingId = ref(null);
const rejectForm = useForm({ admin_response: '' });
const paginationLinks = computed(() => props.requests?.meta?.links || (Array.isArray(props.requests?.links) ? props.requests.links : []));
const requestsList = computed(() => props.requests?.data || []);

const tabs = computed(() => [
    { key: 'all', label: 'All Requests', count: props.totals.all || 0, description: 'Total reservation requests', color: '#005740' },
    { key: 'pending', label: 'Pending', count: props.totals.pending || 0, description: 'Awaiting your review', color: '#d99520' },
    { key: 'approved', label: 'Approved', count: props.totals.approved || 0, description: 'Confirmed reservations', color: '#26775b' },
    { key: 'rejected', label: 'Rejected', count: props.totals.rejected || 0, description: 'Requests declined', color: '#b33d36' },
]);
const statusBreakdown = computed(() => tabs.value.filter(tab => tab.key !== 'all').map(tab => ({
    ...tab,
    percentage: props.totals.all ? Math.round(tab.count / props.totals.all * 100) : 0,
    share: props.totals.all ? tab.count / props.totals.all * 100 : 0,
})));
const radarPoint = (index, ratio = 1) => {
    const angle = -Math.PI / 2 + index * Math.PI * 2 / 3;
    return { x: 110 + Math.cos(angle) * 70 * ratio, y: 110 + Math.sin(angle) * 70 * ratio };
};
const radarGrid = (ratio) => [0, 1, 2].map(index => {
    const point = radarPoint(index, ratio);
    return `${point.x},${point.y}`;
}).join(' ');
const radarMaximum = computed(() => Math.max(1, ...statusBreakdown.value.map(status => status.count)));
const radarPoints = computed(() => statusBreakdown.value.map((status, index) => ({
    ...status, ...radarPoint(index, status.count / radarMaximum.value),
})));
const radarPolygon = computed(() => radarPoints.value.map(point => `${point.x},${point.y}`).join(' '));

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

const visitFilters = (status = selectedStatus.value) => {
    selectedStatus.value = status;
    router.get('/ReservationRequests', {
        status,
        search: search.value || undefined,
        date: date.value || undefined,
        submitted_date: submittedDate.value || undefined,
        per_page: perPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true, onStart: () => { loading.value = true; }, onFinish: () => { loading.value = false; } });
};

const clearFilters = () => {
    search.value = '';
    date.value = '';
    submittedDate.value = '';
    selectedStatus.value = 'all';
    visitFilters('all');
};

const approve = async (requestItem) => {
    if (approvingId.value !== null || rejectForm.processing) return;
    approvingId.value = requestItem.id;
    const confirmed = await confirmDialog(
        `Approve ${requestItem.student?.name}'s reservation for ${requestItem.room?.room_name}? The approved time will be added to the room calendar.`,
        { title: 'Approve reservation request', confirmLabel: 'Approve request' },
    );
    if (!confirmed) { approvingId.value = null; return; }
    router.patch(`/ReservationRequests/${requestItem.id}/approve`, {}, {
        preserveScroll: true,
        onError: (errors) => notifyDialog(Object.values(errors).join('\n'), { title: 'Reservation could not be approved', variant: 'danger' }),
        onFinish: () => { approvingId.value = null; },
    });
};

const openReject = (requestItem) => {
    rejectionTarget.value = requestItem;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const closeReject = () => {
    if (rejectForm.processing || rejectionConfirming.value) return;
    rejectionTarget.value = null;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const reject = async () => {
    if (!rejectionTarget.value || rejectForm.processing || rejectionConfirming.value || approvingId.value !== null) return;
    rejectionConfirming.value = true;
    const confirmed = await confirmDialog('Reject this request and send the message to the student?', { title: 'Confirm rejection', confirmLabel: 'Reject request', variant: 'danger' });
    if (!confirmed) { rejectionConfirming.value = false; return; }
    rejectForm.patch(`/ReservationRequests/${rejectionTarget.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => { rejectionTarget.value = null; rejectForm.reset(); },
        onFinish: () => { rejectionConfirming.value = false; },
    });
};
const expandedRequestIds = ref(new Set());
const toggleRequest = (requestItem, event) => {
    if (event?.target.closest('a, button, input, select, textarea')) return;
    if (event && window.getSelection()?.toString()) return;
    const next = new Set(expandedRequestIds.value);
    if (next.has(requestItem.id)) next.delete(requestItem.id);
    else next.add(requestItem.id);
    expandedRequestIds.value = next;
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

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]" aria-label="Reservation overview">
            <nav class="grid gap-4 sm:grid-cols-2" aria-label="Reservation status filters">
                <button v-for="tab in tabs" :key="tab.key" type="button"
                    class="relative min-h-[150px] overflow-hidden rounded-2xl border bg-white p-5 text-left transition hover:border-[#005740] hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#005740]"
                    :class="filters.status === tab.key ? 'border-[#005740] ring-1 ring-[#005740]' : 'border-slate-200'"
                    :aria-pressed="filters.status === tab.key" :disabled="loading" @click="visitFilters(tab.key)">
                    <span class="text-sm font-medium text-slate-500">{{ tab.label }}</span>
                    <div class="mt-2 flex items-center gap-3">
                        <strong class="text-4xl font-bold tracking-tight text-slate-900">{{ tab.count }}</strong>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200" :style="{ color: tab.color }" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path v-if="tab.key === 'approved'" d="m5 12 4 4L19 6"/><path v-else-if="tab.key === 'rejected'" d="m6 6 12 12M6 18 18 6"/><template v-else-if="tab.key === 'pending'"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></template><path v-else d="M6 3h12v18H6zM9 8h6M9 12h6M9 16h4"/></svg>
                        </span>
                    </div>
                    <p class="mt-3 max-w-[65%] text-xs text-slate-500">{{ tab.description }}</p>
                    <div class="absolute bottom-5 right-5 flex h-16 items-end gap-1.5" aria-hidden="true">
                        <span v-for="status in statusBreakdown" :key="status.key" class="w-5 rounded-md" :style="{ height: `${Math.max(8, status.share * 0.64)}px`, backgroundColor: tab.key === 'all' || tab.key === status.key ? tab.color : '#eef3f0' }"></span>
                    </div>
                </button>
            </nav>
            <aside class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-xl font-bold text-slate-900">Reservation overview</h2>
                <p class="mt-1 text-xs text-slate-500">Current request distribution</p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-6">
                    <div class="w-52 shrink-0">
                        <svg viewBox="0 0 220 210" class="h-auto w-full" role="img" :aria-label="`Radar chart of request counts, scale 0 to ${radarMaximum}: ${statusBreakdown.map(status => `${status.label} ${status.count}`).join(', ')}`">
                            <polygon v-for="level in [0.25, 0.5, 0.75, 1]" :key="level" :points="radarGrid(level)" fill="none" stroke="#dce5df" stroke-dasharray="3 3" />
                            <line v-for="index in [0, 1, 2]" :key="index" x1="110" y1="110" :x2="radarPoint(index).x" :y2="radarPoint(index).y" stroke="#dce5df" />
                            <text v-for="level in [0.5, 1]" :key="`scale-${level}`" x="115" :y="110 - 70 * level" fill="#64748b" font-size="9">{{ Number((radarMaximum * level).toFixed(1)) }}</text>
                            <polygon :points="radarPolygon" fill="#26775b" fill-opacity="0.22" stroke="#005740" stroke-width="2" stroke-linejoin="round" />
                            <circle v-for="point in radarPoints" :key="point.key" :cx="point.x" :cy="point.y" r="3.5" fill="#005740"><title>{{ point.label }}: {{ point.count }} requests</title></circle>
                            <text x="110" y="23" text-anchor="middle" fill="#475569" font-size="11">Pending</text>
                            <text x="170" y="170" text-anchor="middle" fill="#475569" font-size="11">Approved</text>
                            <text x="50" y="170" text-anchor="middle" fill="#475569" font-size="11">Rejected</text>
                        </svg>
                        <p class="text-center text-xs text-slate-500">Request counts · Total {{ totals.all || 0 }}</p>
                    </div>
                    <dl class="min-w-[140px] flex-1 space-y-5">
                        <div v-for="status in statusBreakdown" :key="status.key" class="flex items-center justify-between gap-3 text-sm">
                            <dt class="flex items-center gap-2 text-slate-600"><span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: status.color }" aria-hidden="true"></span>{{ status.label }}</dt>
                            <dd class="font-bold text-slate-900">{{ status.percentage }}% <span class="text-xs font-normal text-slate-500">({{ status.count }})</span></dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </section>

        <section class="app-card mt-6 p-4 sm:p-5" aria-label="Reservation request filters">
            <form @submit.prevent="visitFilters()">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(9rem,1fr)_minmax(10rem,1fr)_auto] items-end">
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Search
                        <input v-model="search" type="search" class="app-field" placeholder="Student, room, building, or purpose…" />
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Status
                        <select v-model="selectedStatus" class="app-field"><option value="all">All Requests</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
                    </label>
                    <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                        Reservation date
                        <input v-model="date" type="date" class="app-field" />
                    </label>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="app-button-primary flex-1" :disabled="loading">{{ loading ? 'Loading…' : 'Apply' }}</button>
                        <button type="button" class="app-button-secondary" :disabled="loading" @click="clearFilters">Clear</button>
                    </div>
                </div>
                <details class="mt-3" :open="Boolean(filters.submitted_date) || Number(filters.per_page || 10) !== 10">
                    <summary class="w-fit cursor-pointer rounded text-xs font-semibold text-[#005740] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">More filters{{ submittedDate || perPage !== 10 ? ' (active)' : '' }}</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:max-w-xl">
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Submission date
                            <input v-model="submittedDate" type="date" class="app-field" />
                        </label>
                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Requests per page
                            <select v-model.number="perPage" class="app-field" @change="visitFilters()"><option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option></select>
                        </label>
                    </div>
                </details>
            </form>
        </section>

        <section class="mt-6 space-y-4" aria-live="polite" :aria-busy="loading">
            <div v-if="requestsList.length" class="app-card overflow-hidden">
                <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Reservation requests table">
                    <table class="reservation-request-table w-full min-w-[850px] text-left text-sm">
                        <caption class="sr-only">Reservation requests. Expand a row to review its details and available actions.</caption>
                        <thead class="!bg-[#005740] !text-white">
                            <tr>
                                <th scope="col" class="px-4 py-3">Request</th>
                                <th scope="col" class="px-4 py-3">Student</th>
                                <th scope="col" class="px-4 py-3">Room / Building</th>
                                <th scope="col" class="px-4 py-3">Schedule</th>
                                <th scope="col" class="px-4 py-3">Attendees</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <template v-for="requestItem in requestsList" :key="requestItem.id">
                                <tr class="cursor-pointer transition hover:bg-emerald-50/60" :class="{ 'bg-emerald-50/60': expandedRequestIds.has(requestItem.id) }" @click="toggleRequest(requestItem, $event)">
                                    <td class="px-4 py-4">
                                        <button type="button" class="flex items-center gap-2 rounded px-1 py-1 font-bold text-[#005740] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2" :aria-expanded="expandedRequestIds.has(requestItem.id)" :aria-controls="`request-details-${requestItem.id}`" :aria-label="`${expandedRequestIds.has(requestItem.id) ? 'Collapse' : 'Expand'} request #${requestItem.id}`" @click="toggleRequest(requestItem)">
                                            <svg class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-90': expandedRequestIds.has(requestItem.id) }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 4 6 6-6 6" /></svg>
                                            #{{ requestItem.id }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-4"><span class="block font-semibold text-slate-900">{{ requestItem.student?.name }}</span><span class="text-xs text-slate-500">{{ requestItem.student?.account_identifier }}</span></td>
                                    <td class="px-4 py-4"><span class="block font-semibold text-slate-900">{{ requestItem.room?.room_code }} — {{ requestItem.room?.room_name }}</span><span class="text-xs text-slate-500">{{ requestItem.room?.building || requestItem.room?.location || 'Campus facility' }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-4"><span class="block font-semibold text-slate-900">{{ formatDate(requestItem.reservation_date) }}</span><span class="text-xs text-slate-500">{{ formatTime(requestItem.start_time) }}–{{ formatTime(requestItem.end_time) }}</span></td>
                                    <td class="px-4 py-4">{{ requestItem.attendees }} / {{ requestItem.room?.capacity || '—' }}</td>
                                    <td class="px-4 py-4"><StatusBadge :status="requestItem.status" size="md" /></td>
                                </tr>
                                <tr v-show="expandedRequestIds.has(requestItem.id)" :id="`request-details-${requestItem.id}`">
                                    <td colspan="6" class="bg-slate-50/60">
                                        <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.42fr)] lg:p-6">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <StatusBadge :status="requestItem.status" size="md" />
                                                    <span class="text-xs font-bold uppercase tracking-[0.1em] text-slate-500">Request #{{ requestItem.id }}</span>
                                                </div>
                                                <h2 class="mt-3 text-xl font-extrabold text-slate-900">{{ requestItem.room?.room_code }} — {{ requestItem.room?.room_name }}</h2>
                                                <p class="mt-1 text-sm text-slate-600">{{ requestItem.room?.building || requestItem.room?.location || 'Campus facility' }}</p>

                                                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                                                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Student</dt><dd class="mt-1 font-semibold text-slate-900">{{ requestItem.student?.name }}<br><span class="font-normal text-slate-500">{{ requestItem.student?.account_identifier }}</span><br><span class="font-normal text-slate-500">{{ requestItem.student?.email }}</span></dd></div>
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
                                                        <button type="button" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-emerald-800 disabled:opacity-60" :disabled="approvingId !== null || rejectForm.processing" @click="approve(requestItem)">
                                                            {{ approvingId === requestItem.id ? 'Approving…' : 'Approve request' }}
                                                        </button>
                                                        <button type="button" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-red-800" :disabled="approvingId !== null || rejectForm.processing" @click="openReject(requestItem)">Reject with message</button>
                                                    </template>
                                                </div>
                                            </aside>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="!requestsList.length" class="app-card p-12 text-center">
                <h2 class="text-lg font-extrabold text-slate-900">No {{ filters.status === 'all' ? '' : filters.status }} reservation requests</h2>
                <p class="mt-2 text-sm text-slate-500">Try another status or clear the search and date filters.</p>
            </div>
        </section>

        <p class="mt-5 text-sm text-slate-600">Showing {{ requests.meta?.from ?? 0 }}–{{ requests.meta?.to ?? 0 }} of {{ requests.meta?.total ?? 0 }} requests</p>
        <nav v-if="paginationLinks.length > 3" aria-label="Reservation request pages" class="mt-5 flex flex-wrap gap-2">
            <Link
                v-for="link in paginationLinks"
                :key="link.label"
                :href="link.url || '#'"
                :aria-current="link.active ? 'page' : undefined" :aria-disabled="!link.url" :tabindex="link.url ? 0 : -1"
                preserve-scroll
                class="modern-page-button"
                :class="{ 'modern-page-button-active': link.active, 'pointer-events-none opacity-50': !link.url }"
                v-html="link.label"
            />
        </nav>

        <ModalDialog v-if="rejectionTarget" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="reject-title" @click.self="closeReject">
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
                    <button type="button" class="app-button-secondary" :disabled="rejectForm.processing || rejectionConfirming" @click="closeReject">Cancel</button>
                    <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-red-800 disabled:opacity-60" :disabled="rejectForm.processing || rejectionConfirming || !rejectForm.admin_response.trim()">
                        {{ rejectForm.processing ? 'Rejecting…' : 'Confirm rejection' }}
                    </button>
                </div>
            </form>
        </ModalDialog>
    </AppLayout>
</template>

<style scoped>
.reservation-request-table thead {
    background: #005740 !important;
    color: white !important;
}
.reservation-request-table thead th {
    color: white !important;
}
</style>
