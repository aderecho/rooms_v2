<script setup>
import { ref, computed, watchEffect, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

// Core Components
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import MessageFunction from '@/Components/MessageFunction.vue';

// ScheduleModal Components
import AppointmentModal from '@/Components/ScheduleModal/AppointmentModal.vue';
import CalendarView from '@/Components/ScheduleModal/CalendarView.vue';
import TableComponent from '@/Components/ScheduleModal/TableComponent.vue';
import MonthGridView from '@/Components/ScheduleModal/MonthGridView.vue';
import TimeGridView from '@/Components/ScheduleModal/TimeGridView.vue';
import EventViewerModal from '@/Components/ScheduleModal/EventViewerModal.vue';
import {
    demoRoomEquipmentsMap,
    demoRoomEquipmentQuantitiesMap,
    demoRoomEquipmentDetailsMap,
    GLOBAL_DEMO_EQUIPMENT_QUANTITIES,
} from '@/data/demoRoomEquipment.js';
import { getAppointmentStatusLabel } from '@/utils/scheduleStatus';

const props = defineProps({
    schedules: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    roomEquipmentQuantities: { type: Object, default: () => ({}) },
    globalEquipmentQuantities: { type: Object, default: () => ({}) },
    faculty: { type: Array, default: () => [] },
    requesters: { type: Array, default: () => [] },
    currentRequester: { type: String, default: '' },
    currentUserRole: { type: String, default: '' },
    terms: { type: Array, default: () => [] },
});

// --- UTILITY FUNCTIONS ---
const createDate = (dateStr, timeStr) => {
    if (timeStr) {
        const [h, m] = timeStr.split(':').map(Number);
        const [y, M, d] = dateStr.split('-').map(Number);
        return new Date(y, M - 1, d, h, m);
    }
    const [y, M, d] = dateStr.split('-').map(Number);
    return new Date(y, M - 1, d);
};

const eventTypeFromDb = (type) => {
    const map = { class: 'Class', meeting: 'Meeting', event: 'Event', other: 'Other type of activity' };
    return map[type] || 'Other type of activity';
};

const fullName = (user) => {
    if (!user) return '';
    const parts = [user.first_name, user.middle_name, user.last_name].filter(Boolean);
    return parts.length ? parts.join(' ') : (user.username || '');
};

const parseRoomEquipments = (equipments) => {
    if (!equipments) return [];
    if (Array.isArray(equipments)) return equipments;

    if (typeof equipments === 'string') {
        const trimmed = equipments.trim();
        if (!trimmed) return [];

        try {
            const parsed = JSON.parse(trimmed);
            if (Array.isArray(parsed)) return parsed;
        } catch (_) {
            // Fall back to splitting by common separators.
        }

        return trimmed
            .split(/[\n,;]+/)
            .map((item) => item.trim())
            .filter(Boolean);
    }

    return [];
};

const dbScheduleToEvent = (s) => {
    const datePart = (s.date || '').slice(0, 10);
    const start = createDate(datePart, (s.start_time || '00:00').slice(0, 5));
    const end = createDate(datePart, (s.end_time || '00:00').slice(0, 5));
    const roomDefaultEquipment = parseRoomEquipments(s.room?.equipments);
    const scheduleEquipment = Array.isArray(s.equipment_needed) ? s.equipment_needed : [];
    return {
        id: s.id,
        dbId: s.id,
        title: s.event_title || 'Untitled',
        list: eventTypeFromDb(s.event_type),
        allDay: false,
        start,
        end,
        extendedProps: {
            room: s.room?.room_name || s.room?.room_code || 'N/A',
            roomCode: s.room?.room_code || '',
            building: s.room?.building?.building_name || 'N/A',
            college: s.room?.college?.college_name || s.organizer || 'N/A',
            subject: s.course_name || s.event_title || '',
            courseCode: s.course_code || '',
            type: eventTypeFromDb(s.event_type),
            requester: s.requester_name || fullName(s.requester) || 'N/A',
            description: s.description || '',
            agenda: s.agenda || '',
            organizer: s.organizer || '',
            numberOfStudents: s.number_of_participants,
            faculty: s.faculty_name || fullName(s.faculty) || '',
            section: s.section || '',
            equipment: scheduleEquipment.length > 0 ? scheduleEquipment : roomDefaultEquipment,
            additional: Array.isArray(s.additional_requirements) ? s.additional_requirements : [],
            status: s.status || 'pending',
            isRecurring: !!s.is_recurring,
        },
    };
};

const loadEventsFromProps = () => {
    if (!Array.isArray(props.schedules)) return [];
    return props.schedules.map(dbScheduleToEvent);
};

const dateToIsoDateString = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const formatTimeDisplay = (date) => {
    const d = new Date(date);
    return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
};

const formatDateDisplay = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const extractExtendedProps = (data) => ({
    room: data.room || 'N/A',
    building: data.organization || 'N/A',
    college: data.deptOffice || 'N/A',
    subject: data.subject || data.title || data.agenda || data.name || 'Untitled',
    type: data.type || data.list || 'Event',
    requester: data.requester || 'N/A',
    description: data.description || '',
    agenda: data.agenda || '',
    numberOfStudents: data.numberOfStudents || null,
    faculty: data.faculty || '',
    section: data.section || ''
});

const checkTimeSlotOccupancy = (room, startTime, endTime, events, excludeEventId = null) => {
    const start = new Date(startTime);
    const end = new Date(endTime);

    return events.some(event => {
        if (excludeEventId && event.id === excludeEventId) return false;
        if (event.extendedProps?.room !== room) return false;

        const eventStart = new Date(event.start);
        const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
        return (start < eventEnd && end > eventStart);
    });
};

const getAvailableSlotsForRoom = (room, date, events, durationHours = 1) => {
    const allSlots = [];
    const dayStart = new Date(date);
    dayStart.setHours(6, 0, 0, 0);

    const dayEnd = new Date(date);
    dayEnd.setHours(22, 0, 0, 0);

    const roomEvents = events.filter(event => {
        const eventDate = dateToIsoDateString(new Date(event.start));
        const targetDate = dateToIsoDateString(new Date(date));
        return event.extendedProps?.room === room && eventDate === targetDate;
    }).sort((a, b) => new Date(a.start) - new Date(b.start));

    let currentTime = new Date(dayStart);

    while (currentTime < dayEnd) {
        const slotEnd = new Date(currentTime);
        slotEnd.setHours(currentTime.getHours() + durationHours);

        const isOccupied = roomEvents.some(event => {
            const eventStart = new Date(event.start);
            const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
            return (currentTime < eventEnd && slotEnd > eventStart);
        });

        if (!isOccupied && slotEnd <= dayEnd) {
            allSlots.push({
                start: new Date(currentTime),
                end: new Date(slotEnd),
                display: `${formatTimeDisplay(currentTime)} - ${formatTimeDisplay(slotEnd)}`
            });
        }

        currentTime.setMinutes(currentTime.getMinutes() + 30);
    }

    return allSlots;
};

// --- CORE STATE ---
const events = ref(loadEventsFromProps());
const availableRooms = computed(() => {
    const list = (props.rooms || [])
        .map((r) => r.room_name || r.room_code)
        .filter(Boolean);
    return list.length > 0 ? list : ['UG 114', 'AVR 201', 'Lab 305', 'Main Conference Room'];
});

const roomEquipmentsMap = computed(() => {
    const map = {};
    (props.rooms || []).forEach((room) => {
        const parsed = parseRoomEquipments(room?.equipments);
        if (!parsed.length) return;

        if (room?.room_name) map[room.room_name] = parsed;
        if (room?.room_code) map[room.room_code] = parsed;
        if (room?.room_name) map[String(room.room_name).toLowerCase()] = parsed;
        if (room?.room_code) map[String(room.room_code).toLowerCase()] = parsed;
    });

    return Object.keys(map).length > 0 ? map : demoRoomEquipmentsMap;
});

const roomEquipmentQuantitiesForModal = computed(() => {
    const fromApi = props.roomEquipmentQuantities || {};
    return Object.keys(fromApi).length > 0 ? fromApi : demoRoomEquipmentQuantitiesMap;
});

const globalEquipmentQuantitiesForModal = computed(() => {
    const fromApi = props.globalEquipmentQuantities || {};
    return Object.keys(fromApi).length > 0 ? fromApi : GLOBAL_DEMO_EQUIPMENT_QUANTITIES;
});

const roomEquipmentDetailsForModal = computed(() => {
    const map = {};
    (props.rooms || []).forEach((room) => {
        const details = room?.equipment_details;
        if (!Array.isArray(details) || !details.length) return;

        [room.room_name, room.room_code]
            .filter(Boolean)
            .forEach((key) => {
                map[key] = details;
                map[String(key).toLowerCase()] = details;
            });
    });

    return Object.keys(map).length > 0 ? map : demoRoomEquipmentDetailsMap;
});

// Layout State
const sidebarOpen = ref(true);
const currentView = ref('table');
const toggleSidebar = () => (sidebarOpen.value = !sidebarOpen.value);

// Calendar State
const currentCalendarDate = ref(new Date());
const currentCalendarMode = ref('list');
const nextEventId = computed(() =>
    (events.value.length > 0 ? Math.max(...events.value.map(e => e.id)) : 0) + 1
);

// Modal States
const modalState = ref({
    appointment: { visible: false, data: null, editing: null },
    eventViewer: { visible: false, event: null }
});

// Toast State
const toastState = ref({
    create: false,
    edit: false,
    delete: { visible: false, name: '' }
});
const conflictNotice = ref({
    visible: false,
    room: '',
    date: '',
    requestedRange: '',
    conflictTitle: '',
    conflictRange: ''
});

// --- UTILITY FUNCTIONS ---
const triggerToast = (type, name = '') => {
    toastState.value = { create: false, edit: false, delete: { visible: false, name: '' } };

    if (type === 'create') toastState.value.create = true;
    if (type === 'edit') toastState.value.edit = true;
    if (type === 'delete') toastState.value.delete = { visible: true, name };

    setTimeout(() => {
        toastState.value = { create: false, edit: false, delete: { visible: false, name: '' } };
    }, 3000);
};

const isAdminAccount = computed(() => String(props.currentUserRole || '').toLowerCase() === 'admin');

const handleStatusUpdate = async ({ event, status, onComplete, onError }) => {
    if (!isAdminAccount.value || !event?.dbId) {
        onError?.();
        return;
    }

    try {
        const res = await axios.patch(`/Schedule/${event.dbId}/status`, { status });
        const updated = dbScheduleToEvent(res.data.schedule);
        const idx = events.value.findIndex((item) => item.id === event.id);
        if (idx !== -1) {
            events.value[idx] = updated;
        }
        refreshSchedulesFromServer();
        window.dispatchEvent(new CustomEvent('appointment-notifications:refresh'));
        window.dispatchEvent(new CustomEvent('appointment-status:success', {
            detail: { message: `Appointment status updated to ${getAppointmentStatusLabel(status)}.` },
        }));
        onComplete?.();
    } catch (err) {
        console.error('Failed to update appointment status:', err);
        alert(err.response?.data?.message || 'Failed to update status.');
        onError?.();
    }
};

const refreshSchedulesFromServer = () => {
    router.reload({
        only: ['schedules'],
        preserveScroll: true,
        preserveState: true,
    });
};

let schedulePollTimer = null;

onMounted(() => {
    schedulePollTimer = window.setInterval(refreshSchedulesFromServer, 15000);
    window.addEventListener('appointment-notifications:refresh', refreshSchedulesFromServer);
    window.addEventListener('appointment-notification:open', handleNotificationOpen);
});

onUnmounted(() => {
    if (schedulePollTimer) {
        window.clearInterval(schedulePollTimer);
    }
    window.removeEventListener('appointment-notifications:refresh', refreshSchedulesFromServer);
    window.removeEventListener('appointment-notification:open', handleNotificationOpen);
});

const closeConflictNoticeModal = () => {
    conflictNotice.value = {
        visible: false,
        room: '',
        date: '',
        requestedRange: '',
        conflictTitle: '',
        conflictRange: ''
    };
};

// --- MODAL CONTROLLERS ---
const openAppointmentModal = (data = null, editing = null) => {
    modalState.value.appointment = {
        visible: true,
        data: data || {
            selectedDate: new Date().toISOString().slice(0, 10),
            initialRoom: '',
            initialHour: 9,
            initialMinute: 0
        },
        editing
    };
};

const openEventViewer = (event) => {
    modalState.value.eventViewer = { visible: true, event };
};

const closeModal = (modalName) => {
    modalState.value[modalName] = {
        visible: false,
        data: null,
        event: null,
        events: [],
        date: null,
        callback: null
    };
};

// --- EVENT HANDLERS ---
const pad2 = (n) => String(n).padStart(2, '0');
const fmtLocalDate = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
const fmtLocalTime = (d) => `${pad2(d.getHours())}:${pad2(d.getMinutes())}:00`;
const fmtLocalDateTime = (d) => `${fmtLocalDate(d)} ${fmtLocalTime(d)}`;

const buildBackendPayload = (data) => {
    const s = new Date(data.start);
    const e = new Date(data.end);
    return {
        room: data.room,
        type: data.type,
        title: data.title,
        date: fmtLocalDate(s),
        startTime: fmtLocalTime(s),
        endTime: fmtLocalTime(e),
        start: fmtLocalDateTime(s),
        end: fmtLocalDateTime(e),
        numberParticipants: data.numberParticipants ?? null,
        numberOfStudents: data.numberOfStudents ?? null,
        deptOffice: data.deptOffice ?? '',
        organization: data.organization ?? '',
        description: data.description ?? '',
        agenda: data.agenda ?? '',
        requester: data.requester ?? '',
        subject: data.subject ?? '',
        section: data.section ?? '',
        faculty: data.faculty ?? '',
        organizer: data.organizer ?? '',
        name: data.name ?? '',
        tablesChairs: !!data.tablesChairs,
        airConditioner: !!data.airConditioner,
        whiteboard: !!data.whiteboard,
        equipmentNeeded: Array.isArray(data.selectedEquipments) ? data.selectedEquipments : [],
        additionalInstructions: data.additionalInstructions ?? '',
        recurring: !!data.recurring,
    };
};

const formatTimeRange = (start, end) => {
    const fmt = (d) => new Date(d).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    return `${fmt(start)} – ${fmt(end)}`;
};

const showConflictNotice = (room, start, end, conflictEvent) => {
    conflictNotice.value = {
        visible: true,
        room,
        date: formatDateDisplay(start),
        requestedRange: formatTimeRange(start, end),
        conflictTitle: conflictEvent?.title || conflictEvent?.extendedProps?.subject || 'Existing appointment',
        conflictRange: formatTimeRange(conflictEvent.start, conflictEvent.end),
    };
};

const findRoomConflict = (room, startTime, endTime, eventsList, excludeEventId = null) => {
    const start = new Date(startTime);
    const end = new Date(endTime);
    return eventsList.find(event => {
        if (excludeEventId && event.id === excludeEventId) return false;
        if (event.extendedProps?.room !== room) return false;
        const eventStart = new Date(event.start);
        const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
        return start < eventEnd && end > eventStart;
    }) || null;
};

const handleAppointmentSuccess = async (data) => {
    const editingEvent = modalState.value.appointment.editing;
    const isEdit = !!editingEvent;
    const eventIdForConflictCheck = isEdit ? editingEvent.id : null;

    const conflictEvent = findRoomConflict(data.room, data.start, data.end, events.value, eventIdForConflictCheck);
    if (conflictEvent) {
        showConflictNotice(data.room, data.start, data.end, conflictEvent);
        openAppointmentModal({
            selectedDate: dateToIsoDateString(data.start),
            initialRoom: data.room,
            initialHour: data.start.getHours(),
            initialMinute: data.start.getMinutes(),
        }, isEdit ? editingEvent : null);
        return;
    }

    const payload = buildBackendPayload(data);

    try {
        let savedSchedule;
        if (isEdit && editingEvent.dbId) {
            const res = await axios.put(`/Schedule/${editingEvent.dbId}`, payload);
            savedSchedule = res.data.schedule;
        } else {
            const res = await axios.post('/Schedule', payload);
            savedSchedule = res.data.schedule;
        }

        const newEvent = dbScheduleToEvent(savedSchedule);

        if (isEdit) {
            const index = events.value.findIndex((e) => e.id === editingEvent.id);
            if (index !== -1) events.value[index] = newEvent;
            triggerToast('edit');
        } else {
            events.value.push(newEvent);
            triggerToast('create');
        }

        closeModal('appointment');

        window.dispatchEvent(new CustomEvent('appointment-notifications:refresh'));

        if (currentView.value === 'calendar') {
            selectEventInCalendar(newEvent);
        }
    } catch (err) {
        console.error('Failed to save appointment:', err);
        const msg = err.response?.data?.message
            || (err.response?.data?.errors && Object.values(err.response.data.errors).flat().join('\n'))
            || 'Failed to save appointment. Please check the form and try again.';
        alert(msg);
    }
};

const handleEditEvent = (event) => {
    closeModal('eventViewer');
    openAppointmentModal(
        {
            selectedDate: dateToIsoDateString(event.start),
            initialRoom: event.extendedProps?.room || '',
            initialHour: event.allDay ? null : event.start.getHours(),
            initialMinute: event.allDay ? null : event.start.getMinutes()
        },
        event
    );
};

const deleteConfirm = ref({
    visible: false,
    event: null,
    loading: false,
    error: '',
});

const resetDeleteConfirmModal = () => {
    deleteConfirm.value = { visible: false, event: null, loading: false, error: '' };
};

const closeDeleteConfirmModal = () => {
    if (deleteConfirm.value.loading) return;
    resetDeleteConfirmModal();
};

const handleDeleteEvent = (eventObject) => {
    deleteConfirm.value = {
        visible: true,
        event: eventObject,
        loading: false,
        error: '',
    };
};

const confirmDeleteEvent = async () => {
    const eventObject = deleteConfirm.value.event;
    if (!eventObject) return;

    deleteConfirm.value.loading = true;
    deleteConfirm.value.error = '';

    try {
        if (eventObject.dbId) {
            await axios.delete(`/Schedule/${eventObject.dbId}`);
        }
        events.value = events.value.filter((e) => e.id !== eventObject.id);
        triggerToast('delete', eventObject?.title || 'Appointment');

        if (
            modalState.value.eventViewer.visible
            && modalState.value.eventViewer.event?.id === eventObject.id
        ) {
            closeModal('eventViewer');
        }

        resetDeleteConfirmModal();
    } catch (err) {
        console.error('Failed to delete appointment:', err);
        deleteConfirm.value.loading = false;
        deleteConfirm.value.error = 'Failed to delete appointment. Please try again.';
    }
};

const handleDateClick = (date, hour = null, minute = null, position = null) => {
    openAppointmentModal({
        selectedDate: dateToIsoDateString(new Date(date)),
        initialRoom: '',
        initialHour: hour ?? 9,
        initialMinute: minute ?? 0,
    });
};

const handleAddAppointment = () => {
    openAppointmentModal({
        selectedDate: dateToIsoDateString(new Date()),
        initialRoom: '',
        initialHour: 9,
        initialMinute: 0,
    });
};

const selectEventInCalendar = (event) => {
    currentView.value = 'calendar';
    currentCalendarDate.value = event.start;
    currentCalendarMode.value = 'day';
    openEventViewer(event);
};

const openAppointmentById = (scheduleId) => {
    const id = Number(scheduleId);
    if (!id) return false;

    const event = events.value.find((item) => item.dbId === id || item.id === id);
    if (!event) return false;

    currentView.value = 'table';
    openEventViewer(event);

    const url = new URL(window.location.href);
    if (url.searchParams.has('appointment')) {
        url.searchParams.delete('appointment');
        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
    }

    return true;
};

const handleAppointmentDeepLink = () => {
    const fromQuery = new URLSearchParams(window.location.search).get('appointment');
    if (fromQuery) {
        openAppointmentById(fromQuery);
    }
};

const handleNotificationOpen = (event) => {
    openAppointmentById(event?.detail?.scheduleId);
};

// Reload events when fresh data arrives from the backend (e.g., after navigation)
watchEffect(() => {
    events.value = loadEventsFromProps();
    handleAppointmentDeepLink();
});
</script>

<template>
    <div class="bg-gray-200 font-sans min-h-screen">
        <MessageFunction
            :show-create-success="toastState.create"
            :show-edit-success="toastState.edit"
            :show-delete-success="toastState.delete.visible"
            :delete-message="toastState.delete.name
                ? `${toastState.delete.name} has been deleted.`
                : 'Successfully deleted!'"
        />

        <Navbar @toggleSidebar="toggleSidebar" class="fixed top-0 left-0 right-0 z-30" />

        <div class="pt-14 min-h-screen transition-all duration-300">
            <Sidebar :sidebarOpen="sidebarOpen" :class="['fixed top-14 left-0 h-[calc(100vh-3.5rem)] z-20 transition-all duration-300 w-64',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full']" />

            <main :class="['p-6 overflow-y-auto transition-all duration-300',
                sidebarOpen ? 'ml-64' : 'ml-0']">
                <!-- Page Header -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="text-2xl font-semibold text-[#7A0C23]">Schedule</div>
                    <div class="text-sm text-gray-500">UPCEBU > SCHEDULES</div>
                </div>

                <!-- View Toggles -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex justify-start space-x-2">
                        <button @click="currentView = 'table'" :class="['px-4 py-2 rounded-lg font-medium transition flex items-center border-2',
                            currentView === 'table'
                                ? 'bg-[#7A0C23] text-white shadow-lg border-[#7A0C23]'
                                : 'bg-white text-[#7A0C23] border-[#7A0C23] hover:bg-red-50']">
                            Appointment List
                        </button>
                        <button @click="currentView = 'calendar'" :class="['px-4 py-2 rounded-lg font-medium transition flex items-center border-2',
                            currentView === 'calendar'
                                ? 'bg-[#7A0C23] text-white shadow-lg border-[#7A0C23]'
                                : 'bg-white text-[#7A0C23] border-[#7A0C23] hover:bg-red-50']">
                            Calendar View
                        </button>
                    </div>

                    <!-- Quick Add Buttons -->

                </div>

                <!-- Main Content -->
                <TableComponent
                    v-if="currentView === 'table'"
                    :events="events"
                    :is-admin="isAdminAccount"
                    @view-details="openEventViewer"
                    @edit-event="handleEditEvent"
                    @delete-event="handleDeleteEvent"
                    @update-status="handleStatusUpdate"
                    @row-clicked="openEventViewer"
                />

                <CalendarView v-else :data="events" :initial-date="currentCalendarDate"
                    :initial-mode="currentCalendarMode" :ListViewComponent="TableComponent"
                    :MonthGridViewComponent="MonthGridView" :TimeGridViewComponent="TimeGridView"
                    @update:date="(date) => currentCalendarDate = date"
                    @update:mode="(mode) => currentCalendarMode = mode" @dateClicked="handleDateClick"
                    @selectEvent="openEventViewer" @editEvent="handleEditEvent" @deleteEvent="handleDeleteEvent"
                    @addAppointment="handleAddAppointment" />
            </main>
        </div>

        <!-- Modals -->
        <AppointmentModal :is-visible="modalState.appointment.visible"
            :selected-date="modalState.appointment.data?.selectedDate"
            :initial-room="modalState.appointment.data?.initialRoom"
            :rooms="availableRooms"
            :current-requester="props.currentRequester"
            :room-equipments-map="roomEquipmentsMap"
            :room-equipment-quantities-map="roomEquipmentQuantitiesForModal"
            :global-equipment-quantities="globalEquipmentQuantitiesForModal"
            :room-equipment-details-map="roomEquipmentDetailsForModal"
            :initial-hour="modalState.appointment.data?.initialHour"
            :initial-minute="modalState.appointment.data?.initialMinute" :editing-event="modalState.appointment.editing"
            @close="() => closeModal('appointment')" @success="handleAppointmentSuccess" />

        <EventViewerModal :is-visible="modalState.eventViewer.visible" :event="modalState.eventViewer.event"
            @close="() => closeModal('eventViewer')" @edit="handleEditEvent" @delete="handleDeleteEvent" />

        <div
            v-if="conflictNotice.visible"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeConflictNoticeModal"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden" @click.stop>
                <div class="bg-[#7A0C23] px-6 py-4">
                    <h3 class="text-xl font-semibold text-white">Reservation Conflict</h3>
                </div>
                <div class="p-6">
                    <p class="text-gray-800 mb-4">
                        This room is already occupied for the selected time.
                    </p>

                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 space-y-2">
                        <p class="text-sm"><span class="font-semibold text-red-800">Room:</span> {{ conflictNotice.room }}</p>
                        <p class="text-sm"><span class="font-semibold text-red-800">Date:</span> {{ conflictNotice.date }}</p>
                        <p class="text-sm"><span class="font-semibold text-red-800">Requested:</span> {{ conflictNotice.requestedRange }}</p>
                        <p class="text-sm"><span class="font-semibold text-red-800">Occupied by:</span> {{ conflictNotice.conflictTitle }}</p>
                        <p class="text-sm"><span class="font-semibold text-red-800">Occupied time:</span> {{ conflictNotice.conflictRange }}</p>
                    </div>

                    <p class="text-sm text-gray-600 mt-4">
                        Please choose a different room or adjust the booking time.
                    </p>

                    <div class="flex justify-end mt-6">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg font-medium text-white bg-[#7A0C23] hover:opacity-90 transition"
                            @click="closeConflictNoticeModal"
                        >
                            Okay
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <div
            v-if="deleteConfirm.visible"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-50"
            @click.self="closeDeleteConfirmModal"
        >
            <div
                class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden"
                @click.stop
            >
                <div class="bg-[#7A0C23] px-6 py-4">
                    <h3 class="text-xl font-semibold text-white">Delete Appointment</h3>
                </div>

                <div class="p-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>

                    <p class="text-center text-gray-700 mb-4">
                        Are you sure you want to delete
                        <strong class="text-[#7A0C23]">{{ deleteConfirm.event?.title }}</strong>?
                    </p>

                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                        <p class="text-sm text-yellow-700">
                            This action cannot be undone. The appointment will be permanently removed.
                        </p>
                    </div>

                    <p v-if="deleteConfirm.error" class="text-sm text-red-600 text-center mb-4">
                        {{ deleteConfirm.error }}
                    </p>

                    <div class="flex justify-end gap-3 pt-2 border-t">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 transition disabled:opacity-50"
                            :disabled="deleteConfirm.loading"
                            @click="closeDeleteConfirmModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 rounded-lg font-medium text-white bg-red-600 hover:bg-red-700 transition disabled:opacity-50 flex items-center"
                            :disabled="deleteConfirm.loading"
                            @click="confirmDeleteEvent"
                        >
                            <span
                                v-if="deleteConfirm.loading"
                                class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"
                            />
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
