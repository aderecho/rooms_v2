<script setup>
import { reactive, watch, defineProps, defineEmits, ref, computed } from 'vue';
import { formatEquipmentAvailability } from '@/utils/equipmentAvailability.js';
import ClassForm from './ClassForm.vue';
import MeetingForm from './MeetingForm.vue';
import EventForm from './EventForm.vue';
import OtherActivityForm from './OtherActivityForm.vue';

const props = defineProps({
    isVisible: Boolean,
    selectedDate: { type: String, default: () => new Date().toISOString().slice(0, 10) },
    initialHour: { type: [Number, null], default: 9 },
    initialMinute: { type: [Number, null], default: 0 },
    editingEvent: { type: Object, default: null },
    initialRoom: { type: String, default: '' },
    rooms: { type: Array, default: () => [] },
    currentRequester: { type: String, default: '' },
    roomEquipmentsMap: { type: Object, default: () => ({}) },
    roomEquipmentQuantitiesMap: { type: Object, default: () => ({}) },
    globalEquipmentQuantities: { type: Object, default: () => ({}) },
    roomEquipmentDetailsMap: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'success']);

// --- Reactive form object ---
const appointmentForm = reactive({
    // Basic Info
    room: props.initialRoom || '',
    type: 'Meeting',
    date: props.selectedDate,
    startHour: props.initialHour !== null ? props.initialHour : 9,
    startMinute: props.initialMinute !== null ? props.initialMinute : 0,
    startAmPm: (props.initialHour !== null && props.initialHour >= 12) ? 'PM' : 'AM',
    endHour: props.initialHour !== null ? props.initialHour + 1 : 10,
    endMinute: props.initialMinute !== null ? props.initialMinute : 0,
    endAmPm: (props.initialHour !== null && props.initialHour + 1 >= 12) ? 'PM' : 'AM',
    isHoliday: false,
    recurring: false,
    numberParticipants: null,
    deptOffice: '',
    organization: '',
    description: '',

    // Equipment
    tablesChairs: false,
    airConditioner: false,
    whiteboard: false,
    selectedEquipments: [],

    // Type-specific fields
    agenda: '', // Meeting
    title: '', // Event
    organizer: '', // Event
    name: '', // Other Activity
    requester: '', // Meeting, Event, Other Activity
    subject: '', // Class
    section: '', // Class
    faculty: '', // Class
    numberOfStudents: null, // Class

    // Additional
    additionalInstructions: '',
    driveLink: ''
});

// Time conflict warning
const timeConflictWarning = ref('');
const showRoomDropdown = ref(false);
const roomQuery = ref('');
const roomOptions = computed(() => (
    Array.isArray(props.rooms) && props.rooms.length > 0 ? props.rooms : []
));

const filteredRoomOptions = computed(() => {
    const query = (roomQuery.value || '').toLowerCase().trim();
    if (!query) return roomOptions.value;
    return roomOptions.value.filter((room) => room.toLowerCase().includes(query));
});

const toggleRoomDropdown = () => {
    if (!showRoomDropdown.value) {
        roomQuery.value = '';
    }
    showRoomDropdown.value = !showRoomDropdown.value;
};

const openRoomDropdown = () => {
    roomQuery.value = appointmentForm.room || '';
    showRoomDropdown.value = true;
};

const selectRoom = (room) => {
    appointmentForm.room = room;
    roomQuery.value = room;
    showRoomDropdown.value = false;
    if (!props.editingEvent) {
        applyRoomEquipmentDefaults(room);
    }
};

const roomEquipmentOptions = ref([]);

const getRoomEquipmentDefaults = (roomName) => {
    const raw = props.roomEquipmentsMap?.[roomName]
        || props.roomEquipmentsMap?.[String(roomName || '').toLowerCase()]
        || [];
    return Array.isArray(raw)
        ? raw.map((item) => String(item).trim()).filter(Boolean)
        : [];
};

