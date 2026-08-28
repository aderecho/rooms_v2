<template>
  <aside
    v-show="sidebarOpen"
    id="sidebar"
    class="admin-sidebar z-20 flex min-h-full flex-col text-white transition-all duration-300 lg:self-stretch"
  >
    <div class="sidebar-brand">
      <span class="sidebar-logo-wrap"><img src="/image/uplogo.png" alt="University of the Philippines seal" /></span>
      <strong>{{ isStudent ? 'ROOM RESERVATIONS' : 'ROOMS ADMIN' }}</strong>
      <small>Campus Space Management</small>
    </div>

    <div class="sidebar-section-label">Operations</div>
    <nav class="sidebar-nav text-sm">
      <Link
        v-for="item in primaryItems"
        :key="item.label"
        :href="item.href"
        view-transition
        prefetch="hover"
        class="sidebar-item"
        :class="{ active: item.active }"
        :aria-current="item.active ? 'page' : undefined"
      >
        <span class="sidebar-icon" v-html="item.icon"></span>
        <span class="sidebar-label">{{ item.label }}</span>
        <span
          v-if="item.badge"
          class="min-w-6 rounded-full bg-red-500 px-1.5 py-0.5 text-center text-[10px] font-extrabold text-white"
        >{{ item.badge > 99 ? '99+' : item.badge }}</span>
      </Link>
    </nav>

    <div v-if="!isStudent" class="sidebar-section-label">Administration</div>
    <nav v-if="!isStudent" class="sidebar-nav text-sm">
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
        <Link
          href="/SamlIntegration"
          view-transition
          prefetch="hover"
          class="sidebar-subitem"
          :class="{ active: isActive(['/SamlIntegration']) }"
        >
          SAML Config
        </Link>
        </div>
      </Transition>

      <form class="sidebar-logout" @submit.prevent="logout">
        <button type="submit" class="sidebar-item w-full text-left">
          <span class="sidebar-icon" v-html="icons.logout"></span>
          <span class="sidebar-label">Logout</span>
        </button>
      </form>
    </nav>
    <form v-else class="sidebar-logout mt-auto" @submit.prevent="logout">
      <button type="submit" class="sidebar-item w-full text-left">
        <span class="sidebar-icon" v-html="icons.logout"></span>
        <span class="sidebar-label">Logout</span>
      </button>
    </form>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
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
const currentUser = computed(() => props.user || page.props.auth?.user || {});
const isStudent = computed(() => String(currentUser.value?.role || '').toLowerCase() === 'student');
const isAdmin = computed(() => String(currentUser.value?.role || '').toLowerCase() === 'admin');
const pendingReservationCount = computed(() => Number(page.props.reservationNotifications?.pendingAdminCount || 0));

const isActive = (paths) => paths.includes(currentPath.value);

const icons = {
  dashboard: '<svg viewBox="0 0 24 24" fill="none"><path d="M4 5h6v6H4V5Zm10 0h6v6h-6V5ZM4 13h6v6H4v-6Zm10 0h6v6h-6v-6Z" stroke="currentColor" stroke-width="1.7"/></svg>',
  building: '<svg viewBox="0 0 24 24" fill="none"><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16" stroke="currentColor" stroke-width="1.7"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M4 21h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  rooms: '<svg viewBox="0 0 24 24" fill="none"><path d="M4 20V8l8-4 8 4v12H4Z" stroke="currentColor" stroke-width="1.7"/><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.7"/></svg>',
  equipment: '<svg viewBox="0 0 24 24" fill="none"><path d="M6 7h12v14H6V7Z" stroke="currentColor" stroke-width="1.7"/><path d="M9 7V4h6v3M9 11h6M9 15h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  calendar: '<svg viewBox="0 0 24 24" fill="none"><path d="M7 3v4M17 3v4M4 9h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/></svg>',
  request: '<svg viewBox="0 0 24 24" fill="none"><path d="M6 3h9l3 3v15H6V3Z" stroke="currentColor" stroke-width="1.7"/><path d="M9 11h6M9 15h6M9 7h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  upload: '<svg viewBox="0 0 24 24" fill="none"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v5h14v-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  report: '<svg viewBox="0 0 24 24" fill="none"><path d="M6 3h9l4 4v14H6V3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M15 3v5h4M9 12h7M9 16h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  analytics: '<svg viewBox="0 0 24 24" fill="none"><path d="M5 19V9m7 10V5m7 14v-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  team: '<svg viewBox="0 0 24 24" fill="none"><path d="M16 19a4 4 0 0 0-8 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6.5 7a3 3 0 0 0-3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  settings: '<svg viewBox="0 0 24 24" fill="none"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.7"/><path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1.5-1.5M5.5 18.5 7 17M17 17l1.5 1.5M5.5 5.5 7 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
  logout: '<svg viewBox="0 0 24 24" fill="none"><path d="M10 6H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4M15 8l4 4-4 4M19 12H9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
};

