<script setup>
import { computed, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faTag, faLock, faClock } from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    viewMode: { type: String, required: true }, // 'day' or 'week'
    weekDays: { type: Array, required: false, default: () => [] },
    singleDay: { type: Object, required: false, default: () => ({}) },
    hourSlots: { type: Array, required: true },
    getEventStyle: { type: Function, required: true },
    dateToTimeString: { type: Function, required: true },
    formatEventTime: { type: Function, required: true },
    events: { type: Array, default: () => [] }
});

const emit = defineEmits(['selectEvent', 'selectDate', 'emitDateClick']);

const clusterModal = ref({
    visible: false,
    date: '',
    slot: '',
    events: []
});

const daysToRender = computed(() => {
    return props.viewMode === 'day' ? [props.singleDay] : props.weekDays;
});

// Format date for display
const formatDateDisplay = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Get day name for header
const getDayName = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { weekday: 'long' });
};

// Get month and day for header
const getMonthDay = (date) => {
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Check if a specific time slot is occupied by any event
const isTimeSlotOccupied = (day, slot) => {
    const slotStart = new Date(day.date);
    slotStart.setHours(slot.hour, slot.minute, 0, 0);

    const slotEnd = new Date(slotStart);
    slotEnd.setMinutes(slotEnd.getMinutes() + 30); // Each slot is 30 minutes

    return day.events.some(event => {
        const eventStart = new Date(event.start);
        const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);

        return slotStart < eventEnd && slotEnd > eventStart;
    });
};

// Get overlapping events for a time slot
const getOverlappingEvents = (day, slot) => {
    const slotStart = new Date(day.date);
    slotStart.setHours(slot.hour, slot.minute, 0, 0);

    const slotEnd = new Date(slotStart);
    slotEnd.setMinutes(slotEnd.getMinutes() + 30);

    return day.events.filter(event => {
        const eventStart = new Date(event.start);
        const eventEnd = event.end ? new Date(event.end) : new Date(eventStart.getTime() + 60 * 60000);
        return slotStart < eventEnd && slotEnd > eventStart;
    });
};

// Get event color based on type
const getEventColor = (event) => {
    const type = event.extendedProps?.type?.toLowerCase() || event.list?.toLowerCase() || '';
    if (type.includes('class')) return 'bg-blue-500 border-blue-600';
    if (type.includes('meeting')) return 'bg-green-500 border-green-600';
    if (type.includes('event')) return 'bg-purple-500 border-purple-600';
    return 'bg-red-500 border-red-600';
};

// Get occupancy color for time slots
const getOccupancyColor = (day, slot) => {
    const overlappingEvents = getOverlappingEvents(day, slot);

    if (overlappingEvents.length === 0) {
        return 'hover:bg-blue-100/50';
    } else if (overlappingEvents.length === 1) {
        const eventType = overlappingEvents[0].extendedProps?.type?.toLowerCase() || '';
        if (eventType.includes('class')) return 'bg-blue-100/70 hover:bg-blue-200/70';
        if (eventType.includes('meeting')) return 'bg-green-100/70 hover:bg-green-200/70';
        if (eventType.includes('event')) return 'bg-purple-100/70 hover:bg-purple-200/70';
        return 'bg-red-100/70 hover:bg-red-200/70';
    } else {
        return 'bg-yellow-100/70 hover:bg-yellow-200/70';
    }
};

const isTodayHeader = (day) => {
    const today = new Date();
    const dayDate = new Date(day.date);
    return dayDate.toDateString() === today.toDateString();
};

// Accurate time slot click handler
const handleTimeSlotClick = (day, slot, clickEvent) => {
    if (isTimeSlotOccupied(day, slot)) {
        const overlappingEvents = getOverlappingEvents(day, slot);
        if (overlappingEvents.length > 0) {
            clusterModal.value = {
                visible: true,
                date: formatDateDisplay(day.date),
                slot: formatSlotTime(slot.hour, slot.minute),
                events: overlappingEvents
                    .slice()
                    .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime())
            };
            return;
        }
    }

    const exactDate = new Date(day.date);
    exactDate.setHours(slot.hour, slot.minute, 0, 0);
    const position = clickEvent
        ? { x: clickEvent.clientX, y: clickEvent.clientY }
        : null;
    emit('emitDateClick', exactDate, slot.hour, slot.minute, position);
};

