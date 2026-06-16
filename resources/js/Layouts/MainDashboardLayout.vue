<script setup>
import { ref, onMounted, onUnmounted, computed, watchEffect } from "vue";
import { usePage, router } from '@inertiajs/vue3';
import Navbar from "@/Components/Navbar.vue";
import Sidebar from "@/Components/Sidebar.vue";
import IconButton from "@/Components/IconButton.vue";
import MessageFunction from "@/Components/MessageFunction.vue";

// --- LAYOUT & SIDEBAR LOGIC ---

// State for mobile sidebar open/closed status.
const sidebarOpen = ref(false);
// State for desktop sidebar: true = forced open (visible), false = forced closed (hidden).
const sidebarForcedOpen = ref(true);
// Tracks window width for responsiveness.
const windowWidth = ref(window.innerWidth);

/**
 * Computed property to determine if the screen is desktop size (>= 768px).
 */
const isDesktop = computed(() => windowWidth.value >= 768);

/**
 * Toggles the sidebar state based on the screen size (mobile vs. desktop).
 */
const toggleSidebar = () => {
    if (isDesktop.value) {
        sidebarForcedOpen.value = !sidebarForcedOpen.value;
    } else {
        sidebarOpen.value = !sidebarOpen.value;
    }
};

/**
 * Updates the window width on resize event.
 */
const updateWindowWidth = () => {
    windowWidth.value = window.innerWidth;
};

/**
 * Ensures the mobile sidebar is closed when transitioning to desktop view.
 */
watchEffect(() => {
    if (isDesktop.value && sidebarOpen.value) {
        sidebarOpen.value = false;
    }
});

// Setup and teardown of event listeners.
onMounted(() => {
    window.addEventListener('resize', updateWindowWidth);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateWindowWidth);
});

// --- INERTIA PROPS & DATA ACCESS ---

// Define dashboard count props passed from the controller.
defineProps({
    totalAccounts: { type: Number, default: 0 },
    totalDepartments: { type: Number, default: 0 },
    totalColleges: { type: Number, default: 0 },
    totalRooms: { type: Number, default: 0 },
    inventoryitems: { type: Object, required: true },
});

const page = usePage();

/**
 * Computed property to safely access the paginated 'rooms' data.
 */
const rooms = computed(() => page.props.rooms);

// --- SEARCH FUNCTIONALITY ---
const searchQuery = ref('');

/**
 * Computed property that filters rooms based on search query across all visible fields
 */
const filteredRooms = computed(() => {
    if (!rooms.value || !rooms.value.data) return [];

    if (!searchQuery.value.trim()) {
        return rooms.value.data;
    }

    const query = searchQuery.value.toLowerCase().trim();

    return rooms.value.data.filter(room => {
        // Search in all available fields
        return (
            // Room name/subject
            (room.room_name && room.room_name.toLowerCase().includes(query)) ||

            // College name
            (room.college?.college_name && room.college.college_name.toLowerCase().includes(query)) ||

            // User account/username
            (room.user_account?.username && room.user_account.username.toLowerCase().includes(query)) ||

            // Location/building
            (room.location && room.location.toLowerCase().includes(query)) ||

            // Faculty (placeholder data)
            ('Faculty Data'.toLowerCase().includes(query)) ||

            // Date field (if exists)
            (room.date && room.date.toLowerCase().includes(query)) ||

            // Time field (if exists)
            (room.time && room.time.toLowerCase().includes(query)) ||

            // Building field (if separate from location)
            (room.building && room.building.toLowerCase().includes(query)) ||

            // Additional fields from schedules
            (room.schedules && room.schedules.some(schedule =>
                (schedule.cfic_id && schedule.cfic_id.toLowerCase().includes(query)) ||
                (schedule.course_name && schedule.course_name.toLowerCase().includes(query)) ||
                (schedule.day && schedule.day.toLowerCase().includes(query)) ||
                (schedule.start_time && schedule.start_time.toLowerCase().includes(query)) ||
                (schedule.end_time && schedule.end_time.toLowerCase().includes(query))
            ))
        );
    });
});

/**
 * Computed property for pagination with filtered data
 */
const paginatedRooms = computed(() => {
    if (!rooms.value) return {};

    // Return filtered rooms count for pagination display
    return {
        ...rooms.value,
        data: filteredRooms.value,
        total: filteredRooms.value.length,
        from: 1,
        to: filteredRooms.value.length
    };
});

