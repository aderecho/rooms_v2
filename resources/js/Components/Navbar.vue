<template>
  <header class="fixed top-0 left-0 right-0 z-50 bg-[#7A0C23] text-white shadow-md">
    <div class="flex items-center justify-between h-14 px-6">
      <!-- Left -->
      <div class="flex items-center space-x-3">
        <button @click="$emit('toggleSidebar')" class="focus:outline-none">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <Link href="/MainDashboard">
          <img src="/image/uplogo.png" alt="logo" class="h-9 w-9" />
        </Link>
        <span class="text-lg font-semibold">UPCEBU</span>
      </div>

      <!-- Right -->
      <AppointmentNotificationBell />
    </div>
  </header>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppointmentNotificationBell from '@/Components/AppointmentNotificationBell.vue'

defineProps({
  user: {
    type: Object,
    default: () => ({})
  },
});
</script>
