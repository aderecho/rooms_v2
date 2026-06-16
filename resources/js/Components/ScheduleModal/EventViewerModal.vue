<script setup>
import { computed } from 'vue';
import { formatDateDisplay, formatTimeDisplay } from './utils/scheduleHelpers';
import StatusBadge from '@/Components/ScheduleModal/StatusBadge.vue';

const props = defineProps({
    isVisible: Boolean,
    event: Object,
});

defineEmits(['close', 'edit', 'delete']);

const ext = computed(() => props.event?.extendedProps || {});

const hasValue = (v) => {
    if (v === null || v === undefined) return false;
    if (Array.isArray(v)) return v.length > 0;
    return String(v).trim() !== '' && v !== 'N/A';
};
</script>

<template>
    <div
        v-if="isVisible"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    >
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center mb-4 sticky top-0 bg-white z-10 p-1 -m-1">
                <h3 class="text-xl font-bold">Appointment Details</h3>
                <button @click="$emit('close')" class="text-xl font-semibold">X</button>
            </div>

            <div v-if="event" class="space-y-4">
                <!-- Basic Info -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-start justify-between mb-2 gap-3">
                        <h4 class="text-lg font-bold text-[#7A0C23]">{{ event.title }}</h4>
                        <StatusBadge
                            v-if="ext.status"
                            :status="ext.status"
                            size="md"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-600">Room:</p>
                            <p class="text-base">{{ ext.room || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600">Building:</p>
                            <p class="text-base">{{ ext.building || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600">College/Office:</p>
                            <p class="text-base">{{ ext.college || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-600">Type:</p>
                            <p class="text-base">{{ ext.type || event.list || 'Event' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Date & Time -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h5 class="font-semibold text-blue-800 mb-2">Date &amp; Time</h5>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-semibold text-blue-600">Date:</p>
                            <p class="text-base">{{ formatDateDisplay(event.start) }}</p>
                        </div>
                        <div v-if="!event.allDay">
                            <p class="text-sm font-semibold text-blue-600">Time:</p>
                            <p class="text-base">
                                {{ formatTimeDisplay(event.start) }} - {{ formatTimeDisplay(event.end) }}
                            </p>
                        </div>
                        <div v-else>
                            <p class="text-sm font-semibold text-blue-600">Time:</p>
                            <p class="text-base">All Day</p>
                        </div>
                    </div>
                    <p v-if="ext.isRecurring" class="text-xs text-blue-700 mt-2 italic">
                        This is a recurring appointment.
                    </p>
                </div>

                <!-- Class-specific info -->
                <div
                    v-if="hasValue(ext.courseCode) || hasValue(ext.section) || hasValue(ext.faculty) || hasValue(ext.numberOfStudents)"
                    class="bg-indigo-50 p-4 rounded-lg"
                >
                    <h5 class="font-semibold text-indigo-800 mb-2">Class Info</h5>
                    <div class="grid grid-cols-2 gap-4">
                        <div v-if="hasValue(ext.courseCode)">
                            <p class="text-sm font-semibold text-indigo-600">Course Code:</p>
                            <p class="text-base">{{ ext.courseCode }}</p>
                        </div>
                        <div v-if="hasValue(ext.section)">
                            <p class="text-sm font-semibold text-indigo-600">Section:</p>
                            <p class="text-base">{{ ext.section }}</p>
                        </div>
                        <div v-if="hasValue(ext.faculty)">
                            <p class="text-sm font-semibold text-indigo-600">Faculty:</p>
                            <p class="text-base">{{ ext.faculty }}</p>
                        </div>
                        <div v-if="hasValue(ext.numberOfStudents)">
                            <p class="text-sm font-semibold text-indigo-600">Participants:</p>
                            <p class="text-base">{{ ext.numberOfStudents }}</p>
                        </div>
                    </div>
                </div>

                <!-- Agenda (Meeting) -->
                <div v-if="hasValue(ext.agenda)" class="bg-purple-50 p-4 rounded-lg">
                    <h5 class="font-semibold text-purple-800 mb-2">Agenda</h5>
                    <p class="text-base">{{ ext.agenda }}</p>
                </div>

                <!-- Description -->
                <div v-if="hasValue(ext.description)" class="bg-green-50 p-4 rounded-lg">
                    <h5 class="font-semibold text-green-800 mb-2">Description</h5>
                    <p class="text-base whitespace-pre-line">{{ ext.description }}</p>
                </div>

                <!-- Equipment -->
                <div v-if="hasValue(ext.equipment)" class="bg-orange-50 p-4 rounded-lg">
                    <h5 class="font-semibold text-orange-800 mb-2">Equipment Needed</h5>
                    <ul class="list-disc list-inside text-base space-y-1">
                        <li v-for="(item, i) in ext.equipment" :key="i">{{ item }}</li>
                    </ul>
                </div>

                <!-- Requester -->
                <div v-if="hasValue(ext.requester)" class="bg-yellow-50 p-4 rounded-lg">
                    <h5 class="font-semibold text-yellow-800 mb-2">Requester</h5>
                    <p class="text-base">{{ ext.requester }}</p>
                    <p v-if="hasValue(ext.organizer) && ext.organizer !== ext.requester" class="text-sm text-yellow-700 mt-1">
                        Organizer: {{ ext.organizer }}
                    </p>
                </div>

                <!-- Additional Instructions -->
                <div v-if="hasValue(ext.additional)" class="bg-gray-100 p-4 rounded-lg">
                    <h5 class="font-semibold text-gray-700 mb-2">Additional Instructions</h5>
                    <ul class="list-disc list-inside text-base space-y-1">
                        <li v-for="(item, i) in ext.additional" :key="i">{{ item }}</li>
                    </ul>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        @click="$emit('close')"
                        class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        Close
                    </button>
                    <button
                        @click="$emit('edit', event)"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
                    >
                        Edit
                    </button>
                    <button
                        @click="$emit('delete', event)"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
