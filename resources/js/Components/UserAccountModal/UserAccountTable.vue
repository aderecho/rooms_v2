<script setup>
import { computed, ref, onMounted } from 'vue';
import IconButton from '@/Components/IconButton.vue';

const props = defineProps({
    users: {
        type: Array,
        required: true
    },
    loading: {
        type: Boolean,
        default: false
    },
    colleges: {
        type: Array,
        default: () => []
    },
    departments: {
        type: Array,
        default: () => []
    },
    canManageUsers: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['openModal', 'search', 'searchQueryUpdated']);

const searchQuery = ref('');

// --- Pagination State ---
const currentPage = ref(1);
const itemsPerPage = ref(10);

// Filtering uses the new user fields
const filteredUsers = computed(() => {
    if (!searchQuery.value) {
        return props.users;
    }
    const query = searchQuery.value.toLowerCase();
    return props.users.filter(user => {
        // Check if user object exists
        if (!user) return false;

        return (
            (user.username && user.username.toLowerCase().includes(query)) ||
            (user.email && user.email.toLowerCase().includes(query)) ||
            (user.first_name && user.first_name.toLowerCase().includes(query)) ||
            (user.last_name && user.last_name.toLowerCase().includes(query)) ||
            (user.role && user.role.toLowerCase().includes(query)) ||
            (user.department && user.department.toLowerCase().includes(query)) ||
            (user.college && user.college.toLowerCase().includes(query))
        );
    });
});

// --- Paginated Data ---
const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredUsers.value.slice(start, end);
});

// --- Pagination Computed ---
const totalPages = computed(() => {
    return Math.ceil(filteredUsers.value.length / itemsPerPage.value);
});

const visiblePages = computed(() => {
    const total = totalPages.value;
    const current = currentPage.value;

    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    if (current <= 4) {
        return [1, 2, 3, 4, 5, '...', total];
    }

    if (current >= total - 3) {
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    }

    return [1, '...', current - 1, current, current + 1, '...', total];
});

const showingRange = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value + 1;
    const end = Math.min(currentPage.value * itemsPerPage.value, filteredUsers.value.length);
    const total = filteredUsers.value.length;
    return { start, end, total };
});

// --- Pagination Methods ---
const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const resetPagination = () => {
    currentPage.value = 1;
};

// Search function
const performSearch = () => {
    emit('searchQueryUpdated', searchQuery.value);
    emit('search');
};

// Handle search input
const handleSearchInput = () => {
    emit('searchQueryUpdated', searchQuery.value);
    resetPagination();
};

// --- Action Handlers ---
const handleAddAccount = () => {
    emit('openModal', 'add');
};


const handleView = (user) => {
    emit('openModal', 'view', user);
};

const handleEdit = (user) => {
    emit('openModal', 'edit', user);
};

const handleDelete = (user) => {
    emit('openModal', 'delete', user);
};

// Helper function to format role display
const formatRole = (role) => {
    if (!role) return 'N/A';
    return role.charAt(0).toUpperCase() + role.slice(1);
};

// Get role color class
const getRoleColor = (role) => {
    if (!role) return 'bg-gray-100 text-gray-800';

    const roleLower = role.toLowerCase();
    if (roleLower === 'admin') return 'bg-purple-100 text-purple-800';
    if (roleLower === 'staff') return 'bg-blue-100 text-blue-800';
    if (roleLower === 'faculty') return 'bg-green-100 text-green-800';
    if (roleLower === 'student') return 'bg-yellow-100 text-yellow-800';
    if (roleLower === 'guest') return 'bg-gray-100 text-gray-800';
    return 'bg-gray-100 text-gray-800';
};

onMounted(() => {
    console.log('UserAccountTable mounted with', props.users.length, 'users');
});
</script>