// --- MESSAGE FUNCTION STATE ---
const showCreateSuccess = ref(false);
const showEditSuccess = ref(false);
const showDeleteSuccess = ref(false);
const showError = ref(false);
const showInfo = ref(false);
const deletedRoomName = ref('');
const errorMessage = ref('');
const infoMessage = ref('');

// --- TOAST TRIGGER FUNCTIONS ---
const triggerToast = (type, data = {}) => {
    // Reset all toast states
    showCreateSuccess.value = false;
    showEditSuccess.value = false;
    showDeleteSuccess.value = false;
    showError.value = false;
    showInfo.value = false;

    switch (type) {
        case 'create':
            showCreateSuccess.value = true;
            break;
        case 'edit':
            showEditSuccess.value = true;
            break;
        case 'delete':
            showDeleteSuccess.value = true;
            deletedRoomName.value = data.name || '';
            break;
        case 'error':
            showError.value = true;
            errorMessage.value = data.message || 'An error occurred!';
            break;
        case 'info':
            showInfo.value = true;
            infoMessage.value = data.message || '';
            break;
    }

    // Auto-hide after 5 seconds
    setTimeout(() => {
        showCreateSuccess.value = false;
        showEditSuccess.value = false;
        showDeleteSuccess.value = false;
        showError.value = false;
        showInfo.value = false;
    }, 5000);
};

// --- TABLE ACTION HANDLERS ---

// State to control the visibility of the details modal.
const isDetailsModalVisible = ref(false);
// State to hold the data of the room/schedule currently being viewed.
const currentViewedDetails = ref(null);

/**
 * Handles the click event for the 'View Details' (eye) icon.
 * Populates the modal with data and makes it visible.
 * @param {Object} room - The room object containing details.
 */
const handleViewDetails = (room) => {
    currentViewedDetails.value = room || null;
    isDetailsModalVisible.value = true;
};

/**
 * Handles the click event for the 'Edit Room' (pen) icon.
 * (Placeholder - This function should navigate to an edit form or open an edit modal).
 * @param {Object} room - The room object to edit.
 */
const handleEditRoom = (room) => {
    console.log("Editing room:", room.id);
    // Show edit success toast
    triggerToast('edit', { message: `Room "${room.room_name || 'Unnamed'}" updated successfully!` });
    // Example: router.get(route('rooms.edit', room.id));
};

/**
 * Handles the click event for the 'Delete Room' (trash) icon.
 * (Placeholder - This function should prompt for confirmation and then delete).
 * @param {number} id - The ID of the room to delete.
 */
const handleDeleteRoom = (room) => {
    if (confirm("Are you sure you want to delete this room record?")) {
        console.log("Deleting room with ID:", room.id);

        // Show delete success toast
        triggerToast('delete', {
            name: room.room_name || 'Room',
            message: `Room "${room.room_name || 'Unnamed'}" deleted successfully!`
        });

        // Example: router.delete(route('rooms.destroy', id));
        // For demo purposes, we'll just show the toast
    }
};

/**
 * Function to close the details modal and clear the viewed data.
 */
const closeDetailsModal = () => {
    isDetailsModalVisible.value = false;
    currentViewedDetails.value = null;
};

/**
 * Handles navigation for pagination links using Inertia.
 * @param {string} url - The URL to visit.
 */