const getEquipmentQuantity = (roomName, equipmentName) => {
    const key = String(equipmentName || '').toLowerCase();
    const roomKey = roomName || appointmentForm.room;
    const roomQuantities = props.roomEquipmentQuantitiesMap?.[roomKey]
        || props.roomEquipmentQuantitiesMap?.[String(roomKey || '').toLowerCase()]
        || {};

    if (roomQuantities[key] !== undefined && roomQuantities[key] !== null) {
        return Number(roomQuantities[key]);
    }

    const globalValue = props.globalEquipmentQuantities?.[key];
    if (globalValue !== undefined && globalValue !== null) {
        return Number(globalValue);
    }

    return null;
};

const syncEquipmentFlags = () => {
    const selectedLower = appointmentForm.selectedEquipments.map((item) => String(item).toLowerCase());
    appointmentForm.tablesChairs = selectedLower.some((item) => item.includes('table') || item.includes('chair'));
    appointmentForm.airConditioner = selectedLower.some((item) => item.includes('air'));
    appointmentForm.whiteboard = selectedLower.some((item) => item.includes('whiteboard') || item.includes('white board'));
};

const refreshEquipmentOptionQuantities = () => {
    if (!roomEquipmentOptions.value.length) return;
    roomEquipmentOptions.value = buildEquipmentOptions(
        roomEquipmentOptions.value.map((item) => item.name),
        appointmentForm.room
    );
};

const buildEquipmentOptions = (names, roomName) => {
    const seen = new Set();
    const options = [];

    for (const raw of names) {
        const name = String(raw).trim();
        if (!name) continue;

        const dedupeKey = name.toLowerCase();
        if (seen.has(dedupeKey)) continue;
        seen.add(dedupeKey);

        options.push({
            name,
            quantity: getEquipmentQuantity(roomName, name),
        });
    }

    return options;
};

const clearRoomEquipment = () => {
    roomEquipmentOptions.value = [];
    appointmentForm.selectedEquipments = [];
    appointmentForm.tablesChairs = false;
    appointmentForm.airConditioner = false;
    appointmentForm.whiteboard = false;
};

const resolveRoomEquipmentDetails = (roomName) => {
    const trimmed = String(roomName || '').trim();
    if (!trimmed) return [];

    const fromMap = props.roomEquipmentDetailsMap?.[trimmed]
        || props.roomEquipmentDetailsMap?.[trimmed.toLowerCase()];

    if (Array.isArray(fromMap) && fromMap.length) {
        return fromMap.map((item) => ({
            name: String(item.name ?? item).trim(),
            quantity: Number(item.quantity ?? getEquipmentQuantity(trimmed, item.name ?? item)),
        }));
    }

    const equipmentNames = getRoomEquipmentDefaults(trimmed);
    return buildEquipmentOptions(equipmentNames, trimmed);
};

const applyRoomEquipmentDefaults = (roomName) => {
    const trimmed = String(roomName || '').trim();
    if (!trimmed) {
        clearRoomEquipment();
        return;
    }

    const details = resolveRoomEquipmentDetails(trimmed);
    if (!details.length) {
        clearRoomEquipment();
        return;
    }

    roomEquipmentOptions.value = details;
    appointmentForm.selectedEquipments = details.map((item) => item.name);
    syncEquipmentFlags();
};

// --- Computed properties ---
const convertTo24Hour = (hour, minute, ampm) => {
    let h = hour;
    if (ampm === 'PM' && h < 12) h += 12;
    if (ampm === 'AM' && h === 12) h = 0;
    return { hour: h, minute };
};

const startDate = computed(() => {
    const datePart = new Date(appointmentForm.date);
    const start24 = convertTo24Hour(appointmentForm.startHour, appointmentForm.startMinute, appointmentForm.startAmPm);

    return new Date(
        datePart.getFullYear(),
        datePart.getMonth(),
        datePart.getDate(),
        start24.hour,
        start24.minute
    );
});

