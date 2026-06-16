<script setup>
import { formatDateDisplay, formatTimeDisplay } from './utils/scheduleHelpers';

defineProps({
  isVisible: Boolean,
  events: Array,
  date: Date
});

defineEmits(['close', 'view-event', 'edit-event', 'delete-event', 'add-appointment']);
</script>

<template>
  <div v-if="isVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 max-h-screen overflow-y-auto">
      <div class="flex justify-between items-center mb-4 sticky top-0 bg-white z-10 p-1 -m-1">
        <h3 class="text-xl font-bold">Appointments on {{ date ? formatDateDisplay(date) : '' }}</h3>
        <button @click="$emit('close')" class="text-xl font-semibold">X</button>
      </div>

      <div v-if="events.length > 0" class="space-y-4">
        <div v-for="event in events" :key="event.id"
             class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition"
             @click="$emit('view-event', event)">
          <div class="flex justify-between items-start">
            <div>
              <h4 class="text-lg font-bold text-[#7A0C23]">{{ event.title }}</h4>
              <div class="flex items-center space-x-4 mt-2 text-sm">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                  </svg>
                  {{ event.extendedProps?.room || 'N/A' }}
                </span>
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                  {{ event.allDay ? 'All Day' : `${formatTimeDisplay(event.start)} - ${formatTimeDisplay(event.end)}` }}
                </span>
                <span :class="['px-2 py-1 rounded-full text-xs font-medium',
                  (event.extendedProps?.type || event.list) === 'Class' ? 'bg-blue-100 text-blue-800' :
                  (event.extendedProps?.type || event.list) === 'Meeting' ? 'bg-green-100 text-green-800' :
                  (event.extendedProps?.type || event.list) === 'Event' ? 'bg-purple-100 text-purple-800' :
                  'bg-yellow-100 text-yellow-800']">
                  {{ event.extendedProps?.type || event.list || 'Event' }}
                </span>
              </div>
            </div>
            <div class="flex space-x-2">
              <button @click.stop="$emit('edit-event', event)" class="p-2 text-green-600 hover:text-green-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
              <button @click.stop="$emit('delete-event', event)" class="p-2 text-red-600 hover:text-red-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
              </button>
            </div>
          </div>

          <div class="mt-3 text-sm text-gray-600">
            <p><span class="font-semibold">Subject:</span> {{ event.extendedProps?.subject || event.title }}</p>
            <p v-if="event.extendedProps?.building" class="mt-1">
              <span class="font-semibold">Building:</span> {{ event.extendedProps.building }}
            </p>
            <p v-if="event.extendedProps?.college" class="mt-1">
              <span class="font-semibold">College/Office:</span> {{ event.extendedProps.college }}
            </p>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8">
        <svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <p class="mt-4 text-lg font-medium text-gray-600">No appointments scheduled for this day</p>

      </div>

      <div class="mt-6 flex justify-end">
        <button @click="$emit('close')" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
          Close
        </button>
      </div>
    </div>
  </div>
</template>
