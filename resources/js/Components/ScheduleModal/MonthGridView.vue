<script setup>
import { ref } from 'vue'
// ==============================
// PROPS
// ==============================
// dateGrid: 6x7 array representing calendar weeks & days
// days: ["Sunday", "Monday", ...]
const props = defineProps({
    dateGrid: Array,
    days: Array
})

// ==============================
// EMITS
// ==============================
// emitDateClick → create new event
// selectEvent → open existing event
// dayClick → highlight/select day
const emit = defineEmits(['emitDateClick', 'selectEvent', 'dayClick'])

const clusterModal = ref({
    visible: false,
    date: '',
    events: []
})

// ==============================
// HANDLE DAY CLICK
// ==============================
// Fires when user clicks a calendar cell
// Emits the clicked date with default time (9:00 AM)
const formatDateDisplay = (date) => {
    const d = new Date(date)
    return d.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    })
}

const closeClusterModal = () => {
    clusterModal.value = {
        visible: false,
        date: '',
        events: []
    }
}

const handleClusterEventClick = (event) => {
    emit('selectEvent', event)
    closeClusterModal()
}

const handleDateClick = (day) => {
    emit('dayClick', day.date) // notify parent of day click

    const dayEvents = [...(day.allDayEvents || []), ...(day.events || [])]

    if (dayEvents.length > 0) {
        clusterModal.value = {
            visible: true,
            date: formatDateDisplay(day.date),
            events: dayEvents
                .slice()
                .sort((a, b) => new Date(a.start).getTime() - new Date(b.start).getTime())
        }
        return
    }

    const exactDate = new Date(day.date)
    exactDate.setHours(9, 0, 0, 0) // default appointment time
    emit('emitDateClick', exactDate)
}

// ==============================
// CHECK IF DAY IS IN CURRENT MONTH
// ==============================
const isSameMonth = (date) => {
    const d = new Date(date)
    const now = new Date()
    return d.getMonth() === now.getMonth() &&
           d.getFullYear() === now.getFullYear()
}

// ==============================
// FORMAT TIME (HH:MM AM/PM)
// ==============================
const formatTime = (date) => {
    const d = new Date(date)
    return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    })
}

const formatEventTime = (event) => {
    const start = new Date(event.start)
    const end = event.end ? new Date(event.end) : new Date(start.getTime() + 60 * 60000)
    return `${formatTime(start)} - ${formatTime(end)}`
}

// ==============================
// TRUNCATE TEXT (EVENT TITLES)
// ==============================
const truncateText = (text, maxLength) => {
    if (!text) return ''
    if (text.length <= maxLength) return text
    return text.substring(0, maxLength) + '...'
}
</script>

