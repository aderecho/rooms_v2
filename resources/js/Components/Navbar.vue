<template>
  <header class="admin-topbar fixed left-0 right-0 top-0 z-50 text-white lg:left-[250px]">
    <div class="flex h-[72px] items-center justify-between gap-4 px-4 sm:px-7">
      <div class="flex min-w-0 flex-1 items-center gap-3">
        <button
          @click="emitToggle"
          class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-white/20 bg-white/10 text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40 lg:hidden"
          aria-label="Toggle sidebar"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-current" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <form class="relative w-full max-w-[420px]" @submit.prevent="submitSearch">
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Search rooms, college, location..."
            class="h-11 w-full rounded-full border border-white/25 bg-white/95 py-0 pl-12 pr-14 text-sm text-slate-700 outline-none transition placeholder:text-slate-500 focus:bg-white focus:ring-4 focus:ring-white/20"
          />
          <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#005740]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m21 21-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <span class="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 rounded-md bg-[#005740]/8 px-2 py-1 text-[10px] font-bold text-[#005740] sm:block">⌘F</span>
        </form>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <button type="button" class="navbar-icon-button" aria-label="Messages">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 6h16v12H4V6Zm1.5 1.5 6.5 5 6.5-5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
          </svg>
        </button>
        <AppointmentNotificationBell />
        <div class="topbar-profile flex items-center gap-3 rounded-full py-1.5 pl-4 pr-1.5">
          <div class="grid h-10 w-10 place-items-center rounded-full border-2 border-white/75 bg-white text-sm font-bold text-[#005740]">
            {{ userInitial }}
          </div>
          <div class="hidden leading-tight md:block">
            <p class="text-sm font-semibold text-white">{{ displayName }}</p>
            <p class="max-w-[190px] truncate text-[10px] font-semibold uppercase tracking-[0.05em] text-white/65">{{ displayEmail }}</p>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3'
import AppointmentNotificationBell from '@/Components/AppointmentNotificationBell.vue'

const emit = defineEmits(['toggleSidebar', 'toggle-sidebar']);

const props = defineProps({
  user: {
    type: Object,
    default: () => ({})
  },
});

const page = usePage();
const searchQuery = ref('');
const currentUser = computed(() => props.user?.name || props.user?.username ? props.user : page.props.auth?.user || {});
const displayName = computed(() => currentUser.value.name || currentUser.value.full_name || currentUser.value.username || 'System Administrator');
const displayEmail = computed(() => currentUser.value.email || 'admin@upcebu.edu.ph');
const userInitial = computed(() => displayName.value.charAt(0).toUpperCase());

const emitToggle = () => {
  emit('toggleSidebar');
  emit('toggle-sidebar');
};

const submitSearch = () => {
  const query = searchQuery.value.trim();

  if (!query) return;

  window.dispatchEvent(new CustomEvent('global-room-search', { detail: { query } }));
  router.visit(`/MainDashboard?search=${encodeURIComponent(query)}`, {
    preserveState: true,
    preserveScroll: false,
    viewTransition: true,
  });
};
</script>

<style scoped>
.admin-topbar{view-transition-name:admin-topbar;border-bottom:1px solid rgba(255,255,255,.13);background:linear-gradient(100deg,rgba(0,61,45,.99),rgba(0,98,72,.97));box-shadow:0 10px 30px rgba(0,61,45,.16);backdrop-filter:blur(18px)}
.topbar-profile{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.09);box-shadow:inset 0 1px rgba(255,255,255,.1)}
.navbar-icon-button {
  display: grid;
  height: 2.5rem;
  width: 2.5rem;
  place-items: center;
  border: 1px solid rgba(255,255,255,.16);
  border-radius: 0.65rem;
  background: rgba(255,255,255,.1);
  color: #ffffff;
  transition: transform 0.2s ease, background-color 0.2s ease;
}

.navbar-icon-button:hover {
  background: rgba(255,255,255,.18);
  transform: translateY(-1px);
}

.navbar-icon-button svg {
  height: 1.1rem;
  width: 1.1rem;
}
</style>