const endDate = computed(() => {
    const datePart = new Date(appointmentForm.date);
    const end24 = convertTo24Hour(appointmentForm.endHour, appointmentForm.endMinute, appointmentForm.endAmPm);

    let endDateTime = new Date(
        datePart.getFullYear(),
        datePart.getMonth(),
        datePart.getDate(),
        end24.hour,
        end24.minute
    );

    // If end time is earlier than start time, add one day
    if (endDateTime <= startDate.value) {
        endDateTime.setDate(endDateTime.getDate() + 1);
    }

    return endDateTime;
});

const totalDurationMinutes = computed(() => {
    const diffMs = endDate.value.getTime() - startDate.value.getTime();
    return Math.max(0, Math.floor(diffMs / 60000));
});

const formatTime = (hour, minute, ampm) => {
    return `${hour}:${minute.toString().padStart(2, '0')} ${ampm}`;
};

const formatDuration = computed(() => {
    const hours = Math.floor(totalDurationMinutes.value / 60);
    const minutes = totalDurationMinutes.value % 60;

    if (hours === 0) return `${minutes} minutes`;
    if (minutes === 0) return `${hours} hours`;
    return `${hours} hours ${minutes} minutes`;
});

const isEndTimeValid = computed(() => {
    return endDate.value > startDate.value;
});

// Get title based on appointment type
const getAppointmentTitle = computed(() => {
    switch (appointmentForm.type) {
        case 'Class':
            return appointmentForm.subject || 'Class';
        case 'Meeting':
            return appointmentForm.agenda || 'Meeting';
        case 'Event':
            return appointmentForm.title || 'Event';
        case 'Other type of activity':
            return appointmentForm.name || 'Activity';
        default:
            return 'Untitled Appointment';
    }
});

const getInitialFormState = () => ({
    type: 'Meeting',
    startHour: 9,
    startMinute: 0,
    startAmPm: 'AM',
    endHour: 10,
    endMinute: 0,
    endAmPm: 'AM',
    isHoliday: false,
    recurring: false,
    numberParticipants: null,
    deptOffice: '',
    organization: '',
    description: '',
    tablesChairs: false,
    airConditioner: false,
    whiteboard: false,
    additionalInstructions: '',
    driveLink: '',
    agenda: '',
    title: '',
    organizer: '',
    name: '',
    requester: props.currentRequester || '',
    subject: '',
    section: '',
    faculty: '',
    numberOfStudents: null,
});

// Watch props for initial data
watch(() => [props.selectedDate, props.initialHour, props.initialMinute, props.initialRoom], ([newDate, newHour, newMinute, newRoom]) => {
    if (!props.editingEvent) {
        Object.assign(appointmentForm, getInitialFormState());
        appointmentForm.date = newDate;
        appointmentForm.room = newRoom || '';
        appointmentForm.requester = props.currentRequester || '';
        applyRoomEquipmentDefaults(appointmentForm.room);

        if (newHour !== null && newMinute !== null) {
            // Convert 24-hour to 12-hour format
            let startHour12 = newHour > 12 ? newHour - 12 : newHour;
            if (startHour12 === 0) startHour12 = 12;

            appointmentForm.startHour = startHour12;
            appointmentForm.startMinute = newMinute;
            appointmentForm.startAmPm = (newHour >= 12) ? 'PM' : 'AM';

            // Set end time to 1 hour later
            let endHour24 = newHour + 1;
            if (endHour24 >= 24) endHour24 -= 24;

            let endHour12 = endHour24 > 12 ? endHour24 - 12 : endHour24;
            if (endHour12 === 0) endHour12 = 12;

            appointmentForm.endHour = endHour12;
            appointmentForm.endMinute = newMinute;
            appointmentForm.endAmPm = (endHour24 >= 12) ? 'PM' : 'AM';
        }
    }
}, { immediate: true });