<template>
    <div class="w-full overflow-hidden">
        <!-- MAIN CALENDAR TABLE -->
        <table class="w-full table-fixed border-collapse border border-yellow-600">

            <!-- ================= HEADER ================= -->
            <thead>
                <tr class="bg-[#7A0C23]">
                    <th
                        v-for="day in days"
                        :key="day"
                        class="w-[14.28%] px-2 py-3 text-xs font-medium text-white uppercase text-center border border-yellow-600"
                    >
                        {{ day }}
                    </th>
                </tr>
            </thead>

            <!-- ================= BODY ================= -->
            <tbody>
                <!-- EACH WEEK -->
                <tr v-for="(week, wIdx) in dateGrid" :key="wIdx">

                    <!-- EACH DAY CELL -->
                    <td
                        v-for="day in week"
                        :key="new Date(day.date).toISOString()"
                        @click="handleDateClick(day)"
                        class="p-0 border border-yellow-400 align-top cursor-pointer relative"
                        :class="[
                            day.dayClass,
                            day.isToday
                                ? 'bg-yellow-400 ring-2 ring-inset ring-red-500/50'
                                : isSameMonth(day.date)
                                ? 'bg-white hover:bg-blue-100'
                                : 'bg-gray-50 hover:bg-gray-100'
                        ]"
                    >
                        <!-- FIXED HEIGHT CELL (NEVER STRETCHES) -->
                        <div class="h-36 flex flex-col p-1 overflow-hidden">

                            <!-- ===== DATE HEADER ===== -->
                            <div class="flex justify-between items-center text-xs font-bold flex-shrink-0 mb-1">
                                <!-- EVENT COUNT BADGE -->
                                <div
                                    v-if="(day.events || []).length > 0"
                                    class="text-xs font-semibold text-[#7A0C23] bg-yellow-100 px-2 py-1 rounded-full"
                                >
                                    {{ day.events.length }} {{ day.events.length === 1 ? 'event' : 'events' }}
                                </div>

                                <!-- DATE NUMBER -->
                                <span
                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs"
                                    :class="day.isToday ? 'bg-red-600 text-white' : 'text-gray-700'"
                                >
                                    {{ new Date(day.date).getDate() }}
                                </span>
                            </div>

                            <!-- ===== EVENTS LIST (SCROLLABLE) ===== -->
                            <div class="flex-grow overflow-y-auto space-y-1 custom-scrollbar">

                                <!-- ALL-DAY EVENTS -->
                                <div
                                    v-for="event in (day.allDayEvents || [])"
                                    :key="'all-' + event.id"
                                    @click.stop="emit('selectEvent', event)"
                                    class="text-[10px] px-1.5 py-1 rounded truncate bg-green-100 text-green-800 border-l-2 border-green-500 cursor-pointer"
                                >
                                    ⚫ {{ truncateText(event.title || event.extendedProps?.subject, 20) }}
                                </div>

                                <!-- TIMED EVENTS -->
                                <div
                                    v-for="event in (day.events || []).slice(0, 3)"
                                    :key="'time-' + event.id"
                                    @click.stop="emit('selectEvent', event)"
                                    class="text-[10px] px-1.5 py-1 rounded truncate bg-blue-100 text-blue-800 border-l-2 border-blue-500 cursor-pointer"
                                >
                                    <b>{{ formatTime(event.start) }}</b>
                                    {{ truncateText(event.title || event.extendedProps?.subject, 15) }}
                                </div>

                                <!-- MORE EVENTS INDICATOR -->
                                <div
                                    v-if="(day.events || []).length > 3"
                                    class="text-[10px] text-center bg-yellow-100 text-yellow-800 rounded py-1"
                                >
                                    + {{ day.events.length - 3 }} more
                                </div>

                                <!-- NO EVENTS -->
                                <div
                                    v-if="(day.events || []).length === 0 && (day.allDayEvents || []).length === 0"
                                    class="text-[10px] text-gray-400 italic text-center pt-2"
                                >
                                    No events
                                </div>

                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Cluster modal for month view day click -->
    <div
        v-if="clusterModal.visible"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/50"
        @click.self="closeClusterModal"
    >
        <div class="w-full max-w-2xl mx-4 bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100">
            <div class="bg-[#7A0C23] px-5 py-4 text-white">
                <h3 class="text-lg font-semibold">Day Schedule</h3>
                <p class="text-sm opacity-90">{{ clusterModal.date }}</p>
            </div>

            <div class="px-5 py-3 border-b bg-gray-50 text-xs text-gray-600 font-medium">
                {{ clusterModal.events.length }} schedule(s) found
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
                        <p><span class="font-semibold">Time</span>: {{ event.allDay ? 'All Day' : formatEventTime(event) }}</p>
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
</template>

<style scoped>
/* Ensures equal column width */
table {
    table-layout: fixed;
    border-spacing: 0;
}

/* Prevents accidental text selection */
td {
    user-select: none;
}

/* Custom scrollbar inside cells */
.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Small hover lift on events */
.cursor-pointer:hover {
    transform: translateY(-1px);
    transition: transform 0.2s ease;
}
</style>
