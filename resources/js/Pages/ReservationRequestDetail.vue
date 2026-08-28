<script setup>
import { computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/ScheduleModal/StatusBadge.vue';
import { confirmDialog } from '@/Composables/useAppDialog.js';

const props = defineProps({
    reservationRequest: { type: Object, required: true },
    viewerMode: { type: String, required: true },
});

const page = usePage();
const item = computed(() => props.reservationRequest?.data || props.reservationRequest);
const isAdmin = computed(() => props.viewerMode === 'admin');
const backUrl = computed(() => isAdmin.value ? '/ReservationRequests' : '/MyReservations');
const rejectForm = useForm({ admin_response: '' });

const formatDate = (value, includeTime = false) => {
    if (!value) return '—';
    const parsed = includeTime ? new Date(value) : new Date(`${value}T00:00:00`);
    return parsed.toLocaleDateString('en-US', includeTime
        ? { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }
        : { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
};

const formatTime = (value) => {
    const [hour, minute] = String(value || '').split(':').map(Number);
    if (!Number.isFinite(hour)) return value || '—';
    return new Date(1970, 0, 1, hour, minute).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
};

const approve = async () => {
    const confirmed = await confirmDialog(
        `Approve ${item.value.student?.name}'s reservation for ${item.value.room?.room_name}? The approved time will be added to the room calendar.`,
        { title: 'Approve reservation request', confirmLabel: 'Approve request' },
    );
    if (!confirmed) return;
    router.patch(`/ReservationRequests/${item.value.id}/approve`, {}, { preserveScroll: true });
};

const reject = () => {
    rejectForm.patch(`/ReservationRequests/${item.value.id}/reject`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <header class="app-page-header">
            <div>
                <span class="app-breadcrumb">{{ isAdmin ? 'Reservation Administration' : 'My Reservations' }}</span>
                <h1 class="app-page-title">Reservation Request #{{ item.id }}</h1>
            </div>
            <Link :href="backUrl" class="app-button-secondary">← Back to requests</Link>
        </header>

        <div v-if="page.props.flash?.success" role="status" class="mb-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-semibold text-emerald-900">
            {{ page.props.flash.success }}
        </div>
        <div v-if="Object.keys(page.props.errors || {}).length" role="alert" class="mb-5 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900">
            <ul class="list-disc pl-5"><li v-for="error in page.props.errors" :key="error">{{ error }}</li></ul>
        </div>

        <section class="app-card overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-7">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <StatusBadge :status="item.status" size="md" />
                        <span class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-500">Submitted {{ formatDate(item.created_at, true) }}</span>
                    </div>
                    <h2 class="mt-4 text-2xl font-extrabold text-slate-900">{{ item.room?.room_code }} — {{ item.room?.room_name }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ item.room?.building || item.room?.location || 'Campus facility' }}</p>
                </div>
            </div>

            <div class="grid gap-6 p-5 lg:grid-cols-2 sm:p-7">
                <div class="space-y-5">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-500">Student</p>
                        <p class="mt-1 font-bold text-slate-900">{{ item.student?.name }}</p>
                        <p class="text-sm text-slate-600">{{ item.student?.email }}</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><p class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-500">Date</p><p class="mt-1 font-bold text-slate-900">{{ formatDate(item.reservation_date) }}</p></div>
                        <div><p class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-500">Time</p><p class="mt-1 font-bold text-slate-900">{{ formatTime(item.start_time) }}–{{ formatTime(item.end_time) }}</p></div>
                        <div><p class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-500">Attendees</p><p class="mt-1 font-bold text-slate-900">{{ item.attendees }} / {{ item.room?.capacity || 'No capacity listed' }}</p></div>
                        <div><p class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-500">Calendar entry</p><p class="mt-1 font-bold text-slate-900">{{ item.schedule_id ? `Schedule #${item.schedule_id}` : 'Not created' }}</p></div>
                    </div>
                    <div><p class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-500">Purpose</p><p class="mt-1 font-semibold text-slate-900">{{ item.purpose }}</p></div>
                    <div><p class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-500">Remarks</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ item.remarks || 'No remarks provided.' }}</p></div>
                </div>

                <aside class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                    <h3 class="font-extrabold text-slate-900">Decision record</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-slate-500">Reviewed by</dt><dd class="font-semibold text-slate-900">{{ item.reviewer?.name || 'Awaiting administrator' }}</dd></div>
                        <div v-if="item.approved_at"><dt class="text-slate-500">Approved</dt><dd class="font-semibold text-emerald-800">{{ formatDate(item.approved_at, true) }}</dd></div>
                        <div v-if="item.rejected_at"><dt class="text-slate-500">Rejected</dt><dd class="font-semibold text-red-800">{{ formatDate(item.rejected_at, true) }}</dd></div>
                    </dl>
                    <div v-if="item.admin_response" class="mt-4 rounded-lg border border-red-200 bg-white p-4 text-sm text-red-900">
                        <strong class="block">Administrator response</strong>
                        <p class="mt-1 whitespace-pre-line">{{ item.admin_response }}</p>
                    </div>

                    <div v-if="isAdmin && item.status === 'pending'" class="mt-5 border-t border-slate-200 pt-5">
                        <button type="button" class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-emerald-800" @click="approve">Approve and add to calendar</button>
                        <form class="mt-4" @submit.prevent="reject">
                            <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                                Required rejection message
                                <textarea v-model="rejectForm.admin_response" class="app-field min-h-28" maxlength="2000" required placeholder="Explain the rejection to the student."></textarea>
                            </label>
                            <p v-if="rejectForm.errors.admin_response" class="mt-1 text-sm font-semibold text-red-700">{{ rejectForm.errors.admin_response }}</p>
                            <button type="submit" class="mt-3 w-full rounded-lg bg-red-700 px-4 py-2.5 text-sm font-extrabold text-white hover:bg-red-800 disabled:opacity-60" :disabled="rejectForm.processing || !rejectForm.admin_response.trim()">
                                {{ rejectForm.processing ? 'Rejecting…' : 'Reject with message' }}
                            </button>
                        </form>
                    </div>
                </aside>
            </div>
        </section>
    </AppLayout>
</template>