// Watch for editing event
watch(() => props.editingEvent, (newEvent) => {
    if (newEvent) {
        // Populate form with event data for editing
        const extProps = newEvent.extendedProps || {};

        appointmentForm.room = extProps.room || '';
        appointmentForm.type = newEvent.list || 'Meeting';
        const _editStart = new Date(newEvent.start);
        appointmentForm.date = `${_editStart.getFullYear()}-${String(_editStart.getMonth() + 1).padStart(2, '0')}-${String(_editStart.getDate()).padStart(2, '0')}`;

        if (!newEvent.allDay) {
            const startDate = new Date(newEvent.start);
            const startHour24 = startDate.getHours();
            let startHour12 = startHour24 > 12 ? startHour24 - 12 : startHour24;
            if (startHour12 === 0) startHour12 = 12;

            appointmentForm.startHour = startHour12;
            appointmentForm.startMinute = startDate.getMinutes();
            appointmentForm.startAmPm = startHour24 >= 12 ? 'PM' : 'AM';

            if (newEvent.end) {
                const endDate = new Date(newEvent.end);
                const endHour24 = endDate.getHours();
                let endHour12 = endHour24 > 12 ? endHour24 - 12 : endHour24;
                if (endHour12 === 0) endHour12 = 12;

                appointmentForm.endHour = endHour12;
                appointmentForm.endMinute = endDate.getMinutes();
                appointmentForm.endAmPm = endHour24 >= 12 ? 'PM' : 'AM';
            }
        }

        // Populate all fields from extendedProps
        appointmentForm.agenda = extProps.agenda || extProps.subject || '';
        appointmentForm.title = newEvent.title || '';
        appointmentForm.subject = extProps.subject || '';
        appointmentForm.requester = extProps.requester || '';
        appointmentForm.deptOffice = extProps.college || '';
        appointmentForm.organization = extProps.building || '';
        appointmentForm.description = extProps.description || '';
        appointmentForm.faculty = extProps.faculty || '';
        appointmentForm.section = extProps.section || '';
        appointmentForm.numberOfStudents = extProps.numberOfStudents || null;
        appointmentForm.organizer = extProps.organizer || '';
        appointmentForm.name = extProps.name || '';

        const roomDefaults = getRoomEquipmentDefaults(appointmentForm.room);
        const eventEquipment = Array.isArray(extProps.equipment)
            ? extProps.equipment.map((item) => String(item).trim()).filter(Boolean)
            : [];
        const mergedNames = eventEquipment.length > 0 ? eventEquipment : roomDefaults;
        roomEquipmentOptions.value = buildEquipmentOptions(
            [...roomDefaults, ...eventEquipment],
            appointmentForm.room
        );
        appointmentForm.selectedEquipments = eventEquipment.length > 0 ? [...mergedNames] : roomDefaults;
    }
}, { immediate: true });

watch(() => props.currentRequester, (newRequester) => {
    if (!props.editingEvent) {
        appointmentForm.requester = newRequester || '';
    }
});

watch(() => appointmentForm.room, (newRoom) => {
    if (!newRoom || props.editingEvent) return;
    applyRoomEquipmentDefaults(newRoom);
});

watch(
    () => [props.roomEquipmentQuantitiesMap, props.globalEquipmentQuantities],
    () => refreshEquipmentOptionQuantities(),
    { deep: true }
);

watch(() => appointmentForm.selectedEquipments, (list) => {
    const values = Array.isArray(list) ? list.map((item) => String(item).toLowerCase()) : [];
    appointmentForm.tablesChairs = values.some((item) => item.includes('table') || item.includes('chair'));
    appointmentForm.airConditioner = values.some((item) => item.includes('air'));
    appointmentForm.whiteboard = values.some((item) => item.includes('whiteboard') || item.includes('white board'));
}, { deep: true });

// --- Modal handlers ---
const closeModal = () => emit('close');