const closeClusterModal = () => {
    clusterModal.value = {
        visible: false,
        date: '',
        slot: '',
        events: []
    };
};

const handleClusterEventClick = (event) => {
    emit('selectEvent', event);
    closeClusterModal();
};

// Get occupancy tooltip text
const getOccupancyTooltip = (day, slot) => {
    const overlappingEvents = getOverlappingEvents(day, slot);

    if (overlappingEvents.length === 0) {
        return `Available at ${slot.hour.toString().padStart(2, '0')}:${slot.minute.toString().padStart(2, '0')}\nClick to schedule`;
    }

    const eventsInfo = overlappingEvents.map(event => {
        const time = props.formatEventTime(event);
        const title = event.title || event.extendedProps?.subject || 'Untitled';
        const room = event.extendedProps?.room || 'Unknown Room';
        const type = event.extendedProps?.type || 'Event';
        return `${time}: ${title} (${room}) - ${type}`;
    }).join('\n');

    return `Occupied at ${slot.hour.toString().padStart(2, '0')}:${slot.minute.toString().padStart(2, '0')}\n\n${eventsInfo}`;
};

// Format a single time as "06:00 AM"
const formatHHMM = (hour, minute) => {
    const ampm = hour >= 12 ? 'PM' : 'AM';
    let displayHour = hour % 12;
    if (displayHour === 0) displayHour = 12;
    const hh = String(displayHour).padStart(2, '0');
    const mm = String(minute).padStart(2, '0');
    return `${hh}:${mm} ${ampm}`;
};

// Format slot label as a 30-minute range to match grid row height, e.g. "10:00 AM - 10:30 AM"
const formatSlotTime = (hour, minute) => {
    let endHour = hour;
    let endMinute = minute + 30;
    if (endMinute >= 60) {
        endMinute -= 60;
        endHour += 1;
    }
    if (endHour >= 24) endHour -= 24;
    return `${formatHHMM(hour, minute)} - ${formatHHMM(endHour, endMinute)}`;
};
</script>

