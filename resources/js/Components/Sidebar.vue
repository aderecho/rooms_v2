<template>
  <aside v-show="sidebarOpen" id="sidebar"
    class="w-64 bg-white text-black h-screen p-4 flex flex-col transition-all duration-300 z-10">
    <div class="text-center mb-6">
      <p class="mt-3 font-semibold text-xl text-[#00b3ff] "><a href="/MainDashboard">Dashboard</a></p>
    </div>

    <nav class="space-y-1 text-sm overflow-y-auto">

      <div v-if="canAccessBuildings" @click="toggleMenu('Building')"
        class="flex justify-between items-center cursor-pointer px-4 py-2 rounded transition duration-150" :class="{
          'bg-gray-100 font-semibold text-gray-800': isBuildingOpen,
          'text-black hover:bg-[#9c1b33] hover:text-white': !isBuildingOpen
        }">
        <span>Building & Equip.</span>
        <svg :class="{ 'rotate-90': isBuildingOpen }" class="w-4 h-4 transform transition-transform duration-300"
          fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
      </div>

      <div v-show="isBuildingOpen" class="pl-4 space-y-1 transition-all duration-300 overflow-hidden">
        <a href="/BuildingDashboard"
          class="block px-4 py-2 hover:bg-[#9c1b33] hover:text-white rounded transition duration-150">Building</a>
        <a href="/equipment"
          class="block px-4 py-2 hover:bg-[#9c1b33] hover:text-white rounded transition duration-150">Equipment</a>
      </div>

      <hr v-if="canAccessBuildings && canAccessCollege" class="border-gray-100 my-1">

      <div v-if="canAccessCollege" @click="toggleMenu('College')"
        class="flex justify-between items-center cursor-pointer px-4 py-2 rounded transition duration-150" :class="{
          'bg-gray-100 font-semibold text-gray-800': isCollegeOpen,
          'text-black hover:bg-[#9c1b33] hover:text-white': !isCollegeOpen
        }">
        <span>College & Dept.</span>
        <svg :class="{ 'rotate-90': isCollegeOpen }" class="w-4 h-4 transform transition-transform duration-300"
          fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
      </div>

      <div v-if="canAccessCollege" v-show="isCollegeOpen" class="pl-4 space-y-1 transition-all duration-300 overflow-hidden">
        <a href="/CollegeDashboard"
          :class="isActive('/CollegeDashboard') ? 'bg-[#9c1b33] text-white font-semibold' : 'hover:bg-[#9c1b33] hover:text-white'"
          class="block px-4 py-2 rounded transition duration-150">College</a>
        <a href="/Departments"
          :class="isActive('/Departments') ? 'bg-[#9c1b33] text-white font-semibold' : 'hover:bg-[#9c1b33] hover:text-white'"
          class="block px-4 py-2 rounded transition duration-150">Departments</a>
      </div>

      <hr v-if="canAccessCollege && canAccessRooms" class="border-gray-100 my-1">

      <div v-if="canAccessRooms" @click="toggleMenu('Rooms')"
        class="flex justify-between items-center cursor-pointer px-4 py-2 rounded transition duration-150" :class="{
          'bg-gray-100 font-semibold text-gray-800': isRoomsOpen,
          'text-black hover:bg-[#9c1b33] hover:text-white': !isRoomsOpen
        }">
        <span>Room Management</span>
        <svg :class="{ 'rotate-90': isRoomsOpen }" class="w-4 h-4 transform transition-transform duration-300" fill="none"
          stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
      </div>

      <div v-if="canAccessRooms" v-show="isRoomsOpen" class="pl-4 space-y-1 transition-all duration-300 overflow-hidden">
        <a href="/Rooms"
          :class="isActive('/Rooms') ? 'bg-[#9c1b33] text-white font-semibold' : 'hover:bg-[#9c1b33] hover:text-white'"
          class="block px-4 py-2 rounded transition duration-150">Rooms</a>
        <a href="/RoomTypes"
          :class="isActive('/RoomTypes') ? 'bg-[#9c1b33] text-white font-semibold' : 'hover:bg-[#9c1b33] hover:text-white'"
          class="block px-4 py-2 rounded transition duration-150">Room Types</a>
        <!-- <a href="/Rooms"
          class="block px-4 py-2 hover:bg-[#9c1b33] hover:text-white rounded transition duration-150">Rooms</a>
        <a href="/RoomTypes"
          class="block px-4 py-2 hover:bg-[#9c1b33] hover:text-white rounded transition duration-150">Room Types</a> -->
      </div>

      <hr v-if="canAccessRooms && (canAccessUsers || canAccessSchedule || canAccessTerms)" class="border-gray-200 my-2">

      <a v-if="canAccessUsers" href="/UserAccountPage"
        class="block px-4 py-2 text-black hover:bg-[#9c1b33] hover:text-white rounded transition duration-150">User
        Account</a>
      <a v-if="canAccessSchedule" href="/Schedule"
        class="block px-4 py-2 text-black hover:bg-[#9c1b33] hover:text-white rounded transition duration-150">Schedules</a>
      <hr v-if="canAccessTerms && (canAccessUsers || canAccessSchedule)" class="border-gray-100 my-1">
      <a v-if="canAccessTerms" href="/Terms"
        class="block px-4 py-2 text-black hover:bg-[#9c1b33] hover:text-white rounded transition duration-150">Terms</a>

      <!-- Logout Link Added Below Terms -->
      <form @submit.prevent="logout">
        <button type="submit"
          class="w-full text-left block px-4 py-2 text-black hover:bg-[#9c1b33] hover:text-white rounded transition duration-150">
          Logout
        </button>
      </form>

    </nav>
  </aside>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  sidebarOpen: {
    type: Boolean,
    default: true
  },
  user: {
    type: Object,
    default: null
  }
});