const submitForm = () => {
    if (!String(appointmentForm.room || '').trim()) {
        alert('Please type or select a room.');
        return;
    }

    if (!isEndTimeValid.value) {
        alert('End time must be after start time. Please adjust the times.');
        return;
    }

    // Prepare data for the parent component
    const dataToSubmit = {
        // All form fields
        ...appointmentForm,

        // Computed date objects
        start: startDate.value,
        end: endDate.value,

        // Duration information
        durationHour: Math.floor(totalDurationMinutes.value / 60),
        durationMinute: totalDurationMinutes.value % 60,

        // Core event properties
        title: getAppointmentTitle.value,
        list: appointmentForm.type,
        allDay: totalDurationMinutes.value >= 1439,

        // Additional computed properties
        formattedDate: new Date(appointmentForm.date).toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }),
        formattedTime: formatTime(appointmentForm.startHour, appointmentForm.startMinute, appointmentForm.startAmPm) +
                      ' - ' +
                      formatTime(appointmentForm.endHour, appointmentForm.endMinute, appointmentForm.endAmPm)
    };

    console.log('Submitting appointment data:', dataToSubmit); // Debug log

    // Emit the final structured event data
    emit('success', dataToSubmit);
    closeModal();
};

// Validate time input
const validateTimeInput = () => {
    timeConflictWarning.value = '';

    if (!isEndTimeValid.value) {
        timeConflictWarning.value = 'End time must be after start time.';
        return false;
    }

    return true;
};

// Handle time change
const handleTimeChange = () => {
    validateTimeInput();
};
</script>

