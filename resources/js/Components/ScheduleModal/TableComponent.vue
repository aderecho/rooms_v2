<script setup>
import { computed, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faEye, faPenToSquare, faTrash, faClock, faChevronLeft, faChevronRight, faClipboardList } from '@fortawesome/free-solid-svg-icons';
import { isFinalAppointmentStatus, APPOINTMENT_STATUS_OPTIONS, getAppointmentStatusMeta, normalizeAppointmentStatus, getCurrentStatusPanelClass, isStatusTransitionDisabled, getAppointmentStatusLabel, getAppointmentStatusTextClass } from '@/utils/scheduleStatus';
import StatusBadge from '@/Components/ScheduleModal/StatusBadge.vue';

const icons = {
    eye: faEye,
    edit: faPenToSquare,
    delete: faTrash,
    clock: faClock,
    clipboardList: faClipboardList,
    chevronLeft: faChevronLeft,
    chevronRight: faChevronRight,
};

const props = defineProps({
    events: {
        type: Array,
        required: true
    },
    isAdmin: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits([
    'view-details',
    'edit-event',
    'delete-event',
    'update-status',
    'row-clicked'
]);

const statusModal = ref({
    visible: false,
    event: null,
    selectedStatus: 'pending',
    loading: false,
});

const statusConfirm = ref({
    visible: false,
});

const statusOptions = APPOINTMENT_STATUS_OPTIONS;

const openStatusModal = (eventObject, e) => {
    e.stopPropagation();
    if (isFinalAppointmentStatus(eventObject.extendedProps?.status)) {
        return;
    }
    statusModal.value = {
        visible: true,
        event: eventObject,
        selectedStatus: eventObject.extendedProps?.status || 'pending',
        loading: false,
    };
};

const closeStatusModal = () => {
    if (statusModal.value.loading) return;
    statusConfirm.value.visible = false;
    statusModal.value = { visible: false, event: null, selectedStatus: 'pending', loading: false };
};

const openStatusConfirm = () => {
    if (!statusModal.value.event) return;
    if (isBlockedStatusOption(statusModal.value.selectedStatus)) return;
    statusConfirm.value.visible = true;
};

const closeStatusConfirm = () => {
    if (statusModal.value.loading) return;
    statusConfirm.value.visible = false;
};

const confirmStatusChange = async () => {
    if (!statusModal.value.event) return;
    if (isBlockedStatusOption(statusModal.value.selectedStatus)) return;
    statusModal.value.loading = true;
    emit('update-status', {
        event: statusModal.value.event,
        status: statusModal.value.selectedStatus,
        onComplete: () => {
            statusModal.value.loading = false;
            statusConfirm.value.visible = false;
            closeStatusModal();
        },
        onError: () => {
            statusModal.value.loading = false;
        },
    });
};

const selectedStatusLabel = computed(() => (
    getAppointmentStatusLabel(statusModal.value.selectedStatus)
));

const currentStatusLabel = computed(() => (
    getAppointmentStatusLabel(currentAppointmentStatus.value)
));

const getStatusMeta = (status) => getAppointmentStatusMeta(status);

const currentAppointmentStatus = computed(() => (
    normalizeAppointmentStatus(statusModal.value.event?.extendedProps?.status)
));

const isCurrentStatusOption = (value) => (
    normalizeAppointmentStatus(value) === currentAppointmentStatus.value
);

const isBlockedStatusOption = (value) => (
    isStatusTransitionDisabled(currentAppointmentStatus.value, value)
);

const selectStatusOption = (value) => {
    if (isBlockedStatusOption(value)) return;
    statusModal.value.selectedStatus = value;
};

const getStatusOptionPanelClass = (option) => {
    const isCurrent = isCurrentStatusOption(option.value);
    const isBlocked = isBlockedStatusOption(option.value);
    const isSelected = statusModal.value.selectedStatus === option.value;

    if (isCurrent) {
        return `${getCurrentStatusPanelClass(option.value)} cursor-default`;
    }

    if (isBlocked) {
        return 'border-gray-200 bg-gray-50 cursor-not-allowed';
    }

    if (isSelected) {
        return `border-[#7A0C23] ring-2 ${getStatusMeta(option.value).ringClass}`;
    }

    return 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50';
};

// Pagination state
const currentPage = ref(1);
const itemsPerPage = ref(10);

const handleAction = (eventName, eventObject, e) => {
    e.stopPropagation();
    emit(eventName, eventObject);
};

const handleRowClick = (eventObject) => {
    emit('row-clicked', eventObject);
};

const formatTime = (date) => {
    if (!date) return '';
    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d)) return '';
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
};