const page = usePage();

const isActive = (path) => page.url.split(/[?#]/)[0] === path;

const currentUser = computed(() => props.user ?? page.props.auth?.user ?? null);

const isAdmin = computed(() => currentUser.value?.role === 'admin');
const permissions = computed(() => currentUser.value?.permissions || []);

const hasPermission = (permission) => {
  return isAdmin.value || permissions.value.includes(permission);
};

const canAccessBuildings = computed(() =>
  isAdmin.value || hasPermission('Staff Work') || hasPermission('Can Edit')
);
const canAccessCollege = computed(() =>
  isAdmin.value || hasPermission('Can Edit')
);
const canAccessRooms = computed(() =>
  isAdmin.value || hasPermission('Can Edit') || hasPermission('Can Book')
);
const canAccessUsers = computed(() => isAdmin.value);
const canAccessSchedule = computed(() =>
  isAdmin.value || hasPermission('Can Book') || hasPermission('Can Approve') || hasPermission('Staff Work')
);
const canAccessTerms = computed(() =>
  isAdmin.value || hasPermission('Can Edit')
);

// --- REACTIVE STATE ---

/**
 * Reactive variables (refs) controlling the open/closed state of each accordion menu.
 * They are initialized to 'false' (closed) to ensure no menu is automatically open on page load.
 */
const isBuildingOpen = ref(false);
const isCollegeOpen = ref(false);
const isRoomsOpen = ref(false);

// --- FUNCTIONS ---

/**
 * Toggles the state of the clicked menu, implementing **Accordion Logic**.
 * It ensures that only one menu is open at any time by closing the others.
 * * @param {('Building'|'College'|'Rooms')} menuName - The identifier for the menu to toggle.
 */
const toggleMenu = (menuName) => {
  // Map the string name to the corresponding ref variable for dynamic access
  const menuState = {
    Building: isBuildingOpen,
    College: isCollegeOpen,
    Rooms: isRoomsOpen,
  };

  const currentState = menuState[menuName].value;

  // 1. If the current menu is already open, click-to-close it (toggle effect).
  if (currentState) {
    menuState[menuName].value = false;
  } else {
    // 2. Close all other menus first (The "Accordion" rule: max one open).
    isBuildingOpen.value = false;
    isCollegeOpen.value = false;
    isRoomsOpen.value = false;

    // 3. Open the newly clicked menu.
    menuState[menuName].value = true;
  }
};

/**
 * Implements **URL Persistence Logic** by checking the browser's current path
 * and automatically opening the relevant parent menu on component mount.
 * This keeps the menu "standing by" (expanded) after a navigation/page reload.
 */
const setInitialMenuState = () => {
  // Map sub-routes to their corresponding parent menu state ref
  const pathMap = {
    Building: ['/BuildingDashboard', '/equipment'],
    College: ['/CollegeDashboard', '/Departments'],
    Rooms: ['/Rooms', '/RoomTypes'],
  };

  const currentPath = window.location.pathname;

  // Check the current path against the defined map and set the corresponding state to true
  if (pathMap.Building.includes(currentPath)) {
    isBuildingOpen.value = true;
  } else if (pathMap.College.includes(currentPath)) {
    isCollegeOpen.value = true;
  } else if (pathMap.Rooms.includes(currentPath)) {
    isRoomsOpen.value = true;
  }
};

// Logout function
const logout = () => {
  router.post('/logout');
};

// --- LIFECYCLE HOOKS ---
/**
 * `onMounted` hook: Executes the persistence logic immediately after the component is added to the DOM.
 */
onMounted(() => {
  setInitialMenuState();
});
</script>
