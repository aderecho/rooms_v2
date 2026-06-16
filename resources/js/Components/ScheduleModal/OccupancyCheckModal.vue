<script setup>
import { formatDateDisplay, formatTimeDisplay } from './utils/scheduleHelpers';

defineProps({
  isVisible: Boolean,
  data: Object
});

defineEmits(['close', 'proceed']);
</script>

<template>
  <div v-if="isVisible" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl p-6 max-h-screen overflow-y-auto">
      <div class="flex justify-between items-center mb-4 sticky top-0 bg-white z-10 p-1 -m-1">
        <h3 class="text-xl font-bold">Room Occupancy Check</h3>
        <button @click="$emit('close')" class="text-xl font-semibold">X</button>
      </div>

      <!-- Room Info -->
      <div class="mb-6">
        <div class="bg-blue-50 p-4 rounded-lg">
          <h4 class="font-bold text-lg text-blue-800 mb-2">
            Room: {{ data?.room }}
          </h4>
          <p class="text-blue-700">
            Date: {{ data?.date ? formatDateDisplay(data.date) : '' }}
          </p>
          <p v-if="data?.selectedHour !== null" class="text-blue-700">
            Selected Time: {{ data?.selectedHour }}:{{ data?.selectedMinute?.toString().padStart(2, '0') || '00' }}
          </p>
        </div>
      </div>

      <!-- Occupied Slots -->
      <div class="mb-6">
        <h4 class="font-bold text-lg text-red-700 mb-3">Occupied Time Slots</h4>
        <div v-if="data?.existingEvents?.length > 0" class="space-y-2">
          <div v-for="event in data.existingEvents" :key="event.id"
               class="bg-red-50 border border-red-200 rounded-lg p-3">
            <div class="flex justify-between items-center">
              <div>
                <h5 class="font-semibold text-red-800">{{ event.title }}</h5>
                <p class="text-sm text-red-600">
                  {{ formatTimeDisplay(event.start) }} - {{ event.end ? formatTimeDisplay(event.end) : 'N/A' }}
                </p>
                <p class="text-xs text-red-500 mt-1">
                  {{ event.extendedProps?.subject || event.title }}
                </p>
              </div>
              <span class="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm font-medium">
                {{ event.extendedProps?.type || event.list }}
              </span>
            </div>
          </div>
        </div>
        <div v-else class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
          <p class="text-green-700 font-medium">No occupied time slots for this room on this date.</p>
        </div>
      </div>

      <!-- Available Slots -->
      <div class="mb-6">
        <h4 class="font-bold text-lg text-green-700 mb-3">Available Time Slots</h4>
        <div v-if="data?.availableSlots?.length > 0">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <button
              v-for="(slot, index) in data.availableSlots"
              :key="index"
              @click="$emit('proceed', slot)"
              class="bg-green-50 border-2 border-green-200 hover:border-green-500 hover:bg-green-100 rounded-lg p-4 text-center transition-colors"
            >
              <div class="font-bold text-green-800">{{ slot.display }}</div>
              <div class="text-sm text-green-600 mt-1">1 hour slot</div>
            </button>
          </div>
          <p class="text-sm text-gray-500 mt-3">Click on an available time slot to proceed</p>
        </div>
        <div v-else class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
          <p class="text-yellow-700 font-medium">No available time slots for this room on this date.</p>
          <p class="text-sm text-yellow-600 mt-1">Please select a different date or room.</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between">
        <button @click="$emit('close')" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
          Cancel
        </button>
        <div class="space-x-3">
          <button
            @click="$emit('proceed')"
            class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600"
            :disabled="!data?.availableSlots?.length"
            :class="{ 'opacity-50 cursor-not-allowed': !data?.availableSlots?.length }"
          >
            Proceed Anyway
          </button>
          <button
            @click="$emit('proceed', data?.availableSlots?.[0])"
            v-if="data?.availableSlots?.length > 0"
            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
          >
            Use First Available Slot
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
