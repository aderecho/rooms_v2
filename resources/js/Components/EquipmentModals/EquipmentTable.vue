<script setup>
import { ref, computed, onMounted, onUnmounted, watch, defineEmits } from 'vue'
import axios from 'axios'
import EquipmentModal from '@/Components/EquipmentModals/EquipmentModal.vue'
import IconButton from '@/Components/IconButton.vue'

// --- Props & Emits ---
const emit = defineEmits(['chart-data-update'])

// --- API Base URL ---
const baseUrl = '/api/equipment'

// --- Pagination State ---
const currentPage = ref(1)
const itemsPerPage = ref(5)

// --- Filter State ---
const activeFilter = ref('all') // 'all', 'available', 'in_use'
const searchTerm = ref('')

// --- Real-time Date & Time ---
const currentDate = ref('')
const currentTime = ref('')
let timerInterval = null

const updateDateTime = () => {
    const now = new Date()
    currentDate.value = now.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    })
    currentTime.value = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    })
}

// --- Equipment Data ---
const usageList = ref([])
const initialLoading = ref(true)
const error = ref(null)

// --- Statistics ---
const statistics = ref({
    total: 0,
    available: 0,
    in_use: 0,
    maintenance: 0,
    damaged: 0,
    retired: 0,
    total_value: 0,
    average_value: 0,
    person_stats: [],
    building_stats: []
})

// --- Fetch Equipment Usage Data ---
const fetchEquipmentUsage = async () => {
    try {
        const response = await axios.get(`${baseUrl}/usage`)

        if (response.data.success) {
            usageList.value = response.data.usage_list.map((item, index) => ({
                id: item.id || index + 1,
                room: item.room || 'N/A',
                name: item.name,
                building: item.building,
                college: item.college,
                equipmentUsed: item.equipmentUsed.map(eq => ({
                    inventory_id: eq.inventory_id,
                    property_id: eq.property_id,
                    name: eq.name,
                    cfic: eq.cfic || 'N/A',
                    status: eq.status,
                    description: eq.description,
                    brand: eq.brand,
                    model: eq.model,
                    serial_number: eq.serial_number
                }))
            }))
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to fetch equipment usage data'
        console.error('Error fetching equipment usage:', err)
    } finally {
        initialLoading.value = false
    }
}

const generateChartColors = (count) => {
    const palette = [
        '#4CAF50', '#FF9800', '#2196F3', '#9C27B0', '#FF5722',
        '#795548', '#607D8B', '#3F51B5', '#009688', '#E91E63',
        '#7A0C23', '#00BCD4', '#8BC34A', '#FFC107', '#673AB7',
    ]

    return Array.from({ length: count }, (_, index) => palette[index % palette.length])
}

const emitChartData = () => {
    const personStats = statistics.value.person_stats || []
    const buildingStats = statistics.value.building_stats || []

    emit('chart-data-update', {
        pieData: {
            labels: personStats.map((p) => p.name),
            datasets: [{
                data: personStats.map((p) => p.equipmentCount),
                backgroundColor: generateChartColors(personStats.length),
            }],
        },
        barData: {
            labels: buildingStats.map((b) => b.building),
            datasets: [{
                label: 'Equipment Count',
                data: buildingStats.map((b) => b.equipmentCount),
                backgroundColor: '#7A0C23',
            }],
        },
    })
}

const getStatsFilterParams = () => {
    const params = {}

    if (searchTerm.value.trim()) {
        params.search = searchTerm.value.trim()
    }

    if (activeFilter.value === 'available') {
        params.status = 'available'
    } else if (activeFilter.value === 'in_use') {
        params.status = 'in_use'
    }

    return params
}

// --- Fetch Equipment Statistics (filtered from database) ---
const fetchEquipmentStats = async () => {
    try {
        const response = await axios.get(`${baseUrl}/stats`, {
            params: getStatsFilterParams(),
        })

        if (response.data.success) {
            statistics.value = response.data.data
            emitChartData()
        }
    } catch (err) {
        console.error('Error fetching equipment stats:', err)
    }
}

// --- Filter Methods ---
const setFilter = (filterType) => {
    activeFilter.value = filterType
    resetPagination()
}

const clearFilter = () => {
    activeFilter.value = 'all'
    resetPagination()
}

// --- Filtered List (Client-side filtering) ---
const filteredUsageList = computed(() => {
    let filtered = usageList.value

    // Apply search filter
    const term = searchTerm.value.trim().toLowerCase()
    if (term) {
        filtered = filtered.filter(item =>
            String(item.room).toLowerCase().includes(term) ||
            item.name.toLowerCase().includes(term) ||
            item.building.toLowerCase().includes(term) ||
            item.college.toLowerCase().includes(term)
        )
    }

    // Apply status filter (Total Items, Available, In Use)
    if (activeFilter.value !== 'all') {
        filtered = filtered.filter(item => {
            // Check if any equipment in the room matches the filter status
            return item.equipmentUsed.some(eq => {
                if (activeFilter.value === 'available') {
                    return eq.status === 'Available'
                } else if (activeFilter.value === 'in_use') {
                    return eq.status === 'In use'
                }
                return true
            })
        })
    }

    return filtered
})

// Statistics cards use the same filtered API data as the charts
const filteredStatistics = computed(() => statistics.value)

// --- Paginated Data ---
const paginatedUsageList = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value
    const end = start + itemsPerPage.value
    return filteredUsageList.value.slice(start, end)
})