<template>
    <div class="flex-1 p-6">
        <!-- Header Section with Title and Breadcrumb -->
        <div class="mb-6">
            <div class="flex justify-between items-center">
                <!-- Title on the Left -->
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-[#7A0C23]">
                        Account Management
                    </h1>
                </div>

                <!-- Breadcrumb on the Right -->
                <div class="text-sm text-gray-500">
                    <span>UPCEBU > ACCOUNT</span>
                </div>
            </div>
        </div>

        <!-- Search and Add Button Section -->
        <div class="mb-6 flex justify-between items-center">
            <!-- Search Input -->
            <div class="relative">
                <input
                    type="text"
                    v-model="searchQuery"
                    @input="handleSearchInput"
                    @keyup.enter="performSearch"
                    placeholder="Search users..."
                    class="border border-yellow-400 bg-blue-50 rounded-full pl-10 pr-4 py-2 w-72 focus:outline-none focus:ring-2 focus:ring-sky-400"
                />
                <!-- Search Icon using IconButton -->
                <IconButton
                    @click="performSearch"
                    icon="search"
                    size="sm"
                    color="gray"
                    class="absolute left-3 top-1/2 transform -translate-y-1/2 cursor-pointer"
                />
            </div>

           
            
          <!-- Add User Button -->
      <div v-if="canManageUsers" class="pt-7 mb-3 flex justify-end">
        <IconButton 
          @click="handleAddAccount" 
          icon="plus" 
          title="Add User Account" 
          size="md" 
          color="green" 
          outlined
          class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-500 transition-colors duration-200"
        >
          Add User Account
        </IconButton>
      </div>
        </div>

        <!-- Main Table Container -->
        <div class="border-yellow-700 bg-white rounded-lg shadow-xl overflow-hidden">
            <div class="overflow-x-auto max-h-[80vh]">
                <table class="min-w-full border border-yellow-400 text-sm text-center">
                    <thead class="border-yellow-400 bg-[#7A0C23] text-white sticky top-0 shadow">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-left">NAME</th>
                            <th class="px-4 py-3 font-semibold text-left">USERNAME</th>
                            <th class="px-4 py-3 font-semibold text-left">EMAIL</th>
                            <th class="px-4 py-3 font-semibold">ROLE</th>
                            <th class="px-4 py-3 font-semibold">DEPARTMENT</th>
                            <th class="px-4 py-3 font-semibold">COLLEGE</th>
                            <th class="px-4 py-3 font-semibold">ACTION</th>
                        </tr>
                    </thead>

                    <!-- Loading State -->
                    <tbody v-if="loading">
                        <tr>
                            <td colspan="7" class="p-8 text-center">
                                <div class="flex items-center justify-center">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#7A0C23] mr-3"></div>
                                    <span class="text-gray-600">Loading users...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>

                    <!-- Data Rows -->
                    <tbody v-else>
                        <tr
                            v-for="(user, index) in paginatedUsers"
                            :key="user.id || index"
                            class="border-yellow-400 odd:bg-white even:bg-gray-100 hover:bg-gray-200 transition duration-100"
                        >
                            <td class="border-yellow-400 px-4 py-2 text-left font-medium text-gray-800">
                                {{ user.first_name || 'N/A' }} {{ user.last_name || '' }}
                            </td>
                            <td class="px-4 py-2 text-left">{{ user.username || 'N/A' }}</td>
                            <td class="px-4 py-2 text-left">{{ user.email || 'N/A' }}</td>
                            <td class="px-4 py-2">
                                <span :class="['px-2 py-1 text-xs rounded-full font-medium', getRoleColor(user.role)]">
                                    {{ formatRole(user.role) }}
                                </span>
                            </td>
                            <td class="px-4 py-2">{{ user.department || 'N/A' }}</td>
                            <td class="px-4 py-2">{{ user.college || 'N/A' }}</td>
                            <td class="px-4 py-2 space-x-2 whitespace-nowrap">
                                <!-- View Button using IconButton -->
                                <IconButton
                                    @click="handleView(user)"
                                    icon="eye"
                                    title="View User"
                                    size="sm"
                                    color="blue"
                                    class="hover:scale-110 transition-transform"
                                />
                                <template v-if="canManageUsers">
                                    <!-- Edit Button using IconButton -->
                                    <IconButton
                                        @click="handleEdit(user)"
                                        icon="edit"
                                        title="Edit User"
                                        size="sm"
                                        color="green"
                                        class="hover:scale-110 transition-transform"
                                    />
                                    <!-- Delete Button using IconButton -->
                                    <IconButton
                                        @click="handleDelete(user)"
                                        icon="delete"
                                        title="Delete User"
                                        size="sm"
                                        color="red"
                                        class="hover:scale-110 transition-transform"
                                    />
                                </template>
                            </td>
                        </tr>
                        <tr v-if="filteredUsers.length === 0 && !loading">
                            <td colspan="7" class="p-8 text-center text-gray-500">
                                <div v-if="props.users.length === 0">
                                    No users found in the database.
                                    <button
                                        v-if="canManageUsers"
                                        @click="handleAddAccount"
                                        class="text-blue-600 hover:text-blue-800 underline ml-2"
                                    >
                                        Add your first user
                                    </button>
                                </div>
                                <div v-else>
                                    No users found matching your search.
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div v-if="filteredUsers.length > 0 && !loading" class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">

                    <!-- Showing range -->
                    <div class="text-sm text-gray-600 font-medium">
                        Showing {{ showingRange.start }}-{{ showingRange.end }} of {{ showingRange.total }} users
                    </div>

                    <!-- Items per page selector -->
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600">Show:</span>
                        <select
                            v-model="itemsPerPage"
                            @change="resetPagination"
                            class="text-sm border border-gray-300 rounded px-2 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-[#7A0C23] focus:border-transparent"
                        >
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                            <option value="20">20</option>
                            <option value="30">30</option>
                        </select>
                        <span class="text-sm text-gray-600">per page</span>
                    </div>

                    <!-- Page navigation -->
                    <div class="flex items-center space-x-2">
                        <!-- Previous button using IconButton -->
                        <IconButton
                            @click="prevPage"
                            :disabled="currentPage === 1"
                            icon="chevronLeft"
                            title="Previous Page"
                            size="sm"
                            color="gray"
                            outlined
                            :class="[
                                'px-3 py-1.5 rounded text-sm font-medium transition-colors duration-150',
                                currentPage === 1
                                    ? 'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400'
                            ]"
                        >
                            Previous
                        </IconButton>

                        <!-- Page numbers -->
                        <div class="flex items-center space-x-1">
                            <template v-for="(page, index) in visiblePages" :key="`${page}-${index}`">
                                <button
                                    v-if="page !== '...'"
                                    @click="goToPage(page)"
                                    :class="[
                                        'px-3 py-1.5 rounded border text-sm font-medium min-w-[36px] transition-colors duration-150',
                                        currentPage === page
                                            ? 'bg-[#7A0C23] text-white border-[#7A0C23]'
                                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                    ]"
                                >
                                    {{ page }}
                                </button>
                                <span
                                    v-else
                                    class="px-2 text-gray-500 text-sm"
                                >
                                    ...
                                </span>
                            </template>
                        </div>

                        <!-- Next button using IconButton -->
                        <IconButton
                            @click="nextPage"
                            :disabled="currentPage === totalPages"
                            icon="chevronRight"
                            title="Next Page"
                            size="sm"
                            color="gray"
                            outlined
                            :class="[
                                'px-3 py-1.5 rounded text-sm font-medium transition-colors duration-150',
                                currentPage === totalPages
                                    ? 'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400'
                            ]"
                        >
                            Next
                        </IconButton>
                    </div>

                    <!-- Page indicator -->
                    <div class="text-sm text-gray-600">
                        Page {{ currentPage }} of {{ totalPages }}
                    </div>
                </div>

                <!-- Results summary -->
                <div class="mt-4 pt-3 border-t border-gray-300 text-center">
                    <p class="text-sm text-gray-500">
                        Total Users in Database: <span class="font-semibold text-[#7A0C23]">{{ users.length }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>


<style scoped>
/* Custom styles for pagination */
button:not(:disabled):hover {
    transform: translateY(-1px);
    transition: transform 0.2s ease;
}

/* Ensure pagination controls are properly spaced */
.space-x-1 > * + * {
    margin-left: 0.25rem;
}

.space-x-2 > * + * {
    margin-left: 0.5rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .flex-col.md\:flex-row {
        gap: 1rem;
    }

    .space-x-2 {
        justify-content: center;
    }

    /* Make search input and button stack on mobile */
    .mb-6.flex.justify-between.items-center {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .mb-6.flex.justify-between.items-center .relative {
        width: 100%;
    }

    .mb-6.flex.justify-between.items-center .relative input {
        width: 100%;
    }

    .mb-6.flex.justify-between.items-center .IconButton {
        width: 100%;
        justify-content: center;
    }
}
</style>