<template>
<div v-if="isVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4 sticky top-0 bg-white z-10 p-1 -m-1">
            <h3 class="text-xl font-bold">Appointment {{ editingEvent ? '(Editing)' : '' }}</h3>
            <button @click="closeModal" class="text-xl font-semibold">X</button>
        </div>

        <div class="mb-4">
            <label class="text-sm font-medium text-gray-700 block mb-1">Room</label>
            <div class="relative" @focusout="() => setTimeout(() => (showRoomDropdown = false), 120)">
                <input
                    v-model="appointmentForm.room"
                    type="text"
                    required
                    class="border border-gray-300 rounded-md p-2 pr-10 w-full appearance-none"
                    placeholder="Type or select a room"
                    @focus="openRoomDropdown"
                    @input="roomQuery = appointmentForm.room; openRoomDropdown()"
                >
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 pointer-events-auto"
                    tabindex="-1"
                    aria-label="Toggle room list"
                    @click="toggleRoomDropdown"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div
                    v-if="showRoomDropdown"
                    class="absolute z-20 mt-1 w-full max-h-48 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg"
                >
                    <button
                        v-for="room in filteredRoomOptions"
                        :key="room"
                        type="button"
                        class="block w-full px-3 py-2 text-left hover:bg-gray-100"
                        @click="selectRoom(room)"
                    >
                        {{ room }}
                    </button>
                    <div v-if="filteredRoomOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                        No matching rooms
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="text-sm font-medium text-gray-700 block mb-1">Requesting for a</label>
            <select v-model="appointmentForm.type" class="border border-gray-300 rounded-md p-2 w-full">
                <option>Class</option>
                <option>Meeting</option>
                <option>Event</option>
                <option>Other type of activity</option>
            </select>
        </div>

        <!-- Dynamic form component based on appointment type -->
        <component
            :is="appointmentForm.type === 'Class' ? ClassForm
                : appointmentForm.type === 'Meeting' ? MeetingForm
                : appointmentForm.type === 'Event' ? EventForm
                : OtherActivityForm"
            :formData="appointmentForm"
            class="pb-4"
        />

        <div class="mt-6 border-t pt-4">
            <h4 class="text-lg font-semibold mb-3">General Information</h4>

            <!-- Department/Office -->
            <label for="deptOffice" class="block text-sm font-medium text-gray-700">Dept/Office *</label>
            <input type="text" id="deptOffice" v-model="appointmentForm.deptOffice" required
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

            <!-- Organization -->
            <label for="organization" class="block text-sm font-medium text-gray-700 mt-4">Organization *</label>
            <input type="text" id="organization" v-model="appointmentForm.organization" required
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

            <!-- Holiday Checkbox -->
            <div class="mt-4 flex items-center">
                <input type="checkbox" id="isHoliday" v-model="appointmentForm.isHoliday" class="mr-2">
                <label for="isHoliday" class="text-sm font-medium text-gray-700">Is this date a recognized holiday?</label>
            </div>

            <!-- Start Date and Time Section -->
            <div class="mt-4">
                <label class="text-sm font-medium text-gray-700 block mb-2">Start Date and Time *</label>
                <div class="grid grid-cols-3 gap-2 items-end">
                    <div class="col-span-1">
                        <label for="startDate" class="block text-xs font-medium text-gray-500">Date*</label>
                        <input type="date" id="startDate" v-model="appointmentForm.date" required
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                          @change="handleTimeChange">
                    </div>
                    <div>
                        <label for="startHour" class="block text-xs font-medium text-gray-500">Hour*</label>
                        <input type="number" id="startHour" v-model.number="appointmentForm.startHour"
                          required min="1" max="12" step="1"
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                          @input="handleTimeChange">
                    </div>
                    <div>
                        <label for="startMinute" class="block text-xs font-medium text-gray-500">Minute*</label>
                        <input type="number" id="startMinute" v-model.number="appointmentForm.startMinute"
                          required min="0" max="59" step="15"
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                          @input="handleTimeChange">
                    </div>
                    <div>
                        <label for="startAmPm" class="block text-xs font-medium text-gray-500">AM/PM*</label>
                        <select id="startAmPm" v-model="appointmentForm.startAmPm" required
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                          @change="handleTimeChange">
                          <option>AM</option>
                          <option>PM</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- End Time Section -->
            <div class="mt-4">
                <label class="text-sm font-medium text-gray-700 block mb-2">End Time *</label>
                <div class="grid grid-cols-3 gap-2 items-end">
                    <div>
                        <label for="endHour" class="block text-xs font-medium text-gray-500">Hour*</label>
                        <input type="number" id="endHour" v-model.number="appointmentForm.endHour"
                          required min="1" max="12" step="1"
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                          @input="handleTimeChange"
                          :class="{ 'border-red-500': !isEndTimeValid }">
                    </div>
                    <div>
                        <label for="endMinute" class="block text-xs font-medium text-gray-500">Minute*</label>
                        <input type="number" id="endMinute" v-model.number="appointmentForm.endMinute"
                          required min="0" max="59" step="15"
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                          @input="handleTimeChange"
                          :class="{ 'border-red-500': !isEndTimeValid }">
                    </div>
                    <div>
                        <label for="endAmPm" class="block text-xs font-medium text-gray-500">AM/PM*</label>
                        <select id="endAmPm" v-model="appointmentForm.endAmPm" required
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 text-sm"
                          @change="handleTimeChange"
                          :class="{ 'border-red-500': !isEndTimeValid }">
                          <option>AM</option>
                          <option>PM</option>
                        </select>
                    </div>
                </div>

                <!-- Duration Display -->
                <div class="mt-3 p-2 bg-gray-50 rounded border">
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-gray-700">Duration:</span>
                        <span class="font-semibold" :class="{ 'text-red-500': !isEndTimeValid, 'text-green-600': isEndTimeValid }">
                            {{ formatDuration }}
                        </span>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>Start: {{ formatTime(appointmentForm.startHour, appointmentForm.startMinute, appointmentForm.startAmPm) }}</span>
                        <span>End: {{ formatTime(appointmentForm.endHour, appointmentForm.endMinute, appointmentForm.endAmPm) }}</span>
                    </div>
                    <div v-if="!isEndTimeValid" class="mt-1 text-xs text-red-500 font-medium">
                        ⚠️ End time must be after start time
                    </div>
                    <div v-if="timeConflictWarning" class="mt-1 text-xs text-red-500 font-medium">
                        ⚠️ {{ timeConflictWarning }}
                    </div>
                </div>
            </div>

            <!-- Recurring Meeting -->
            <div class="mt-4 flex items-center">
                <input type="checkbox" id="recurring" v-model="appointmentForm.recurring" class="mr-2">
                <label for="recurring" class="text-sm font-medium text-gray-700">Recurring Meeting</label>
            </div>

            <!-- Number of Participants -->
            <label v-if="appointmentForm.type !== 'Class'" for="numParticipants" class="block text-sm font-medium text-gray-700 mt-4">Number of Participants *</label>
            <input v-if="appointmentForm.type !== 'Class'" type="number" id="numParticipants"
              v-model.number="appointmentForm.numberParticipants" required min="1"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

            <!-- Description -->
            <label v-if="appointmentForm.type === 'Other type of activity' || appointmentForm.type === 'Class'"
                   for="description" class="block text-sm font-medium text-gray-700 mt-4">Description *</label>
            <textarea v-if="appointmentForm.type === 'Other type of activity' || appointmentForm.type === 'Class'"
              id="description" v-model="appointmentForm.description" rows="3" required
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500"></textarea>

            <!-- Equipment Section -->
            <div class="mt-4">
                <h5 class="text-sm font-semibold mb-2">Equipment</h5>
                <div v-if="roomEquipmentOptions.length > 0" class="flex flex-col space-y-2">
                    <div
                        v-for="equipment in roomEquipmentOptions"
                        :key="equipment.name"
                        class="flex justify-between items-center gap-3"
                    >
                        <label
                            :for="`equipment-${equipment.name}`"
                            class="min-w-0 flex-1 cursor-pointer"
                        >
                            <span class="text-sm font-medium text-gray-800 capitalize">{{ equipment.name }}</span>
                            <span
                                class="ml-2 inline-block text-xs font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full tabular-nums"
                            >
                                {{ formatEquipmentAvailability(equipment.quantity) }}
                            </span>
                        </label>
                        <input
                            :id="`equipment-${equipment.name}`"
                            type="checkbox"
                            :value="equipment.name"
                            v-model="appointmentForm.selectedEquipments"
                            class="shrink-0"
                        >
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500">
                    No equipment configured for this room yet.
                </p>
                <input type="hidden" :value="appointmentForm.selectedEquipments.join(',')">
                <div class="hidden">
                    {{ appointmentForm.tablesChairs }}{{ appointmentForm.airConditioner }}{{ appointmentForm.whiteboard }}
                </div>
            </div>

            <!-- Additional Instructions -->
            <label for="additionalInstructions" class="block text-sm font-medium text-gray-700 mt-4">Additional instructions</label>
            <textarea id="additionalInstructions" v-model="appointmentForm.additionalInstructions" rows="3"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500"></textarea>

            <!-- File Attachment -->
            <label for="driveLink" class="block text-sm font-medium text-gray-700 mt-4">Attach File <i class="text-gray-400">(i)</i></label>
            <input type="text" id="driveLink" v-model="appointmentForm.driveLink"
              placeholder="Attach your Google Drive Link here"
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

            <!-- Room Reminders -->
            <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                <h6 class="text-xs font-semibold text-red-700">Room Reminders:</h6>
                <ul class="list-disc list-inside text-sm text-red-600">
                    <li>Food is not allowed inside.</li>
                    <li>Please ensure your time slot does not conflict with existing appointments.</li>
                </ul>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex justify-end space-x-3">
            <button type="button" @click="closeModal"
              class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 transition">
                Close
            </button>
            <button
                type="button"
                @click="submitForm"
                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                :disabled="!isEndTimeValid || timeConflictWarning || !appointmentForm.deptOffice || !appointmentForm.organization"
            >
                {{ editingEvent ? 'Update Appointment' : 'Submit Appointment' }}
            </button>
        </div>
    </div>
</div>
</template>
