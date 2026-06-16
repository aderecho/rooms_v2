<script setup>
import { ref, computed, defineEmits, defineProps, watch, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import IconButton from '@/Components/IconButton.vue'

// Define props from parent (matching what TermController sends)
const props = defineProps({
    initialTerms: {
        type: Array,
        default: () => []
    },
    pagination: {
        type: Object,
        default: () => ({})
    },
    filters: {
        type: Object,
        default: () => ({})
    },
    stats: {
        type: Object,
        default: () => ({})
    },
    currentTerm: {
        type: Object,
        default: null
    }
})

// Emit events for toast notifications and search
const emit = defineEmits(['status-updated', 'record-deleted', 'record-created', 'record-edited', 'error', 'search'])

// Use actual data from Laravel
const records = ref(props.initialTerms || [])
const currentPagination = ref(props.pagination || {})

// Initialize with current filters
const searchTerm = ref(props.filters.search || '')
const currentPerPage = ref(props.filters.perPage || 5)
const currentPage = ref(currentPagination.value.current_page || 1)

// --- Modal State ---
const isStatusModalOpen = ref(false)
const recordToUpdate = ref(null)

// --- Add/Edit Modal State ---
const isAddEditModalOpen = ref(false)
const isAddMode = ref(true)
const editingRecord = ref(null)

// --- Delete Modal State ---
const isDeleteModalOpen = ref(false)
const recordToDelete = ref(null)

// --- New Record Form State ---
const newRecord = ref({
    name: '',
    code: '',
    type: 'semester',
    startDate: '',
    endDate: '',
    status: 'upcoming',
    academic_year: new Date().getFullYear()
})

// Status options for frontend
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'NotActive', label: 'Not Active' },
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' }
]

// Term type options
const termTypeOptions = [
    { value: 'semester', label: 'Semester' },
    { value: 'trimester', label: 'Trimester' },
    { value: 'quarter', label: 'Quarter' },
    { value: 'summer', label: 'Summer' },
    { value: 'special', label: 'Special' }
]

// Watch for prop changes
watch(() => props.initialTerms, (newTerms) => {
    records.value = newTerms || []
})

watch(() => props.pagination, (newPagination) => {
    currentPagination.value = newPagination || {}
    currentPage.value = newPagination.current_page || 1
})

watch(() => props.filters, (newFilters) => {
    searchTerm.value = newFilters.search || ''
    currentPerPage.value = newFilters.perPage || 5
})

// --- Filtered Records (client-side for display) ---
const filteredRecords = computed(() => {
    const query = searchTerm.value.toLowerCase()
    if (!query) return records.value

    return records.value.filter(record =>
        record.name.toLowerCase().includes(query) ||
        record.code.toLowerCase().includes(query) ||
        record.type.toLowerCase().includes(query) ||
        record.status.toLowerCase().includes(query) ||
        record.startDate.includes(query) ||
        record.endDate.includes(query)
    )
})

// --- Paginated Records ---
const paginatedRecords = computed(() => {
    const start = (currentPage.value - 1) * currentPerPage.value
    const end = start + currentPerPage.value
    return filteredRecords.value.slice(start, end)
})

// --- Pagination Computed ---
const totalPages = computed(() => {
    return Math.ceil(filteredRecords.value.length / currentPerPage.value)
})