const adminItems = computed(() => [
  {
    label: 'Reservation Requests',
    href: '/ReservationRequests',
    icon: icons.request,
    active: currentPath.value.startsWith('/ReservationRequests'),
    badge: pendingReservationCount.value,
  },
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
    label: 'Import Schedules',
    href: '/Schedule/Import',
    icon: icons.upload,
    active: isActive(['/Schedule/Import']),
  },
  {
    label: 'Schedule Report',
    href: '/Reports/Schedule',
    icon: icons.report,
    active: isActive(['/Reports/Schedule']),
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

const primaryItems = computed(() => {
  if (isStudent.value) {
    return [{
      label: 'My Reservations',
      href: '/MyReservations',
      icon: icons.request,
      active: currentPath.value.startsWith('/MyReservations'),
    }];
  }

  return adminItems.value.filter((item) => isAdmin.value || item.label !== 'Reservation Requests');
});

const logout = () => {
  router.post('/logout');
};
</script>

<style scoped>
.admin-sidebar {
  view-transition-name: admin-sidebar;
  width: 250px;
  min-width: 250px;
  height: calc(100dvh - 4.5rem) !important;
  min-height: calc(100dvh - 4.5rem) !important;
  max-height: calc(100dvh - 4.5rem);
  padding: 1.25rem 0.85rem 1rem;
  overflow-y: auto;
  overscroll-behavior: contain;
  background: radial-gradient(circle at 16% 92%, rgba(217,155,34,.12), transparent 24%), linear-gradient(180deg,#003d2d 0%,#005740 58%,#003f30 100%);
  border-right: 1px solid rgba(255,255,255,.12);
  box-shadow: 12px 0 34px rgba(0,61,45,.18);
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.25) transparent;
}
.sidebar-brand{display:grid;justify-items:center;padding:.1rem .55rem 1.15rem;border-bottom:1px solid rgba(226,190,112,.25);text-align:center}
.sidebar-logo-wrap{display:grid;width:4.9rem;height:4.9rem;place-items:center;border:1px solid rgba(236,196,104,.55);border-radius:1.15rem;background:rgba(255,255,255,.08);box-shadow:0 12px 25px rgba(0,35,25,.3),inset 0 1px rgba(255,255,255,.14)}
.sidebar-logo-wrap img{width:4.1rem;height:4.1rem;object-fit:contain}.sidebar-brand strong{margin-top:.65rem;color:#f2ca70;font-size:.69rem;letter-spacing:.18em}.sidebar-brand small{margin-top:.2rem;color:rgba(255,255,255,.7);font-size:.55rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.sidebar-section-label{margin:1rem .55rem .45rem;color:#e2b85f;font-size:.57rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.sidebar-nav{display:grid;gap:.28rem}

.sidebar-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 2.62rem;
  border: 1px solid transparent;
  border-radius: 0.62rem;
  padding: 0.58rem 0.62rem;
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
  background: #edc66e;
  opacity: 0;
  transform: translateY(-50%) scaleY(0.35);
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.sidebar-item:hover,
.sidebar-item:focus-visible,
.sidebar-item.active {
  color: #ffffff;
  border-color: rgba(255,255,255,.15);
  background: linear-gradient(100deg,rgba(255,255,255,.18),rgba(255,255,255,.09));
  box-shadow: inset 3px 0 #edc66e,0 7px 16px rgba(0,39,28,.12);
  transform: translateX(0.08rem);
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

.sidebar-nav:last-of-type{display:flex;min-height:8rem;flex:1;flex-direction:column}.sidebar-logout{margin-top:auto;border-top:1px solid rgba(255,255,255,.12);padding-top:.7rem}

@media (min-width: 1024px) {
  .admin-sidebar {
    height: 100dvh !important;
    min-height: 100dvh !important;
    max-height: 100dvh;
  }
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