const formatDate = (date) => {
    if (!date) return '';
    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d)) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const formatEndDate = (date) => {
    if (!date) return '';
    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d)) return '';
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const formatTimeSlot = (event) => {
    if (event.allDay) {
        return 'All Day';
    } else {
        const startTime = formatTime(event.start);
        const endTime = event.end ? formatTime(event.end) : '';
        return `${startTime} ${endTime ? '- ' + endTime : ''}`;
    }
};

const getEventType = (event) => {
    const type = event.extendedProps?.type?.toLowerCase() || event.list?.toLowerCase() || '';
    if (type.includes('event')) return 'Event';
    if (type.includes('class')) return 'Class';
    if (type.includes('meeting')) return 'Meeting';
    return 'Other Activity';
};

// Original sorted events
const sortedEvents = computed(() => {
    return [...props.events].sort((a, b) =>
        new Date(a.start).getTime() - new Date(b.start).getTime()
    );
});

// Process events for table
const processedEvents = computed(() => {
    return sortedEvents.value.map(event => {
        const extendedProps = event.extendedProps || {};

        return {
            id: event.id,
            title: event.title,
            room: extendedProps.room || event.title,
            building: extendedProps.building || 'N/A',
            college: extendedProps.college || 'N/A',
            subject: extendedProps.subject || event.title,
            startDate: formatDate(event.start),
            endDate: event.end ? formatEndDate(event.end) : formatDate(event.start),
            timeSlot: formatTimeSlot(event),
            isRecurring: event.rrule ? 'Yes' : 'No',
            eventType: getEventType(event),
            eventObject: event,
            allDay: event.allDay || false,
            type: event.list || 'Event',
            isOccupied: true,
            requester: extendedProps.requester || 'N/A',
            description: extendedProps.description || '',
            status: extendedProps.status || 'pending',
        };
    });
});

// Pagination computed properties
const totalPages = computed(() => {
    return Math.ceil(processedEvents.value.length / itemsPerPage.value);
});

const paginatedEvents = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return processedEvents.value.slice(start, end);
});

const showingRange = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value + 1;
    const end = Math.min(currentPage.value * itemsPerPage.value, processedEvents.value.length);
    const total = processedEvents.value.length;
    return { start, end, total };
});

// Pagination functions
const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// Reset pagination when events change
const resetPagination = () => {
    currentPage.value = 1;
};
</script>