const showingRange = computed(() => {
    const start = (currentPage.value - 1) * currentPerPage.value + 1
    const end = Math.min(currentPage.value * currentPerPage.value, filteredRecords.value.length)
    const total = filteredRecords.value.length
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

// --- Status Display Functions ---
const getStatusDisplay = (status) => {
    switch (status) {
        case 'active':
            return {
                text: 'Active',
                class: 'bg-green-100 text-green-800 border border-green-400 hover:bg-green-200'
            }
        case 'NotActive':
            return {
                text: 'Not Active',
                class: 'bg-red-100 text-red-800 border border-red-400 hover:bg-red-200'
            }
        case 'upcoming':
            return {
                text: 'Upcoming',
                class: 'bg-blue-100 text-blue-800 border border-blue-400 hover:bg-blue-200'
            }
        case 'completed':
            return {
                text: 'Completed',
                class: 'bg-gray-100 text-gray-800 border border-gray-400 hover:bg-gray-200'
            }
        case 'cancelled':
            return {
                text: 'Cancelled',
                class: 'bg-yellow-100 text-yellow-800 border border-yellow-400 hover:bg-yellow-200'
            }
        default:
            return {
                text: 'Unknown',
                class: 'bg-gray-100 text-gray-800 border border-gray-400 hover:bg-gray-200'
            }
    }
}

// --- Term Type Display ---
const getTermTypeDisplay = (type) => {
    const types = {
        'semester': 'Semester',
        'trimester': 'Trimester',
        'quarter': 'Quarter',
        'summer': 'Summer',
        'special': 'Special'
    }
    return types[type] || type
}

// --- CRUD Operations ---

// Open Add Modal
const openAddModal = () => {
    isAddMode.value = true
    newRecord.value = {
        name: '',
        code: '',
        type: 'semester',
        startDate: '',
        endDate: '',
        status: 'upcoming',
        academic_year: new Date().getFullYear()
    }
    isAddEditModalOpen.value = true
}

// Open Edit Modal
const openEditModal = (record) => {
    isAddMode.value = false
    editingRecord.value = {
        ...record,
        // Ensure dates are in YYYY-MM-DD format for input[type="date"]
        startDate: record.startDate ? record.startDate.split('T')[0] : '',
        endDate: record.endDate ? record.endDate.split('T')[0] : ''
    }
    isAddEditModalOpen.value = true
}

// Save Record (Add or Edit)
const saveRecord = () => {
    const formData = isAddMode.value ? newRecord.value : editingRecord.value

    // Validate required fields
    if (!formData.name || !formData.code || !formData.startDate || !formData.endDate) {
        emit('error', 'All required fields must be filled')
        return
    }

    if (isAddMode.value) {
        emit('record-created', formData)
    } else {
        emit('record-edited', editingRecord.value.id, formData)
    }

    isAddEditModalOpen.value = false
    resetPagination()
}

// --- Status Modal Logic ---
const openStatusModal = (record) => {
    recordToUpdate.value = record
    isStatusModalOpen.value = true
}

const closeStatusModal = () => {
    isStatusModalOpen.value = false
    recordToUpdate.value = null
}

const updateStatus = (newStatusValue) => {
    if (recordToUpdate.value) {
        // For setting as current term
        if (newStatusValue === 'set-current') {
            emit('status-updated', {
                action: 'set-current',
                id: recordToUpdate.value.id,
                name: recordToUpdate.value.name
            })
        } else {
            // For changing status
            emit('status-updated', {
                action: 'change-status',
                id: recordToUpdate.value.id,
                name: recordToUpdate.value.name,
                status: newStatusValue
            })
        }
    }
    closeStatusModal()
}

// --- Delete Confirmation Logic ---
const openDeleteModal = (record) => {
    recordToDelete.value = record
    isDeleteModalOpen.value = true
}

const closeDeleteModal = () => {
    isDeleteModalOpen.value = false
    recordToDelete.value = null
}

const confirmDelete = () => {
    if (recordToDelete.value) {
        emit('record-deleted', recordToDelete.value.id)
    }
    closeDeleteModal()
}

// Handle search input
const handleSearch = () => {
    emit('search', {
        search: searchTerm.value,
        perPage: currentPerPage.value
    })
}

// Handle items per page change
const handleItemsPerPageChange = () => {
    emit('search', {
        search: searchTerm.value,
        perPage: currentPerPage.value
    })
    resetPagination()
}

// Helper computed for form binding
const formRecord = computed(() => {
    return isAddMode.value ? newRecord.value : editingRecord.value
})

// Check if record is current term
const isCurrentTerm = (record) => {
    return props.currentTerm && record.id === props.currentTerm.id
}

// Status options with "Set as Current" option
const enhancedStatusOptions = computed(() => {
    const options = [...statusOptions]
    options.push({ value: 'set-current', label: 'Set as Current Term' })
    return options
})

// Format date for display
const formatDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

// Load data from server on mount
onMounted(() => {
    // If no initial data, fetch from API
    if (records.value.length === 0) {
        fetchTerms()
    }
})

// Fetch terms from API
const fetchTerms = () => {
    router.get('/api/terms', {
        search: searchTerm.value,
        perPage: currentPerPage.value,
        page: currentPage.value
    }, {
        preserveState: true,
        replace: true,
        onSuccess: (data) => {
            if (data.props.terms) {
                records.value = data.props.terms
            }
        },
        onError: () => {
            emit('error', 'Failed to fetch terms')
        }
    })
}
</script>

<template>
    <div class="mb-6 p-6 bg-white rounded-xl shadow-lg">
        <!-- Search and Add Controls -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 space-y-3 md:space-y-0">
            <!-- Search Box -->
            <div class="relative w-full md:w-80">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <IconButton
                        icon="search"
                        title="Search records"
                        size="sm"
                        disabled
                    />
                </div>
                <input
                    type="text"
                    v-model="searchTerm"
                    @input="handleSearch"
                    placeholder="Search by name, code, or status..."
                    class="pl-10 pr-4 py-2 w-full border border-yellow-300 rounded-lg focus:ring-2 focus:ring-[#850038] focus:border-transparent outline-none bg-blue-50 shadow-sm"
                />
            </div>

         <!-- Add Button - Changed to yellow -->
       <div class="pt-7 mb-3 flex justify-end">
        <IconButton 
          @click="handleAdd" 
          icon="plus" 
          title="Add Building" 
          size="md" 
          color="green" 
          outlined
          class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-500 transition-colors duration-200"
        >
          Add Building
        </IconButton>
      </div>

        </div>

        <!-- Records Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-yellow-300">
                <thead class="bg-[#850038] text-white">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            TERM NAME
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            TERM CODE
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            TYPE
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            START DATE
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            END DATE
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            STATUS
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                            ACTIONS
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-yellow-400">
                    <tr v-for="record in paginatedRecords" :key="record.id" class="hover:bg-gray-50 transition duration-150">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ record.name }}
                            <span v-if="isCurrentTerm(record)" class="ml-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">
                                Current
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ record.code }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ getTermTypeDisplay(record.type) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ formatDate(record.startDate) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                            {{ formatDate(record.endDate) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <!-- Clickable Status Badge -->
                            <span
                                @click="openStatusModal(record)"
                                :class="[
                                    'px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full cursor-pointer transition duration-150',
                                    getStatusDisplay(record.status).class
                                ]"
                                title="Click to change status"
                            >
                                {{ getStatusDisplay(record.status).text }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-3">
                                <!-- Edit Button -->
                                <IconButton
                                    @click="openEditModal(record)"
                                    icon="edit"
                                    title="Edit Term"
                                    size="sm"
                                    color="green"
                                    class="hover:scale-110 transition-transform"
                                />

                                <!-- Delete Button -->
                                <IconButton
                                    @click="openDeleteModal(record)"
                                    icon="delete"
                                    title="Delete Term"
                                    size="sm"
                                    color="red"
                                    class="hover:scale-110 transition-transform"
                                />
                            </div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-if="filteredRecords.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="text-gray-500">
                                <IconButton
                                    icon="search"
                                    title="No Results"
                                    size="lg"
                                    disabled
                                    class="mx-auto mb-3 opacity-50"
                                />
                                <p class="text-base font-medium mb-1">No terms found</p>
                                <p class="text-sm mb-4">Try adjusting your search or add a new term</p>
                                <IconButton
                                    @click="openAddModal"
                                    icon="plus"
                                    title="Add New Term"
                                    size="sm"
                                    color="green"
                                    outlined
                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md"
                                >
                                    Add New Term
                                </IconButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div v-if="filteredRecords.length > 0" class="bg-gray-50 px-6 py-4 border-t border-gray-200 mt-4">
            <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
                <!-- Showing range -->
                <div class="text-sm text-gray-600">
                    Showing {{ showingRange.start }} to {{ showingRange.end }} of {{ showingRange.total }} entries
                </div>

                <!-- Items per page selector -->
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">Show:</span>
                    <select
                        v-model="currentPerPage"
                        @change="handleItemsPerPageChange"
                        class="text-sm border border-gray-300 rounded px-2 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-[#850038] focus:border-transparent"
                    >
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="20">20</option>
                    </select>
                    <span class="text-sm text-gray-600">per page</span>
                </div>

                <!-- Page navigation -->
                <div class="flex items-center space-x-2">
                    <!-- Previous button -->
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
                        <button
                            v-for="page in totalPages"
                            :key="page"
                            @click="goToPage(page)"
                            :class="[
                                'px-3 py-1.5 rounded border text-sm font-medium min-w-[36px] transition-colors duration-150',
                                currentPage === page
                                    ? 'bg-[#850038] text-white border-[#850038]'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                            ]"
                        >
                            {{ page }}
                        </button>
                    </div>

                    <!-- Next button -->
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
                    Filtered Results: <span class="font-semibold text-[#850038]">{{ filteredRecords.length }}</span>
                    | Total Terms in DB: <span class="font-semibold text-[#850038]">{{ stats.total || 0 }}</span>
                </p>
            </div>
        </div>

        <!-- MODALS -->

        <!-- Status Change Modal -->
        <div v-if="isStatusModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="closeStatusModal">
            <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm transform transition-all duration-300 scale-100" @click.stop>
                <h3 class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">Manage: {{ recordToUpdate?.name }}</h3>

                <div class="space-y-3">
                    <div v-for="option in enhancedStatusOptions" :key="option.value" class="mb-2">
                        <button
                            @click="updateStatus(option.value)"
                            :class="[
                                'w-full text-center py-2 px-4 rounded-lg font-medium transition-colors',
                                option.value === 'set-current'
                                    ? 'bg-purple-100 text-purple-800 border border-purple-400 hover:bg-purple-200'
                                    : getStatusDisplay(option.value).class.replace('cursor-pointer', '').replace('hover:bg-green-200', 'hover:bg-green-300 hover:text-green-900').replace('hover:bg-red-200', 'hover:bg-red-300 hover:text-red-900'),
                                recordToUpdate?.status === option.value ? 'ring-2 ring-offset-2 ring-[#850038]' : 'hover:shadow-md'
                            ]"
                        >
                            {{ option.value === 'set-current' ? 'Set as Current Term' : `Set to ${option.label}` }}
                        </button>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t flex justify-end">
                    <IconButton
                        @click="closeStatusModal"
                        icon="times"
                        title="Cancel"
                        size="sm"
                        color="gray"
                        outlined
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition"
                    >
                        Cancel
                    </IconButton>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="isAddEditModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="isAddEditModalOpen = false">
            <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md transform transition-all duration-300 scale-100 max-h-[90vh] overflow-y-auto" @click.stop>
                <h3 class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">
                    {{ isAddMode ? 'Add New Term' : 'Edit Term: ' + editingRecord?.name }}
                </h3>

                <form @submit.prevent="saveRecord" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Term Name *</label>
                        <input
                            type="text"
                            v-model="formRecord.name"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#850038] focus:border-transparent outline-none"
                            placeholder="e.g., First Semester 2024"
                        />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Term Code *</label>
                            <input
                                type="text"
                                v-model="formRecord.code"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#850038] focus:border-transparent outline-none"
                                placeholder="e.g., 2024-1"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                            <input
                                type="number"
                                v-model="formRecord.academic_year"
                                min="2000"
                                max="2100"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#850038] focus:border-transparent outline-none"
                                placeholder="e.g., 2024"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Term Type</label>
                        <select
                            v-model="formRecord.type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#850038] focus:border-transparent outline-none"
                        >
                            <option v-for="option in termTypeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
                            <input
                                type="date"
                                v-model="formRecord.startDate"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#850038] focus:border-transparent outline-none"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label>
                            <input
                                type="date"
                                v-model="formRecord.endDate"
                                required
                                :min="formRecord.startDate"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#850038] focus:border-transparent outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select
                            v-model="formRecord.status"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#850038] focus:border-transparent outline-none"
                        >
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                        <IconButton
                            type="button"
                            @click="isAddEditModalOpen = false"
                            icon="times"
                            title="Cancel"
                            size="sm"
                            color="gray"
                            outlined
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition"
                        >
                            Cancel
                        </IconButton>

                        <IconButton
                            type="submit"
                            icon="check"
                            :title="isAddMode ? 'Add Term' : 'Save Changes'"
                            size="sm"
                            color="green"
                            outlined
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition"
                        >
                            {{ isAddMode ? 'Add Term' : 'Save Changes' }}
                        </IconButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40" @click.self="closeDeleteModal">
            <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm transform transition-all duration-300 scale-100" @click.stop>
                <h3 class="text-xl font-semibold text-red-700 border-b pb-2 mb-4">Confirm Deletion</h3>

                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-5">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <IconButton
                                icon="warning"
                                title="Warning"
                                size="sm"
                                disabled
                            />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Delete <span class="font-semibold">"{{ recordToDelete?.name }}"</span>?
                                <span class="block text-yellow-600 text-xs mt-1">This action cannot be undone.</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <IconButton
                        @click="closeDeleteModal"
                        icon="times"
                        title="Cancel"
                        size="sm"
                        color="gray"
                        outlined
                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg transition"
                    >
                        Cancel
                    </IconButton>

                    <IconButton
                        @click="confirmDelete"
                        icon="delete"
                        title="Delete"
                        color="white"
                        size="sm"
                        outlined
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition shadow-md"
                    >
                        Delete
                    </IconButton>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Modal Transition Styles */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

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
}
</style>
