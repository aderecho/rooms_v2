<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { isFinalAppointmentStatus, getAppointmentStatusLabel } from '@/utils/scheduleStatus';
import StatusBadge from '@/Components/ScheduleModal/StatusBadge.vue';

const panelOpen = ref(false);
const loading = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const isAdmin = ref(false);
const statusUpdateLoadingId = ref(null);
const successToast = ref({ visible: false, message: '' });
const clearConfirm = ref({ visible: false, loading: false });
let pollTimer = null;
let successToastTimer = null;

const showStatusSuccess = (message) => {
    successToast.value = { visible: true, message };
    if (successToastTimer) {
        window.clearTimeout(successToastTimer);
    }
    successToastTimer = window.setTimeout(() => {
        successToast.value = { visible: false, message: '' };
    }, 3000);
};

const onStatusSuccess = (event) => {
    if (event?.detail?.message) {
        showStatusSuccess(event.detail.message);
    }
};

const fetchNotifications = async () => {
    try {
        loading.value = notifications.value.length === 0;
        const res = await axios.get('/api/schedule-notifications');
        notifications.value = res.data.notifications || [];
        unreadCount.value = res.data.unread_count || 0;
        isAdmin.value = !!res.data.is_admin;
    } catch (err) {
        console.error('Failed to load notifications:', err);
    } finally {
        loading.value = false;
    }
};

const togglePanel = async () => {
    panelOpen.value = !panelOpen.value;
    if (panelOpen.value) {
        await fetchNotifications();
    }
};

const closePanel = () => {
    panelOpen.value = false;
    clearConfirm.value.visible = false;
};