<template>
    <div class="bg-white shadow-lg rounded-xl overflow-auto">
        <!-- Table Header with Pagination Controls -->
        <div class="flex flex-col sm:flex-row justify-between items-center p-4 border-b border-yellow-400">
            <div class="text-lg font-semibold text-[#7A0C23] mb-2 sm:mb-0">
                Appointments ({{ processedEvents.length }})
            </div>
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">Show:</span>
                    <select v-model="itemsPerPage" @change="resetPagination"
                            class="text-sm border border-gray-300 rounded px-2 py-1 bg-white">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                    <span class="text-sm text-gray-600">entries</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-yellow-500" style="table-layout: fixed; width: 100%;">
                <colgroup>
                    <col style="width: 20%;"> <!-- APPOINTMENT -->
                    <col style="width: 12%;"> <!-- ROOM -->
                    <col style="width: 12%;"> <!-- BUILDING -->
                    <col style="width: 12%;"> <!-- COLLEGE -->
                    <col style="width: 10%;"> <!-- START DATE -->
                    <col style="width: 10%;"> <!-- TIME SLOT -->
                    <col style="width: 9%;">  <!-- EVENT TYPE -->
                    <col style="width: 9%;">  <!-- STATUS -->
                    <col style="width: 14%;"> <!-- ACTIONS -->
                </colgroup>
                <thead class="bg-[#7A0C23] text-white">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">APPOINTMENT</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">ROOM</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">BUILDING</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">COLLEGE</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">START DATE</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">TIME SLOT</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">EVENT TYPE</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">STATUS</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider whitespace-nowrap overflow-hidden text-ellipsis">ACTIONS</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-yellow-400 bg-white">
                    <tr
                        v-for="item in paginatedEvents"
                        :key="item.id"
                        @click="handleRowClick(item.eventObject)"
                        class="transition duration-150 cursor-pointer hover:bg-blue-50"
                    >
                        <!-- APPOINTMENT Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm font-bold text-[#7A0C23] truncate" :title="item.title">{{ item.title }}</div>
                            <div class="text-xs text-gray-500 mt-1 truncate" :title="item.description">{{ item.description }}</div>
                        </td>

                        <!-- ROOM Column (Only show room name, no "Occupied" badge) -->
                        <td class="px-4 py-4">
                            <div class="text-sm font-medium text-gray-900 truncate" :title="item.room">
                                {{ item.room }}
                            </div>
                        </td>

                        <!-- BUILDING Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700 truncate" :title="item.building">{{ item.building }}</div>
                        </td>

                        <!-- COLLEGE Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700 truncate" :title="item.college">{{ item.college }}</div>
                        </td>

                        <!-- START DATE Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700 truncate" :title="item.startDate">{{ item.startDate }}</div>
                        </td>

                        <!-- TIME SLOT Column -->
                        <td class="px-4 py-4">
                            <div class="text-sm text-gray-700 truncate" :title="item.timeSlot">{{ item.timeSlot }}</div>
                        </td>

                        <!-- EVENT TYPE Column -->
                        <td class="px-4 py-4">
                            <span :class="[
                                'px-2 py-1 rounded-full text-xs font-medium truncate inline-block max-w-full',
                                item.eventType === 'Class' ? 'bg-blue-100 text-blue-800' :
                                item.eventType === 'Meeting' ? 'bg-green-100 text-green-800' :
                                item.eventType === 'Event' ? 'bg-purple-100 text-purple-800' :
                                'bg-yellow-100 text-yellow-800'
                            ]" :title="item.eventType">
                                {{ item.eventType }}
                            </span>
                        </td>

                        <!-- STATUS Column -->
                        <td class="px-4 py-4 status-cell">
                            <StatusBadge :status="item.status" />
                        </td>

                        <!-- ACTIONS Column -->
                        <td class="px-4 py-4">
                            <div class="flex items-center justify-center space-x-2" @click.stop>
                                <button
                                    v-if="isAdmin && !isFinalAppointmentStatus(item.status)"
                                    @click="openStatusModal(item.eventObject, $event)"
                                    title="Change Status"
                                    class="text-amber-600 hover:text-amber-800 transition-transform hover:scale-110"
                                >
                                    <FontAwesomeIcon :icon="icons.clipboardList" class="h-5 w-5" />
                                </button>
                                <span
                                    v-else-if="isAdmin && isFinalAppointmentStatus(item.status)"
                                    title="Status is closed and cannot be changed"
                                    class="text-gray-300 cursor-not-allowed inline-flex"
                                >
                                    <FontAwesomeIcon :icon="icons.clipboardList" class="h-5 w-5" />
                                </span>
                                <button
                                    @click="handleAction('view-details', item.eventObject, $event)"
                                    title="View Details"
                                    class="text-blue-500 hover:text-blue-700 transition-transform hover:scale-110"
                                >
                                    <FontAwesomeIcon :icon="icons.eye" class="h-5 w-5" />
                                </button>
                                <button
                                    @click="handleAction('edit-event', item.eventObject, $event)"
                                    title="Edit Event"
                                    class="text-green-600 hover:text-green-800 transition-transform hover:scale-110"
                                >
                                    <FontAwesomeIcon :icon="icons.edit" class="h-5 w-5" />
                                </button>
                                <button
                                    @click="handleAction('delete-event', item.eventObject, $event)"
                                    title="Delete Event"
                                    class="text-red-600 hover:text-red-800 transition-transform hover:scale-110"
                                >
                                    <FontAwesomeIcon :icon="icons.delete" class="h-5 w-5" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="processedEvents.length === 0">
                        <td :colspan="9" class="px-6 py-8 text-center text-gray-500">
                            <div class="flex flex-col items-center">
                                <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="text-lg font-medium text-gray-600">No appointments scheduled</p>
                                <p class="text-sm text-gray-500 mt-1">Click "New Appointment" to schedule one</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <div v-if="processedEvents.length > 0" class="flex flex-col sm:flex-row items-center justify-between p-4 border-t border-gray-200 bg-gray-50">
            <div class="text-sm text-gray-600 mb-2 sm:mb-0">
                Showing {{ showingRange.start }} to {{ showingRange.end }} of {{ showingRange.total }} entries
            </div>

            <div class="flex items-center space-x-2 mb-2 sm:mb-0">
                <!-- Previous Button -->
                <button
                    @click="prevPage"
                    :disabled="currentPage === 1"
                    :class="[
                        'flex items-center px-3 py-1.5 rounded border text-sm transition-colors',
                        currentPage === 1
                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-300'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400'
                    ]"
                >
                    <FontAwesomeIcon :icon="icons.chevronLeft" class="h-3 w-3 mr-1" />
                    Previous
                </button>

                <!-- Page Numbers -->
                <div class="flex items-center space-x-1">
                    <button
                        v-for="page in totalPages"
                        :key="page"
                        @click="goToPage(page)"
                        :class="[
                            'px-3 py-1.5 rounded border text-sm min-w-[36px] transition-colors',
                            currentPage === page
                                ? 'bg-[#7A0C23] text-white border-[#7A0C23]'
                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                        ]"
                    >
                        {{ page }}
                    </button>
                </div>

                <!-- Next Button -->
                <button
                    @click="nextPage"
                    :disabled="currentPage === totalPages"
                    :class="[
                        'flex items-center px-3 py-1.5 rounded border text-sm transition-colors',
                        currentPage === totalPages
                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed border-gray-300'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400'
                    ]"
                >
                    Next
                    <FontAwesomeIcon :icon="icons.chevronRight" class="h-3 w-3 ml-1" />
                </button>
            </div>

            <div class="text-sm text-gray-600">
                Page {{ currentPage }} of {{ totalPages }}
            </div>
        </div>

        <!-- Status change modal -->
        <div
            v-if="statusModal.visible"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50"
            @click.self="closeStatusModal"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 flex flex-col max-h-[min(90vh,36rem)] overflow-hidden" @click.stop>
                <div class="shrink-0 bg-[#7A0C23] px-5 py-4">
                    <h3 class="text-lg font-semibold text-white leading-tight">
                        Change Appointment Status
                    </h3>
                    <div class="mt-3 pt-3 border-t border-white/20 flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-white/60">
                                Appointment
                            </p>
                            <p class="text-sm font-medium text-white mt-0.5 truncate">
                                {{ statusModal.event?.title || 'Untitled appointment' }}
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <p class="text-[10px] font-medium uppercase tracking-wider text-white/60">
                                Current status
                            </p>
                            <StatusBadge
                                :status="currentAppointmentStatus"
                                size="md"
                                :show-dot="true"
                                variant="header"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 mb-3">Select new status</p>
                    <div class="space-y-2">
                    <button
                        v-for="option in statusOptions"
                        :key="option.value"
                        type="button"
                        class="status-option-btn w-full text-left rounded-lg border p-3 transition text-gray-900"
                        :class="getStatusOptionPanelClass(option)"
                        :aria-disabled="isBlockedStatusOption(option.value)"
                        @click="selectStatusOption(option.value)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <StatusBadge
                                :status="option.value"
                                size="md"
                                :show-dot="true"
                                :muted="isCurrentStatusOption(option.value)"
                                variant="default"
                            />
                        </div>
                        <p class="text-xs text-gray-600 mt-2">{{ option.description }}</p>
                    </button>
                    </div>
                </div>

                <div class="shrink-0 px-5 py-3 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 transition disabled:opacity-50"
                        :disabled="statusModal.loading"
                        @click="closeStatusModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg font-medium text-white bg-[#7A0C23] hover:opacity-90 transition disabled:opacity-50"
                        :disabled="statusModal.loading || isBlockedStatusOption(statusModal.selectedStatus)"
                        @click="openStatusConfirm"
                    >
                        {{ statusModal.loading ? 'Saving...' : 'Save Status' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Status change confirmation -->
        <div
            v-if="statusConfirm.visible"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50"
            @click.self="closeStatusConfirm"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>
                <div class="bg-[#7A0C23] px-6 py-4">
                    <h3 class="text-xl font-semibold text-white">Confirm Status Change</h3>
                </div>

                <div class="p-6">
                    <p class="text-center text-gray-700 mb-4">
                        Are you sure you want to change the status of
                        <strong class="text-[#7A0C23]">{{ statusModal.event?.title || 'this appointment' }}</strong>
                        from
                        <strong :class="getAppointmentStatusTextClass(currentAppointmentStatus)">{{ currentStatusLabel }}</strong>
                        to
                        <strong :class="getAppointmentStatusTextClass(statusModal.selectedStatus)">{{ selectedStatusLabel }}</strong>?
                    </p>

                    <div class="flex justify-end gap-3 pt-2 border-t">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 transition disabled:opacity-50"
                            :disabled="statusModal.loading"
                            @click="closeStatusConfirm"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg font-medium text-white bg-[#7A0C23] hover:opacity-90 transition disabled:opacity-50"
                            :disabled="statusModal.loading"
                            @click="confirmStatusChange"
                        >
                            {{ statusModal.loading ? 'Saving...' : 'Yes, Change Status' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Fixed table layout */
table {
    table-layout: fixed;
}

/* Ensure columns don't resize */
th, td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Status & actions: no ellipsis (avoids ".." after badges/buttons) */
td:nth-child(8),
th:nth-child(8),
td:nth-child(9),
th:nth-child(9) {
    overflow: visible;
    text-overflow: clip;
    white-space: normal;
}

/* Allow truncation with tooltip */
.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Fixed column widths */
td:first-child, th:first-child { width: 20%; }
td:nth-child(2), th:nth-child(2) { width: 12%; }
td:nth-child(3), th:nth-child(3) { width: 12%; }
td:nth-child(4), th:nth-child(4) { width: 12%; }
td:nth-child(5), th:nth-child(5) { width: 10%; }
td:nth-child(6), th:nth-child(6) { width: 10%; }
td:nth-child(7), th:nth-child(7) { width: 9%; }
td:nth-child(8), th:nth-child(8) { width: 11%; }
td:nth-child(9), th:nth-child(9) { width: 12%; }

/* Action buttons container */
.flex.items-center.justify-center.space-x-3 {
    min-width: 120px;
}

/* Hover effects for buttons */
button.transition-transform:hover:not(:disabled) {
    transform: scale(1.1);
}

/* Prevent text selection on buttons */
button {
    user-select: none;
}

/* Center align text in action column */
td:last-child {
    text-align: center;
}

/* Ensure consistent icon sizing */
.h-5.w-5 {
    height: 1.25rem;
    width: 1.25rem;
}

/* Better spacing for action buttons */
.space-x-3 > * + * {
    margin-left: 0.75rem;
}

/* Row hover effect */
tr:hover {
    background-color: #f0f9ff;
}

/* Pagination button styles */
button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .min-w-full {
        min-width: 800px; /* Allow horizontal scroll on mobile */
    }

    .px-4 {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}
</style>
