<template>
  <aside
    v-show="sidebarOpen"
    id="sidebar"
    class="z-20 flex min-h-screen flex-col bg-[#005740] px-6 py-7 text-white shadow-[12px_0_34px_rgba(0,87,64,0.18)] transition-all duration-300 lg:self-stretch"
    style="width: 245px; min-width: 245px;"
  >
    <nav class="space-y-2 text-sm">
      <a
        v-for="item in primaryItems"
        :key="item.label"
        :href="item.href"
        class="sidebar-item"
        :class="{ active: item.active }"
        :aria-current="item.active ? 'page' : undefined"
      >
        <span class="sidebar-icon" v-html="item.icon"></span>
        <span class="sidebar-label">{{ item.label }}</span>
      </a>
    </nav>

    <div class="mt-10 text-xs font-semibold uppercase tracking-[0.12em] text-white/50">General</div>
    <nav class="mt-4 space-y-2 text-sm">
      <button
        type="button"
        class="sidebar-item sidebar-toggle w-full text-left"
        :class="{ active: settingsOpen || isActive(['/SamlIntegration']) }"
        :aria-expanded="settingsOpen"
        @click="settingsOpen = !settingsOpen"
      >
        <span class="sidebar-icon" v-html="icons.settings"></span>
        <span class="sidebar-label">Settings</span>
        <svg class="sidebar-chevron" :class="{ open: settingsOpen }" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <Transition name="submenu">
        <div v-show="settingsOpen" class="ml-4 space-y-1 border-l border-white/15 pl-3">
        <a
          href="/SamlIntegration"
          class="sidebar-subitem"
          :class="{ active: isActive(['/SamlIntegration']) }"
        >
          SAML Config
        </a>
        </div>
      </Transition>

      <form @submit.prevent="logout">
        <button type="submit" class="sidebar-item w-full text-left">
          <span class="sidebar-icon" v-html="icons.logout"></span>
          <span class="sidebar-label">Logout</span>
        </button>
      </form>
    </nav>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

defineProps({
  sidebarOpen: {
    type: Boolean,
    default: true,
  },
  user: {
    type: Object,
    default: null,
  },
});

const page = usePage();
const currentPath = computed(() => page.url.split(/[?#]/)[0]);
const settingsOpen = ref(['/SamlIntegration'].includes(currentPath.value));

const isActive = (paths) => paths.includes(currentPath.value);

const icons = {
  dashboard: '<svg viewBox="0 0 24 24" fill="none"><path d="M4 5h6v6H4V5Zm10 0h6v6h-6V5ZM4 13h6v6H4v-6Zm10 0h6v6h-6v-6Z" stroke="currentColor" stroke-width="1.7"/></svg>',
  building: '<svg viewBox="0 0 24 24" fill="none"><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16" stroke="currentColor" stroke-width="1.7"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M4 21h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  rooms: '<svg viewBox="0 0 24 24" fill="none"><path d="M4 20V8l8-4 8 4v12H4Z" stroke="currentColor" stroke-width="1.7"/><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.7"/></svg>',
  equipment: '<svg viewBox="0 0 24 24" fill="none"><path d="M6 7h12v14H6V7Z" stroke="currentColor" stroke-width="1.7"/><path d="M9 7V4h6v3M9 11h6M9 15h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  calendar: '<svg viewBox="0 0 24 24" fill="none"><path d="M7 3v4M17 3v4M4 9h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/></svg>',
  analytics: '<svg viewBox="0 0 24 24" fill="none"><path d="M5 19V9m7 10V5m7 14v-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  team: '<svg viewBox="0 0 24 24" fill="none"><path d="M16 19a4 4 0 0 0-8 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6.5 7a3 3 0 0 0-3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  settings: '<svg viewBox="0 0 24 24" fill="none"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.7"/><path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1.5-1.5M5.5 18.5 7 17M17 17l1.5 1.5M5.5 5.5 7 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  logout: '<svg viewBox="0 0 24 24" fill="none"><path d="M10 6H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4M15 8l4 4-4 4M19 12H9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
};

const primaryItems = computed(() => [
  {
    label: 'Dashboard',
    href: '/MainDashboard',
    icon: icons.dashboard,
    active: isActive(['/MainDashboard']),
  },
  {
    label: 'Buildings & Rooms',
    href: '/BuildingDashboard',
    icon: icons.rooms,
    active: isActive(['/BuildingDashboard', '/Rooms', '/RoomTypes']),
  },
  {
    label: 'Calendar',
    href: '/Schedule',
    icon: icons.calendar,
    active: isActive(['/Schedule']),
  },
  {
    label: 'Analytics',
    href: '/Analytics',
    icon: icons.analytics,
    active: isActive(['/Analytics']),
  },
  {
    label: 'Team',
    href: '/UserAccountPage',
    icon: icons.team,
    active: isActive(['/UserAccountPage']),
  },
]);

const logout = () => {
  router.post('/logout');
};
</script>

<style scoped>
.sidebar-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 2.75rem;
  border-radius: 0.75rem;
  padding: 0.68rem 0.85rem;
  color: rgba(255, 255, 255, 0.78);
  outline: none;
  overflow: hidden;
  transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}

.sidebar-item::before {
  content: '';
  position: absolute;
  left: 0.35rem;
  top: 50%;
  width: 0.2rem;
  height: 1.35rem;
  border-radius: 999px;
  background: #d9f99d;
  opacity: 0;
  transform: translateY(-50%) scaleY(0.35);
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.sidebar-item:hover,
.sidebar-item:focus-visible,
.sidebar-item.active {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.14);
  transform: translateX(0.18rem);
}

.sidebar-item:active {
  transform: translateX(0.18rem) scale(0.99);
}

.sidebar-item:hover .sidebar-icon,
.sidebar-item:focus-visible .sidebar-icon,
.sidebar-item.active .sidebar-icon {
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
}

.sidebar-item.active::before {
  opacity: 1;
  transform: translateY(-50%) scaleY(1);
}

.sidebar-subitem {
  display: flex;
  align-items: center;
  border-radius: 0.6rem;
  padding: 0.52rem 0.75rem;
  color: rgba(255, 255, 255, 0.72);
  transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
}

.sidebar-subitem:hover,
.sidebar-subitem:focus-visible,
.sidebar-subitem.active {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.1);
  transform: translateX(0.15rem);
}

.sidebar-icon {
  display: grid;
  height: 1.35rem;
  width: 1.35rem;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 0.5rem;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.sidebar-label {
  min-width: 0;
  flex: 1;
}

.sidebar-toggle {
  justify-content: flex-start;
}

.sidebar-chevron {
  height: 1rem;
  width: 1rem;
  flex: 0 0 auto;
  color: rgba(255, 255, 255, 0.72);
  transition: transform 0.2s ease;
}

.sidebar-chevron.open {
  transform: rotate(90deg);
}

.sidebar-icon :deep(svg) {
  height: 1.1rem;
  width: 1.1rem;
}

.submenu-enter-active,
.submenu-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.submenu-enter-from,
.submenu-leave-to {
  opacity: 0;
  transform: translateY(-0.25rem);
}
</style>