// --- Pagination Computed ---
const totalPages = computed(() => {
    return Math.ceil(filteredUsageList.value.length / itemsPerPage.value)
})

const showingRange = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value + 1
    const end = Math.min(currentPage.value * itemsPerPage.value, filteredUsageList.value.length)
    const total = filteredUsageList.value.length
    return { start, end, total }
})

// --- Pagination Methods ---
const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++
    }
}

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--
    }
}

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page
    }
}

const resetPagination = () => {
    currentPage.value = 1
}

// --- Modal State ---
const isDetailsModalVisible = ref(false)
const selectedUserUsage = ref(null)

const handleViewDetails = (usage) => {
    selectedUserUsage.value = usage
    isDetailsModalVisible.value = true
}

const closeDetailsModal = () => {
    isDetailsModalVisible.value = false
    selectedUserUsage.value = null
}

// Debounce search
let searchTimeout = null
const handleSearch = () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout)
    }
    
    searchTimeout = setTimeout(() => {
        resetPagination()
    }, 300)
}

// --- Initialize Data ---
const initializeData = async () => {
    await Promise.all([
        fetchEquipmentUsage(),
        fetchEquipmentStats()
    ])
}

// --- Lifecycle & Watchers ---
onMounted(() => {
    updateDateTime()
    timerInterval = setInterval(updateDateTime, 1000)
    initializeData()
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
    if (searchTimeout) clearTimeout(searchTimeout)
    if (statsTimeout) clearTimeout(statsTimeout)
})

let statsTimeout = null
const refreshChartStats = () => {
    if (statsTimeout) {
        clearTimeout(statsTimeout)
    }

    statsTimeout = setTimeout(() => {
        fetchEquipmentStats()
    }, 300)
}

watch([searchTerm, activeFilter], () => {
    refreshChartStats()
})

// Watch items per page change
watch(itemsPerPage, () => {
    resetPagination()
})

// Watch search term
watch(searchTerm, () => {
    handleSearch()
})
</script>