const markAsRead = async (notification) => {
    if (notification.read_at) return;
    try {
        await axios.patch(`/api/schedule-notifications/${notification.id}/read`);
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (err) {
        console.error('Failed to mark notification as read:', err);
    }
};

const markAllAsRead = async () => {
    try {
        await axios.patch('/api/schedule-notifications/read-all');
        notifications.value = notifications.value.map((item) => ({
            ...item,
            read_at: item.read_at || new Date().toISOString(),
        }));
        unreadCount.value = 0;
    } catch (err) {
        console.error('Failed to mark all notifications as read:', err);
    }
};

const markAllAsUnread = async () => {
    try {
        await axios.patch('/api/schedule-notifications/unread-all');
        notifications.value = notifications.value.map((item) => ({
            ...item,
            read_at: null,
        }));
        unreadCount.value = notifications.value.length;
    } catch (err) {
        console.error('Failed to mark all notifications as unread:', err);
    }
};

const toggleAllReadState = () => {
    if (unreadCount.value > 0) {
        markAllAsRead();
        return;
    }
    markAllAsUnread();
};

const openClearConfirm = () => {
    if (!hasNotifications.value) return;
    clearConfirm.value.visible = true;
};

const closeClearConfirm = () => {
    if (clearConfirm.value.loading) return;
    clearConfirm.value.visible = false;
};

const confirmClearAll = async () => {
    if (!hasNotifications.value) return;

    clearConfirm.value.loading = true;
    try {
        await axios.delete('/api/schedule-notifications/clear-all');
        notifications.value = [];
        unreadCount.value = 0;
        clearConfirm.value.visible = false;
    } catch (err) {
        console.error('Failed to clear notifications:', err);
        alert(err.response?.data?.message || 'Failed to clear notifications.');
    } finally {
        clearConfirm.value.loading = false;
    }
};

const updateAppointmentStatus = async (notification, status) => {
    if (!isAdmin.value || !notification.schedule_id) return;
    if (isFinalAppointmentStatus(notification.schedule?.status)) return;

    statusUpdateLoadingId.value = notification.id;
    try {
        await axios.patch(`/Schedule/${notification.schedule_id}/status`, { status });
        if (notification.schedule) {
            notification.schedule.status = status;
        }
        await fetchNotifications();
        window.dispatchEvent(new CustomEvent('appointment-notifications:refresh'));
        showStatusSuccess(`Appointment status updated to ${getAppointmentStatusLabel(status)}.`);
    } catch (err) {
        console.error('Failed to update appointment status:', err);
        alert(err.response?.data?.message || 'Failed to update status.');
    } finally {
        statusUpdateLoadingId.value = null;
    }
};

const formatDate = (value) => {
    if (!value) return 'N/A';
    const d = new Date(value);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatTime = (value) => {
    if (!value) return '';
    const str = String(value);

    if (str.includes('T') || str.includes('-')) {
        const parsed = new Date(str);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
    }

    const match = str.match(/(\d{1,2}):(\d{2})/);
    if (!match) return '';

    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    const parsed = new Date(1970, 0, 1, hours, minutes);
    return parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
};

const openNotificationAppointment = async (notification) => {
    await markAsRead(notification);
    closePanel();

    if (!notification.schedule_id) return;

    const targetUrl = `/Schedule?appointment=${notification.schedule_id}`;
    if (window.location.pathname === '/Schedule') {
        window.dispatchEvent(new CustomEvent('appointment-notification:open', {
            detail: { scheduleId: notification.schedule_id },
        }));
        return;
    }

    router.visit(targetUrl);
};

const handleRefreshEvent = () => {
    fetchNotifications();
};

onMounted(() => {
    fetchNotifications();
    pollTimer = window.setInterval(fetchNotifications, 15000);
    window.addEventListener('appointment-notifications:refresh', handleRefreshEvent);
    window.addEventListener('appointment-status:success', onStatusSuccess);
});

onUnmounted(() => {
    if (pollTimer) window.clearInterval(pollTimer);
    if (successToastTimer) window.clearTimeout(successToastTimer);
    window.removeEventListener('appointment-notifications:refresh', handleRefreshEvent);
    window.removeEventListener('appointment-status:success', onStatusSuccess);
});

const hasNotifications = computed(() => notifications.value.length > 0);
</script>

<template>
    <div class="relative">
        <div
            v-if="successToast.visible"
            class="fixed top-20 right-6 z-[9999] bg-green-600 text-white px-5 py-4 rounded-lg shadow-lg max-w-sm"
        >
            <p class="font-semibold">Status Updated!</p>
            <p class="text-green-100 text-sm mt-0.5">{{ successToast.message }}</p>
        </div>

        <button
            type="button"
            class="relative p-2 rounded-full hover:bg-white/10 transition"
            title="Appointment notifications"
            @click="togglePanel"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9" />
            </svg>
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-[#7A0C23]"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div
            v-if="panelOpen"
            class="fixed inset-0 z-[90] bg-black/30"
            @click.self="closePanel"
        >
            <div class="absolute right-4 top-16 w-[28rem] max-w-[calc(100vw-2rem)] bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-[#7A0C23] text-white">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-base font-semibold">Appointment Notifications</h3>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-1 rounded-full flex items-center gap-1.5"
                                :class="unreadCount > 0 ? 'bg-red-500/90 text-white' : 'bg-white/15 text-white'"
                            >
                                <span
                                    v-if="unreadCount > 0"
                                    class="h-2 w-2 rounded-full bg-white shrink-0"
                                    aria-hidden="true"
                                />
                                {{ unreadCount }} unread
                            </span>
                            <button
                                v-if="hasNotifications"
                                type="button"
                                class="text-xs underline opacity-90 hover:opacity-100"
                                @click="toggleAllReadState"
                            >
                                {{ unreadCount > 0 ? 'Mark all read' : 'Mark as unread' }}
                            </button>
                            <button
                                v-if="hasNotifications"
                                type="button"
                                class="text-xs underline opacity-90 hover:opacity-100"
                                @click="openClearConfirm"
                            >
                                Clear all
                            </button>
                        </div>
                    </div>
                </div>

                <div class="max-h-[70vh] overflow-y-auto p-3 space-y-2">
                    <div v-if="loading" class="text-sm text-gray-500 text-center py-8">
                        Loading notifications...
                    </div>

                    <div
                        v-for="notification in notifications"
                        :key="notification.id"
                        class="rounded-lg border p-3 bg-white cursor-pointer transition hover:border-[#7A0C23]/40 hover:shadow-sm"
                        :class="notification.read_at ? 'border-gray-200' : 'border-[#7A0C23]/30 bg-red-50/30'"
                        @click="openNotificationAppointment(notification)"
                    >
                        <div class="min-w-0">
                        <div class="flex items-start justify-between gap-2 text-gray-900">
                            <p class="text-sm font-semibold text-gray-900 truncate min-w-0">
                                {{ notification.title }}
                            </p>
                            <div class="flex items-center gap-2 shrink-0">
                                <StatusBadge
                                    v-if="notification.type === 'booking_confirmation'"
                                    status="pending"
                                    size="sm"
                                />
                                <StatusBadge
                                    v-else-if="notification.schedule?.status"
                                    :status="notification.schedule.status"
                                    size="sm"
                                />
                                <span
                                    v-if="!notification.read_at"
                                    class="h-3 w-3 rounded-full bg-red-600"
                                    aria-label="Unread notification"
                                />
                            </div>
                        </div>

                        <p class="mt-1 text-xs text-gray-600">{{ notification.message }}</p>

                        <div v-if="notification.schedule" class="mt-2 text-xs text-gray-700 space-y-1">
                            <p><span class="font-semibold">Room</span>: {{ notification.schedule.room }}</p>
                            <p><span class="font-semibold">Date</span>: {{ formatDate(notification.schedule.date) }}</p>
                            <p v-if="notification.schedule.start_time">
                                <span class="font-semibold">Time</span>:
                                {{ formatTime(notification.schedule.start_time) }}
                                <span v-if="notification.schedule.end_time">- {{ formatTime(notification.schedule.end_time) }}</span>
                            </p>
                        </div>

                        <div
                            v-if="isAdmin && notification.schedule_id && notification.type === 'appointment_created' && !isFinalAppointmentStatus(notification.schedule?.status)"
                            class="mt-3 flex gap-1 justify-end"
                        >
                            <button
                                type="button"
                                class="px-2 py-1 text-[11px] rounded bg-green-100 text-green-800 hover:bg-green-200 disabled:opacity-50"
                                :disabled="statusUpdateLoadingId === notification.id"
                                @click.stop="updateAppointmentStatus(notification, 'approved')"
                            >
                                Approve
                            </button>
                            <button
                                type="button"
                                class="px-2 py-1 text-[11px] rounded bg-red-100 text-red-800 hover:bg-red-200 disabled:opacity-50"
                                :disabled="statusUpdateLoadingId === notification.id"
                                @click.stop="updateAppointmentStatus(notification, 'rejected')"
                            >
                                Reject
                            </button>
                            <button
                                type="button"
                                class="px-2 py-1 text-[11px] rounded bg-gray-100 text-gray-800 hover:bg-gray-200 disabled:opacity-50"
                                :disabled="statusUpdateLoadingId === notification.id"
                                @click.stop="updateAppointmentStatus(notification, 'cancelled')"
                            >
                                Cancel
                            </button>
                        </div>
                        <p
                            v-else-if="isAdmin && isFinalAppointmentStatus(notification.schedule?.status)"
                            class="mt-3 text-[11px] text-gray-500 text-right"
                        >
                            This appointment is closed and can no longer be updated.
                        </p>
                        </div>
                    </div>

                    <div
                        v-if="!loading && !hasNotifications"
                        class="text-sm text-gray-500 text-center py-8"
                    >
                        No appointment notifications yet.
                    </div>
                </div>
            </div>
        </div>

        <!-- Clear all confirmation -->
        <div
            v-if="clearConfirm.visible"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            @click.self="closeClearConfirm"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden" @click.stop>
                <div class="bg-[#7A0C23] px-6 py-4">
                    <h3 class="text-xl font-semibold text-white">Clear All Notifications</h3>
                </div>

                <div class="p-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                    </div>

                    <p class="text-center text-gray-700 mb-2">
                        Are you sure you want to clear all appointment notifications?
                    </p>
                    <p class="text-center text-sm text-gray-500 mb-4">
                        This will remove <strong class="text-gray-800">{{ notifications.length }}</strong>
                        notification{{ notifications.length === 1 ? '' : 's' }}.
                        This action cannot be undone.
                    </p>

                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-200">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 transition disabled:opacity-50"
                            :disabled="clearConfirm.loading"
                            @click="closeClearConfirm"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg font-medium text-white bg-[#7A0C23] hover:opacity-90 transition disabled:opacity-50"
                            :disabled="clearConfirm.loading"
                            @click="confirmClearAll"
                        >
                            {{ clearConfirm.loading ? 'Clearing...' : 'Yes, Clear All' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
