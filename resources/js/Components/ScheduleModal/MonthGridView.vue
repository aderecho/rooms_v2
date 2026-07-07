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
    <div class="w-full overflow-hidden rounded-xl border border-slate-200 bg-white">
        <!-- MAIN CALENDAR TABLE -->
        <table class="w-full table-fixed border-collapse">

            <!-- ================= HEADER ================= -->
            <thead>
                <tr class="bg-slate-50">
                    <th
                        v-for="day in days"
                        :key="day"
                        class="w-[14.28%] border-b border-slate-200 px-2 py-3 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
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
                        class="relative cursor-pointer border border-slate-100 p-0 align-top transition"
                        :class="[
                            day.dayClass,
                            day.isToday
                                ? 'bg-emerald-50 ring-2 ring-inset ring-[#005740]/20'
                                : isSameMonth(day.date)
                                ? 'bg-white hover:bg-emerald-50/50'
                                : 'bg-slate-50/70 hover:bg-slate-100'
                        ]"
                    >
                        <!-- FIXED HEIGHT CELL (NEVER STRETCHES) -->
                        <div class="flex h-36 flex-col overflow-hidden p-2">

                            <!-- ===== DATE HEADER ===== -->
                            <div class="mb-2 flex flex-shrink-0 items-center justify-between text-xs font-bold">
                                <!-- EVENT COUNT BADGE -->
                                <div
                                    v-if="(day.events || []).length > 0"
                                    class="rounded-full bg-[#005740]/10 px-2 py-1 text-[11px] font-semibold text-[#005740]"
                                >
                                    {{ day.events.length }} {{ day.events.length === 1 ? 'event' : 'events' }}
                                </div>

                                <!-- DATE NUMBER -->
                                <span
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                                    :class="day.isToday ? 'bg-[#005740] text-white' : 'text-slate-700'"
                                >
                                    {{ new Date(day.date).getDate() }}
                                </span>
                            </div>

                            <!-- ===== EVENTS LIST (SCROLLABLE) ===== -->
                            <div class="custom-scrollbar flex-grow space-y-1 overflow-y-auto">

                                <!-- ALL-DAY EVENTS -->
                                <div
                                    v-for="event in (day.allDayEvents || [])"
                                    :key="'all-' + event.id"
                                    @click.stop="emit('selectEvent', event)"
                                    class="cursor-pointer truncate rounded-md border-l-2 border-[#005740] bg-[#005740]/10 px-2 py-1 text-[10px] font-medium text-[#005740]"
                                >
                                    {{ truncateText(event.title || event.extendedProps?.subject, 20) }}
                                </div>

                                <!-- TIMED EVENTS -->
                                <div
                                    v-for="event in (day.events || []).slice(0, 3)"
                                    :key="'time-' + event.id"
                                    @click.stop="emit('selectEvent', event)"
                                    class="cursor-pointer truncate rounded-md border-l-2 border-[#005740] bg-white px-2 py-1 text-[10px] font-medium text-slate-700 shadow-sm ring-1 ring-slate-200"
                                >
                                    <b>{{ formatTime(event.start) }}</b>
                                    {{ truncateText(event.title || event.extendedProps?.subject, 15) }}
                                </div>

                                <!-- MORE EVENTS INDICATOR -->
                                <div
                                    v-if="(day.events || []).length > 3"
                                    class="rounded-md bg-slate-100 py-1 text-center text-[10px] font-medium text-slate-600"
                                >
                                    + {{ day.events.length - 3 }} more
                                </div>

                                <!-- NO EVENTS -->
                                <div
                                    v-if="(day.events || []).length === 0 && (day.allDayEvents || []).length === 0"
                                    class="pt-3 text-center text-[10px] text-slate-400"
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
            <div class="bg-[#005740] px-5 py-4 text-white">
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
                    class="w-full text-left rounded-xl border border-gray-200 bg-white p-4 hover:border-[#005740]/30 hover:shadow-sm transition"
                    @click="handleClusterEventClick(event)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-base font-semibold text-gray-900 truncate">
                            {{ event.title || event.extendedProps?.subject || 'Untitled' }}
                        </p>
                        <span class="px-2 py-1 rounded-full text-[11px] font-semibold bg-[#005740]/10 text-[#005740] whitespace-nowrap">
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
                    class="px-4 py-2 rounded-lg text-white bg-[#005740] hover:opacity-90 transition"
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