const goToPage = (url) => {
    if (!url) return;
    router.visit(url, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

// --- ADD ROOM FUNCTION (Demo) ---
const handleAddRoom = () => {
    // Demo function to show create success toast
    triggerToast('create', { message: 'New room created successfully!' });

    // In real application, this would open a form modal or navigate to create page
    // Example: router.get(route('rooms.create'));
};

// --- SEARCH FUNCTION ---
const handleSearch = () => {
    if (searchQuery.value.trim()) {
        // Show info toast for search with result count
        const resultCount = filteredRooms.value.length;
        const message = resultCount === 0
            ? `No results found for "${searchQuery.value}"`
            : `Found ${resultCount} result(s) for "${searchQuery.value}"`;

        triggerToast('info', { message });

        console.log("Searching for:", searchQuery.value);
        console.log("Found results:", resultCount);
    } else {
        // Show toast when clearing search
        triggerToast('info', { message: 'Showing all room records' });
    }
};

// Clear search function
const clearSearch = () => {
    searchQuery.value = '';
    triggerToast('info', { message: 'Search cleared, showing all records' });
};
</script>

<template>
    <div class="relative min-h-screen">
        <!-- Message Function Toasts -->
        <MessageFunction
            :show-create-success="showCreateSuccess"
            :show-edit-success="showEditSuccess"
            :show-delete-success="showDeleteSuccess"
            :show-error="showError"
            :show-info="showInfo"
            :deleted-room-name="deletedRoomName"
            :error-message="errorMessage"
            :info-message="infoMessage"
            @close-create="showCreateSuccess = false"
            @close-edit="showEditSuccess = false"
            @close-delete="showDeleteSuccess = false"
            @close-error="showError = false"
            @close-info="showInfo = false"
        />

        <Navbar @toggleSidebar="toggleSidebar" :is-mobile-open="sidebarOpen" :is-desktop-open="sidebarForcedOpen"
            :is-desktop="isDesktop" />

        <Sidebar
            :class="['fixed top-14 left-0 h-[calc(100vh-3.5rem)] z-20 transition-all duration-300 bg-white shadow-lg',
                // Desktop Sidebar positioning
                isDesktop ? (sidebarForcedOpen ? 'w-64' : 'w-0 overflow-hidden') :
                // Mobile Sidebar positioning
                (sidebarOpen ? 'w-64 translate-x-0' : '-translate-x-full w-64')]"
            :sidebar-open="sidebarForcedOpen" />

        <transition name="fade">
            <div v-if="sidebarOpen && !isDesktop" class="fixed inset-0 bg-black bg-opacity-50 z-30"
                @click="sidebarOpen = false"></div>
        </transition>

        <div :class="['min-h-screen transition-all duration-300',
            // Content margin based on sidebar state
            isDesktop ? (sidebarForcedOpen ? 'ml-64' : 'ml-0') : 'ml-0']">

            <main id="mainContent" class="flex-1 px-6 py-6 bg-gray-200 pt-20">
                <slot>
                    <!-- Header with Actions -->
                    <div class="text-gray-500 mb-6 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                        <div>
                            <h1 class="text-[#7A0C23] font-bold text-2xl">Dashboard</h1>

                        </div>
 <span class="text-sm text-gray-600 px-3 py-1 rounded-md border ">
                                    UPCEBU &gt; DASHBOARD
                                </span>

                    </div>

                   <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl text-center shadow-lg overflow-hidden">
                            <div class="bg-yellow-500 text-white p-3 font-semibold">Total Accounts</div>
                            <div class="bg-white p-3">
                                <p class="text-3xl font-bold text-gray-800">{{ totalAccounts }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl text-center shadow-lg overflow-hidden">
                            <div class="bg-green-600 text-white p-3 font-semibold">Total Department</div>
                            <div class="bg-white p-3">
                                <p class="text-3xl font-bold text-gray-800">{{ totalDepartments }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl text-center shadow-lg overflow-hidden">
                            <div class="bg-[#800020] text-white p-3 font-semibold">Total Colleges</div>
                            <div class="bg-white p-3">
                                <p class="text-3xl font-bold text-gray-800">{{ totalColleges }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl text-center shadow-lg overflow-hidden">
                            <div class="bg-blue-500 text-white p-3 font-semibold">Total Rooms</div>
                            <div class="bg-white p-3">
                                <p class="text-3xl font-bold text-gray-800">{{ totalRooms }}</p>
                            </div>
                        </div>
                    </div>


                      <div class=" mb-4 flex flex-col sm:flex-row gap-3">
                            <!-- Search Bar with Clear Button -->
                            <div class="relative flex-1">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search by room name, college, location, faculty, etc..."
                                    class="pl-10 pr-10 py-2 border border-yellow-400 bg-blue-50 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#7A0C23] focus:border-transparent w-500"
                                    @keyup.enter="handleSearch"
                                />
                                <!-- Search Icon -->
                                <IconButton
                                    icon="search"
                                    size="sm"
                                    color="gray"
                                    class="absolute left-2 top-1/2 transform -translate-y-1/2"
                                    @click="handleSearch"
                                    title="Search"
                                />
                                <!-- Clear Icon (only shows when there's text) -->
                                <IconButton
                                    v-if="searchQuery"
                                    icon="times"
                                    size="sm"
                                    color="gray"
                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 hover:bg-gray-100 rounded-full p-1"
                                    @click="clearSearch"
                                    title="Clear search"
                                />
                            </div>

                            <!-- Search Button -->

                        </div>

                    <!-- Rooms Table -->
                    <div class="overflow-x-auto bg-white rounded-lg shadow-xl mb-6">
                        <!-- Search Results Summary -->
                        <div v-if="searchQuery" class="px-4 py-2 bg-blue-50 border-b border-yellow-100">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <IconButton
                                        icon="search"
                                        size="sm"
                                        color="yellow"
                                        class="mr-2"
                                    />
                                    <span class="text-sm font-medium text-yellow-500">
                                        Search results for: "<span class="font-bold">{{ searchQuery }}</span>"
                                    </span>
                                    <span class="ml-3 text-xs text-yellow-600 bg-yellow-100 px-2 py-1 rounded-full">
                                        {{ filteredRooms.length }} {{ filteredRooms.length === 1 ? 'result' : 'results' }}
                                    </span>
                                </div>
                                <IconButton
                                    v-if="filteredRooms.length === 0"
                                    icon="info"
                                    size="xs"
                                    color="yellow"
                                    outlined
                                    title="No results found. Try different keywords."
                                />
                            </div>
                        </div>

                        <table class="min-w-full text-sm border-collapse">
                            <thead class="bg-[#800020] text-white">
                                <tr>
                                    <th class="px-4 py-3 font-semibold text-left">SUBJECT</th>
                                    <th class="px-4 py-3 font-semibold text-left hidden sm:table-cell">COLLEGE</th>
                                    <th class="px-4 py-3 font-semibold hidden md:table-cell">TIME</th>
                                    <th class="px-4 py-3 font-semibold hidden md:table-cell">DATE</th>
                                    <th class="px-4 py-3 font-semibold hidden md:table-cell">FACULTY</th>
                                    <th class="px-4 py-3 font-semibold hidden md:table-cell">ROOM</th>
                                    <th class="px-4 py-3 font-semibold hidden md:table-cell">BUILDING</th>
                                    <th class="px-4 py-3 font-semibold text-center">ACTION</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-yellow-600">
                                <tr v-if="filteredRooms.length === 0">
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500 italic">
                                        <div class="flex flex-col items-center justify-center">
                                            <IconButton
                                                icon="search"
                                                size="lg"
                                                color="gray"
                                                disabled
                                                class="mb-2"
                                            />
                                            <p v-if="searchQuery">No room records found matching "{{ searchQuery }}"</p>
                                            <p v-else>No room records found.</p>
                                            <p class="text-sm mt-1" v-if="searchQuery">
                                                Try different search terms like room name, college, or location.
                                            </p>
                                            <p class="text-sm mt-1" v-else>
                                                Click "Add Room" to create a new one.
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-for="room in filteredRooms" :key="room.id"
                                    class="odd:bg-white even:bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <td class="px-4 py-3 text-left font-medium">{{ room.room_name || 'N/A' }}</td>
                                    <td class="px-4 py-3 text-left hidden sm:table-cell">{{ room.college?.college_name || 'N/A' }}</td>
                                    <td class="px-4 py-3 hidden md:table-cell">{{ room.user_account?.username || 'N/A' }}</td>
                                    <td class="px-4 py-3 hidden md:table-cell">{{ room.location ?? "N/A" }}</td>
                                    <td class="px-4 py-3 hidden md:table-cell">Faculty Data</td>
                                    <td class="px-4 py-3 hidden md:table-cell">{{ room.room_name || 'N/A' }}</td>
                                    <td class="px-4 py-3 hidden md:table-cell">{{ room.location || 'N/A' }}</td>

                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center justify-center space-x-2">
                                            <!-- View Button -->
                                            <IconButton
                                                icon="eye"
                                                size="sm"
                                                color="blue"
                                                title="View Details"
                                                @click="handleViewDetails(room)"
                                                class="hover:scale-110 transition-transform"
                                            />

                                            <!-- Edit Button -->
                                            <IconButton
                                                icon="edit"
                                                size="sm"
                                                color="green"
                                                title="Edit Room"
                                                @click="handleEditRoom(room)"
                                                class="hover:scale-110 transition-transform"
                                            />

                                            <!-- Delete Button -->
                                            <IconButton
                                                icon="delete"
                                                size="sm"
                                                color="red"
                                                title="Delete Room"
                                                @click="handleDeleteRoom(room)"
                                                class="hover:scale-110 transition-transform"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (Only show if not searching) -->
                    <div v-if="rooms && rooms.links && !searchQuery" class="mt-2 flex justify-end">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4
                                    bg-gray-100 border border-gray-300 rounded-md px-3 py-1">
                            <p class="text-xs sm:text-sm border-r border-gray-300 px-3" v-if="rooms.from">
                                {{ rooms.from }}-{{ rooms.to }} of {{ rooms.total }}
                            </p>
                            <div class="flex space-x-1">
                                <span v-for="link in rooms.links" :key="link.label">
                                    <button
                                        v-if="link.url"
                                        @click="goToPage(link.url)"
                                        class="p-1 text-xs sm:text-sm rounded transition"
                                        :class="{
                                            'text-gray-600 hover:bg-gray-200': link.url && !link.active,
                                            'bg-blue-600 text-white font-bold hover:bg-blue-700': link.active,
                                            'text-gray-400 cursor-not-allowed': !link.url
                                        }">
                                        <template v-if="link.label.includes('Previous')">
                                            <IconButton
                                                icon="chevronLeft"
                                                size="xs"
                                                color="gray"
                                                disabled
                                            />
                                        </template>
                                        <template v-else-if="link.label.includes('Next')">
                                            <IconButton
                                                icon="chevronRight"
                                                size="xs"
                                                color="gray"
                                                disabled
                                            />
                                        </template>
                                        <span class="px-1" v-else v-html="link.label"></span>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </slot>
            </main>
        </div>

        <!-- Details Modal -->
        <transition name="fade">
            <div v-if="isDetailsModalVisible"
                class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg p-6 relative">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b">
                        <h3 class="text-xl font-bold text-[#800020]">Room/Schedule Details</h3>
                        <IconButton
                            icon="times"
                            size="sm"
                            color="gray"
                            title="Close"
                            @click="closeDetailsModal"
                        />
                    </div>

                    <div v-if="currentViewedDetails" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Room Name:</label>
                                <p class="text-gray-900 font-medium">{{ currentViewedDetails.room_name || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Location:</label>
                                <p class="text-gray-900">{{ currentViewedDetails.location || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">College:</label>
                                <p class="text-gray-900">{{ currentViewedDetails.college?.college_name || currentViewedDetails.college || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">User Account:</label>
                                <p class="text-gray-900">{{ currentViewedDetails.user_account?.username || 'N/A' }}</p>
                            </div>
                        </div>

                        <div class="pt-3 border-t">
                            <div class="flex items-center mb-3">
                                <IconButton
                                    icon="list"
                                    size="sm"
                                    color="gray"
                                    class="mr-2"
                                />
                                <h4 class="font-medium text-gray-900">Schedules:</h4>
                            </div>

                            <div v-if="(currentViewedDetails.schedules || []).length === 0"
                                 class="text-center py-4 text-gray-500 italic bg-gray-50 rounded">
                                <IconButton
                                    icon="warning"
                                    size="sm"
                                    color="gray"
                                    disabled
                                    class="mb-2"
                                />
                                <p>No schedules found for this room</p>
                            </div>

                            <div v-else class="max-h-60 overflow-y-auto pr-2 space-y-2">
                                <div v-for="sched in currentViewedDetails.schedules" :key="sched.id"
                                    class="p-3 border rounded bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="text-xs text-gray-500">CFIC ID:</label>
                                            <p class="text-sm">{{ sched.cfic_id || 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">Course:</label>
                                            <p class="text-sm">{{ sched.course_name || 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">Day:</label>
                                            <p class="text-sm">{{ sched.day || 'N/A' }}</p>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500">Time:</label>
                                            <p class="text-sm">{{ sched.start_time || 'N/A' }} — {{ sched.end_time || 'N/A' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t flex justify-end space-x-3">
                        <IconButton
                            icon="edit"
                            outlined
                            color="green"
                            title="Edit Room"
                            @click="handleEditRoom(currentViewedDetails)"
                            v-if="currentViewedDetails"
                        >
                            Edit Room
                        </IconButton>

                        <IconButton
                            icon="times"
                            outlined
                            color="gray"
                            title="Close"
                            @click="closeDetailsModal"
                        >
                            Close
                        </IconButton>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
/* Fade transition for sidebar overlay and details modal */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Smooth hover transitions for cards */
.rounded-xl {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.rounded-xl:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

/* Highlight search results */
.highlight-search {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
}

/* Search bar clear button animation */
.clear-button {
    transition: all 0.2s ease;
}

.clear-button:hover {
    background-color: #f8f9fa;
    transform: scale(1.1);
}
</style>