<template>
    <div class="space-y-4 p-2 sm:p-4">
        <!-- Initial Loading State -->
        <div v-if="initialLoading" class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#7A0C23]"></div>
            <p class="mt-2 text-gray-600">Loading equipment data...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <p class="text-red-600 font-semibold">Error: {{ error }}</p>
            <button @click="initializeData" class="mt-2 px-4 py-2 bg-[#7A0C23] text-white rounded-lg hover:bg-[#5a091a] transition-colors">
                Retry
            </button>
        </div>

        <!-- Main Content -->
        <div v-else>
            <div class="bg-white shadow-lg rounded-xl p-4 sm:p-6">
                <!-- Header Section - Responsive -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold text-[#7A0C23]">Equipment Management</h1>
                        <p class="text-sm sm:text-base text-gray-600 mt-1">Track and manage all equipment assets</p>
                    </div>

                    <!-- Date/Time Section - Responsive -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center px-3 py-2 sm:px-4 sm:py-2 bg-yellow-400 rounded-lg text-xs sm:text-sm font-semibold text-white">
                            📄 {{ currentDate }}
                        </div>
                        <div class="flex items-center px-3 py-2 sm:px-4 sm:py-2 bg-green-400 rounded-lg text-xs sm:text-sm font-semibold text-white">
                            ⏱ {{ currentTime }}
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards with Filters - Responsive Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mt-6">
                    <!-- Search Input - Full width on mobile -->
                    <div class="relative w-full sm:col-span-2 lg:col-span-1">
                        <input
                            type="text"
                            v-model="searchTerm"
                            placeholder="Search by name, room..."
                            class="w-full pl-10 pr-4 py-2 sm:py-3 border border-yellow-500 rounded-lg focus:ring-2 focus:ring-[#7A0C23] focus:border-transparent outline-none transition-all text-sm"
                        />
                        <div class="absolute left-3 top-2.5 sm:top-3.5 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <button 
                            v-if="searchTerm"
                            @click="searchTerm = ''"
                            class="absolute right-3 top-2.5 sm:top-3.5 text-gray-400 hover:text-gray-600"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:h-5 sm:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Total Items Card -->
                    <div 
                        @click="setFilter('all')"
                        :class="[
                            'p-3 sm:p-4 rounded-lg border-2 cursor-pointer transition-all duration-200 hover:shadow-lg',
                            activeFilter === 'all' 
                                ? 'bg-[#7A0C23] border-[#7A0C23] text-white scale-105 shadow-lg' 
                                : 'bg-white border-gray-200 text-gray-700 hover:border-[#7A0C23]'
                        ]"
                    >
                        <p class="text-xs sm:text-sm font-bold uppercase flex items-center justify-between">
                            Total Items
                            <span v-if="activeFilter === 'all'" class="text-xs bg-white text-[#7A0C23] px-2 py-1 rounded-full">Active</span>
                        </p>
                        <p class="text-xl sm:text-2xl font-bold mt-1">{{ filteredStatistics.total || 0 }}</p>
                        <p class="text-xs mt-1 opacity-75 hidden sm:block">Click to view all items</p>
                    </div>

                    <!-- Available Card -->
                    <div 
                        @click="setFilter('available')"
                        :class="[
                            'p-3 sm:p-4 rounded-lg border-2 cursor-pointer transition-all duration-200 hover:shadow-lg',
                            activeFilter === 'available' 
                                ? 'bg-green-600 border-green-600 text-white scale-105 shadow-lg' 
                                : 'bg-white border-gray-200 text-gray-700 hover:border-green-600'
                        ]"
                    >
                        <p class="text-xs sm:text-sm font-bold uppercase flex items-center justify-between">
                            Available
                            <span v-if="activeFilter === 'available'" class="text-xs bg-white text-green-600 px-2 py-1 rounded-full">Active</span>
                        </p>
                        <p class="text-xl sm:text-2xl font-bold mt-1">{{ filteredStatistics.available || 0 }}</p>
                        <p class="text-xs mt-1 opacity-75 hidden sm:block">Click to view available items</p>
                    </div>

                    <!-- In Use Card -->
                    <div 
                        @click="setFilter('in_use')"
                        :class="[
                            'p-3 sm:p-4 rounded-lg border-2 cursor-pointer transition-all duration-200 hover:shadow-lg',
                            activeFilter === 'in_use' 
                                ? 'bg-yellow-400 border-yellow-400 text-white scale-105 shadow-lg' 
                                : 'bg-white border-gray-200 text-gray-700 hover:border-yellow-400'
                        ]"
                    >
                        <p class="text-xs sm:text-sm font-bold uppercase flex items-center justify-between">
                            In Use
                            <span v-if="activeFilter === 'in_use'" class="text-xs bg-white text-yellow-400 px-2 py-1 rounded-full">Active</span>
                        </p>
                        <p class="text-xl sm:text-2xl font-bold mt-1">{{ filteredStatistics.in_use || 0 }}</p>
                        <p class="text-xs mt-1 opacity-75 hidden sm:block">Click to view in-use items</p>
                    </div>
                </div>

                <!-- Active Filter Indicator - Responsive -->
                <div v-if="activeFilter !== 'all' || searchTerm" class="mt-4 flex flex-wrap items-center gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs sm:text-sm text-gray-600">Active Filters:</span>
                        <span v-if="activeFilter !== 'all'" class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium"
                            :class="{
                                'bg-[#7A0C23] text-white': activeFilter === 'all',
                                'bg-green-100 text-green-800': activeFilter === 'available',
                                'bg-yellow-100 text-yellow-500': activeFilter === 'in_use'
                            }"
                        >
                            {{ activeFilter === 'all' ? 'All Items' : activeFilter === 'available' ? 'Available Only' : 'In Use Only' }}
                            <button @click.stop="clearFilter" class="ml-2 hover:text-white focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                        <span v-if="searchTerm" class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium bg-yellow-100 text-yellow-800">
                            Search: "{{ searchTerm }}"
                            <button @click="searchTerm = ''" class="ml-2 hover:text-yellow-900 focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                        <button 
                            @click="clearFilter(); searchTerm = ''" 
                            class="text-xs sm:text-sm text-[#7A0C23] hover:text-[#5a091a] underline"
                        >
                            Clear All Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Equipment Table - Responsive -->
            <div class="mt-6 sm:mt-10 bg-white shadow-lg rounded-xl overflow-hidden border border-yellow-400">
                <!-- Mobile Card View (hidden on larger screens) -->
                <div class="block lg:hidden">
                    <div v-for="item in paginatedUsageList" :key="item.id" class="border-b border-yellow-400 p-4 hover:bg-gray-50">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-8 h-8 flex items-center justify-center bg-green-100 text-green-700 rounded-full font-bold text-xs">
                                    {{ item.equipmentUsed.length }}
                                </span>
                                <span class="text-xs text-gray-500 font-medium">Equipments</span>
                            </div>
                            <span class="px-2 py-1 text-xs font-bold bg-gray-100 text-gray-600 rounded">
                                {{ item.college }}
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <div class="text-xs text-gray-500">Location</div>
                                <div class="text-sm font-semibold text-green-700">Room {{ item.room }}</div>
                                <div class="text-xs text-gray-500">{{ item.building }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500">Accountable Person</div>
                                <div class="font-bold text-gray-900 text-sm">{{ item.name }}</div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <IconButton
                                @click="handleViewDetails(item)"
                                icon="eye"
                                title="View Details"
                                size="sm"
                                color="black"
                                outlined
                                class="hover:scale-105 bg-green-500 text-xs"
                            >
                                View Details
                            </IconButton>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table View (hidden on mobile) -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-[#7A0C23] text-white sticky top-0 z-10">
                            <tr>
                                <th class="px-4 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm font-bold uppercase tracking-wider">Items</th>
                                <th class="px-4 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm font-bold uppercase tracking-wider">Location</th>
                                <th class="px-4 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm font-bold uppercase tracking-wider">College</th>
                                <th class="px-4 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm font-bold uppercase tracking-wider">Accountable Person</th>
                                <th class="px-4 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm font-bold uppercase tracking-wider text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-yellow-400">
                            <tr v-for="item in paginatedUsageList" :key="item.id" class="hover:bg-gray-300 transition-colors">
                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="w-8 h-8 flex items-center justify-center bg-green-100 text-green-700 rounded-full font-bold text-xs">
                                            {{ item.equipmentUsed.length }}
                                        </span>
                                        <span class="text-xs text-gray-500 font-medium">Equipments</span>
                                    </div>
                                </td>

                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                    <div class="text-sm font-semibold text-green-700">Room {{ item.room }}</div>
                                    <div class="text-xs text-gray-500">{{ item.building }}</div>
                                </td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                    <span class="px-2 py-1 text-xs font-bold bg-gray-100 text-gray-600 rounded">
                                        {{ item.college }}
                                    </span>
                                </td>

                                <td class="px-4 sm:px-6 py-3 sm:py-4">
                                    <div class="font-bold text-gray-900">{{ item.name }}</div>
                                </td>

                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-center">
                                    <IconButton
                                        @click="handleViewDetails(item)"
                                        icon="eye"
                                        title="View Details"
                                        size="sm"
                                        color="black"
                                        outlined
                                        class="hover:scale-105 bg-green-500"
                                    >
                                        View Details
                                    </IconButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- No Results State -->
                <div v-if="filteredUsageList.length === 0" class="px-4 sm:px-6 py-8 sm:py-12 text-center text-gray-400">
                    <p class="text-base sm:text-lg font-bold">No results found</p>
                    <p class="text-xs sm:text-sm mt-2">Try adjusting your filters or search terms.</p>
                    <button 
                        @click="clearFilter(); searchTerm = ''" 
                        class="mt-4 px-4 py-2 bg-[#7A0C23] text-white rounded-lg hover:bg-[#5a091a] transition-colors text-sm"
                    >
                        Clear All Filters
                    </button>
                </div>

                <!-- Pagination Controls - Responsive -->
                <div v-if="filteredUsageList.length > 0" class="border-t border-gray-200 bg-gray-50 px-4 sm:px-6 py-4">
                    <div class="flex flex-col space-y-4">
                        <!-- Items per page and showing range - Stack on mobile -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div class="text-xs sm:text-sm text-gray-600 order-2 sm:order-1">
                                Showing {{ showingRange.start }} to {{ showingRange.end }} of {{ showingRange.total }} entries
                            </div>

                            <div class="flex items-center space-x-2 order-1 sm:order-2">
                                <span class="text-xs sm:text-sm text-gray-600">Show:</span>
                                <select
                                    v-model="itemsPerPage"
                                    class="text-xs sm:text-sm border border-gray-300 rounded px-2 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-[#7A0C23] focus:border-transparent"
                                >
                                    <option value="3">3</option>
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                </select>
                                <span class="text-xs sm:text-sm text-gray-600">per page</span>
                            </div>
                        </div>

                        <!-- Page navigation - Responsive -->
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <button
                                @click="prevPage"
                                :disabled="currentPage === 1"
                                :class="[
                                    'px-2 sm:px-3 py-1.5 rounded text-xs sm:text-sm font-medium transition-colors duration-150 border',
                                    currentPage === 1
                                        ? 'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed'
                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400'
                                ]"
                            >
                                Previous
                            </button>

                            <div class="flex flex-wrap items-center justify-center gap-1">
                                <button
                                    v-for="page in totalPages"
                                    :key="page"
                                    @click="goToPage(page)"
                                    :class="[
                                        'px-2 sm:px-3 py-1.5 rounded border text-xs sm:text-sm font-medium min-w-[32px] sm:min-w-[36px] transition-colors duration-150',
                                        currentPage === page
                                            ? 'bg-[#7A0C23] text-white border-[#7A0C23]'
                                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                    ]"
                                >
                                    {{ page }}
                                </button>
                            </div>

                            <button
                                @click="nextPage"
                                :disabled="currentPage === totalPages"
                                :class="[
                                    'px-2 sm:px-3 py-1.5 rounded text-xs sm:text-sm font-medium transition-colors duration-150 border',
                                    currentPage === totalPages
                                        ? 'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed'
                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400'
                                ]"
                            >
                                Next
                            </button>
                        </div>

                        <!-- Results summary -->
                        <div class="pt-3 border-t border-gray-300 text-center">
                            <p class="text-xs sm:text-sm text-gray-500">
                                Filtered Results: <span class="font-semibold text-[#7A0C23]">{{ filteredUsageList.length }}</span>
                                | Total Records: <span class="font-semibold text-[#7A0C23]">{{ usageList.length }}</span>
                                <span v-if="activeFilter !== 'all'" class="ml-2 text-xs">
                                    (Filtered by: {{ activeFilter === 'available' ? 'Available' : 'In Use' }})
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Empty state when no data -->
                <div v-if="usageList.length === 0 && !initialLoading" class="px-4 sm:px-6 py-8 sm:py-12 text-center text-gray-400 bg-white">
                    <p class="text-base sm:text-lg font-bold">No equipment records available</p>
                    <p class="text-xs sm:text-sm mt-2">Start by adding equipment records to the system.</p>
                </div>
            </div>

            <EquipmentModal
                :is-visible="isDetailsModalVisible"
                :selected-usage="selectedUserUsage"
                @close="closeDetailsModal"
            />
        </div>
    </div>
</template>

<style scoped>
thead th {
    position: sticky;
    top: 0;
    z-index: 10;
}

button:not(:disabled):hover {
    transform: translateY(-1px);
    transition: transform 0.2s ease;
}

/* Responsive pagination */
@media (max-width: 640px) {
    .space-x-1 > * + * {
        margin-left: 0.15rem;
    }
    
    .space-x-2 > * + * {
        margin-left: 0.3rem;
    }
}
</style>