<template>
    <div class="time-grid-view border border-yellow-300 rounded-lg shadow-lg bg-white">
        <!-- Header with day labels -->
        <div class="bg-[#7A0C23] flex border-b border-yellow-600">
            <div class="w-40 shrink-0 bg-[#7A0C23]"></div>
            <div class="flex-grow grid" :style="`grid-template-columns: repeat(${daysToRender.length}, minmax(0, 1fr))`">
                <div
                    v-for="(day, index) in daysToRender"
                    :key="index"
                    @click="viewMode === 'week' ? emit('selectDate', day.date) : null"
                    :class="[
                        'p-3 text-center border-r border-yellow-500 last:border-r-0 cursor-pointer transition duration-150',
                        isTodayHeader(day) ? 'bg-red-800 text-white' : 'bg-[#7A0C23] text-white',
                        viewMode === 'week' ? 'hover:bg-[#8A1C33]' : ''
                    ]"
                >
                    <div class="font-bold text-lg">{{ getDayName(day.date) }}</div>
                    <div class="text-sm opacity-90">{{ getMonthDay(day.date) }}</div>
                    <div v-if="viewMode === 'day'" class="text-xs mt-1 font-normal">
                        {{ formatDateDisplay(day.date) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- All Day Events Section -->
        <div class="bg-[#7A0C23] flex border-b border-yellow-600">
            <div class="bg-[#7A0C23] w-40 shrink-0 border-r border-yellow-500 py-2 px-1 text-xs font-bold text-white flex items-center justify-center">
                All Day
            </div>
            <div class="flex-grow grid" :style="`grid-template-columns: repeat(${daysToRender.length}, minmax(0, 1fr))`">
                <div
                    v-for="(day, index) in daysToRender"
                    :key="index"
                    class="p-1 border-r border-yellow-500 last:border-r-0 min-h-[40px]"
                >
                    <div
                        v-for="event in day.allDayEvents"
                        :key="event.id"
                        @click="emit('selectEvent', event)"
                        class="text-xs p-2 mb-1 rounded-md cursor-pointer bg-indigo-100 text-indigo-800 hover:bg-indigo-200 transition truncate border-l-4 border-indigo-600"
                        :title="event.title || event.extendedProps?.subject"
                    >
                        <FontAwesomeIcon :icon="faTag" class="w-3 h-3 mr-1 inline" />
                        {{ event.title || event.extendedProps?.subject }}
                    </div>
                    <div
                        v-if="day.allDayEvents.length === 0"
                        class="text-xs text-yellow-400 italic py-2 text-center"
                    >
                        No all-day events
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Grid -->
        <div class="flex h-[calc(100vh-300px)] overflow-y-auto relative bg-white">
            <!-- Time Labels (Left Side) -->
            <div class="w-40 shrink-0 border-r border-gray-200 bg-white sticky left-0 z-30">
                <div class="relative w-full h-full">
                    <div
                        v-for="(slot, index) in hourSlots"
                        :key="index"
                        class="absolute w-full text-[11px] text-center px-1 flex items-center justify-center"
                        :style="`top: ${index * 3}rem; height: 3rem;`"
                    >
                        <span
                            :class="[
                                'rounded-md px-2 py-0.5 border font-semibold whitespace-nowrap',
                                slot.minute === 0
                                    ? 'bg-red-50 border-red-300 text-[#7A0C23]'
                                    : 'bg-red-50/60 border-red-200 text-[#7A0C23]/80'
                            ]"
                        >
                            {{ formatSlotTime(slot.hour, slot.minute) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Time Grid Content -->
            <div class="flex-grow grid relative" :style="`grid-template-columns: repeat(${daysToRender.length}, minmax(0, 1fr))`">
                <!-- Time Slots for each day -->
                <div
                    v-for="(day, dayIndex) in daysToRender"
                    :key="dayIndex"
                    :class="[
                        'border-r border-gray-200 last:border-r-0 relative',
                        isTodayHeader(day) ? 'bg-blue-50/40' : 'bg-white'
                    ]"
                >
                    <!-- Time Slot Grid -->
                    <div
                        v-for="(slot, slotIndex) in hourSlots"
                        :key="slotIndex"
                        @click="handleTimeSlotClick(day, slot, $event)"
                        :class="[
                            'h-12 transition duration-100 cursor-default relative group',
                            getOccupancyColor(day, slot),
                            slot.minute === 0 ? 'border-t-2 border-gray-300' : 'border-t border-dashed border-gray-200'
                        ]"
                        :title="getOccupancyTooltip(day, slot)"
                    >
                        <div
                            v-if="isTimeSlotOccupied(day, slot)"
                            class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <div class="bg-black/50 text-white text-xs px-2 py-1 rounded flex items-center">
                                <FontAwesomeIcon :icon="faLock" class="w-3 h-3 mr-1" />
                                Occupied
                            </div>
                        </div>
                    </div>

                    <!-- Events -->
                    <div
                        v-for="event in day.events"
                        :key="event.id"
                        @click="emit('selectEvent', event)"
                        class="absolute left-1 right-1 mx-0.5 px-2 py-1 rounded-lg cursor-pointer text-white shadow-md z-20 overflow-hidden border-l-4 select-none"
                        :class="[getEventColor(event)]"
                        :style="props.getEventStyle(event, day.events)"
                        :title="`${props.formatEventTime(event)}: ${event.title || event.extendedProps?.subject} - ${event.extendedProps?.room || 'Unknown Room'}`"
                    >
                        <p class="text-xs font-bold leading-tight truncate">
                            {{ event.title || event.extendedProps?.subject }}
                        </p>
                        <p class="text-[0.7rem] leading-tight opacity-90">
                            {{ props.formatEventTime(event) }}
                        </p>
                        <p v-if="event.extendedProps?.room" class="text-[0.6rem] leading-tight opacity-75 truncate">
                            {{ event.extendedProps.room }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="bg-gray-50 border-t border-gray-200 p-3">
            <div class="flex flex-wrap items-center justify-center gap-4 text-xs">
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-blue-100/70 rounded mr-2"></div>
                    <span>Class Occupied</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-green-100/70 rounded mr-2"></div>
                    <span>Meeting Occupied</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-purple-100/70 rounded mr-2"></div>
                    <span>Event Occupied</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-yellow-100/70 rounded mr-2"></div>
                    <span>Other Activity of</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-white border border-gray-300 rounded mr-2"></div>
                    <span>Available</span>
                </div>
                <div class="flex items-center ml-4 text-gray-600">
                    <FontAwesomeIcon :icon="faLock" class="w-3 h-3 mr-1" />
                    <span>Hover over occupied slots for details</span>
                </div>
            </div>
        </div>

        <!-- Cluster modal for overlapping events -->
        <div
            v-if="clusterModal.visible"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50"
            @click.self="closeClusterModal"
        >
            <div class="w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100">
                <div class="bg-[#7A0C23] px-5 py-4 text-white">
                    <h3 class="text-lg font-semibold">Occupied Slot Details</h3>
                    <p class="text-sm opacity-90">{{ clusterModal.date }} | {{ clusterModal.slot }}</p>
                </div>

                <div class="px-5 py-3 border-b bg-gray-50 text-xs text-gray-600 font-medium">
                    {{ clusterModal.events.length }} schedule(s) in this slot
                </div>

                <div class="p-4 max-h-[60vh] overflow-y-auto space-y-3">
                    <button
                        v-for="event in clusterModal.events"
                        :key="event.id"
                        type="button"
                        class="w-full text-left rounded-xl border border-gray-200 bg-white p-4 hover:border-[#7A0C23]/30 hover:shadow-sm transition"
                        @click="handleClusterEventClick(event)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-base font-semibold text-gray-900 truncate">
                                {{ event.title || event.extendedProps?.subject || 'Untitled' }}
                            </p>
                            <span class="px-2 py-1 rounded-full text-[11px] font-semibold bg-[#7A0C23]/10 text-[#7A0C23] whitespace-nowrap">
                                {{ event.extendedProps?.type || 'Event' }}
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-gray-700 space-y-1">
                            <p><span class="font-semibold">Time</span>: {{ props.formatEventTime(event) }}</p>
                            <p><span class="font-semibold">Room</span>: {{ event.extendedProps?.room || 'N/A' }}</p>
                        </div>
                    </button>
                </div>

                <div class="px-4 py-3 border-t flex justify-end">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg text-white bg-[#7A0C23] hover:opacity-90 transition"
                        @click="closeClusterModal"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.time-grid-view {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.w-20 {
    min-width: 5rem;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Ensure events are properly positioned */
.absolute {
    position: absolute;
}

/* Group hover effects */
.group:hover .group-hover\:opacity-100 {
    opacity: 1;
}

/* Occupied slot styling */
.bg-blue-100\/70 {
    background-color: rgba(219, 234, 254, 0.7);
}
.bg-green-100\/70 {
    background-color: rgba(209, 250, 229, 0.7);
}
.bg-purple-100\/70 {
    background-color: rgba(233, 213, 255, 0.7);
}
.bg-red-100\/70 {
    background-color: rgba(254, 226, 226, 0.7);
}
.bg-yellow-100\/70 {
    background-color: rgba(254, 249, 195, 0.7);
}

/* Available slot hover */
.hover\:bg-blue-100\/50:hover {
    background-color: rgba(219, 234, 254, 0.5);
}
</style>
