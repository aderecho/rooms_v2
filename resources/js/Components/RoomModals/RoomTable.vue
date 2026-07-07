<template>
    <div class="space-y-4">
        <section class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="grid gap-3 md:grid-cols-[minmax(0,22rem)_minmax(12rem,18rem)]">
                <div class="relative w-full">
                    <input
                        type="text"
                        v-model="search"
                        placeholder="Search rooms by name..."
                        class="w-full rounded-full border border-slate-300 bg-emerald-50/60 py-2.5 pl-10 pr-4 text-sm text-slate-900 outline-none transition focus:border-[#005740] focus:bg-white focus:ring-4 focus:ring-[#005740]/10"
                    />
                    <IconButton
                        icon="search"
                        size="sm"
                        color="gray"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
                    />
                </div>
                <select v-model="buildingFilter" class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-[#005740] focus:ring-[#005740]">
                    <option value="">All buildings</option>
                    <option v-for="building in buildingOptions" :key="building.id" :value="String(building.id)">
                        {{ building.building_name }}
                    </option>
                </select>
            </div>

            <button type="button" class="app-button-primary" @click="handleAdd">
                + Add Rooms
            </button>
        </section>

            <!-- Table -->
            <div class="modern-table-card">
                <div class="modern-table-header">
                    <div>
                        <div class="modern-table-title">Rooms</div>
                        <p class="modern-table-subtitle">{{ rooms?.total ?? 0 }} room records</p>
                    </div>
                    <div class="modern-table-controls">
                        <span>Rows</span>
                        <select v-model="perPage" @change="updatePerPage">
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm table-fixed">
                        <thead class="bg-[#005740] text-white">
                            <tr>
                                <th class="px-4 py-3 text-left">Room Name</th>
                                <th class="px-4 py-3 text-left">Room Code</th>
                                <th class="px-4 py-3 text-left">College Name</th>
                                <th class="px-4 py-3 text-left">Assigned User</th>
                                <th class="px-4 py-3 text-left">Floor Number</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-if="!rooms?.data?.length">
                                <td colspan="6" class="text-center py-10 text-gray-500">
                                    No rooms found.
                                </td>
                            </tr>

                            <tr v-for="room in rooms.data" :key="room.id" class="odd:bg-white even:bg-slate-50">
                                <td class="px-4 py-3 font-semibold text-slate-950">{{ room.room_name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">{{ room.room_code ?? 'N/A' }}</td>
                                <td class="px-4 py-3">{{ room.college?.college_name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">
                                    {{ room.assigned_user
                                        ? `${room.assigned_user.first_name} ${room.assigned_user.last_name}`
                                    : 'N/A'
                                    }}
                                </td>
                                <td class="px-4 py-3">{{ room.floor_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center gap-2">
                                        <IconButton icon="eye" @click="handleView(room)" />
                                        <IconButton icon="edit" @click="handleEdit(room)" />
                                        <IconButton icon="delete" @click="handleDelete(room)" />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <RoomModal v-if="isModalVisible" :type="modalType" :room="modalData" @close="isModalVisible = false" />

                <!-- Pagination -->
                <div v-if="rooms.data" class="modern-table-footer">
                    <div>
                        Showing {{ rooms.from ?? 0 }} to {{ rooms.to ?? 0 }} of {{ rooms.total ?? 0 }} rooms
                    </div>

                    <div class="flex flex-wrap justify-center gap-1">
                        <button v-for="link in rooms.links" :key="link.label"
                            @click="link.url && router.visit(link.url, { preserveState: true })" v-html="link.label"
                            :disabled="!link.url" class="modern-page-button"
                            :class="{ 'modern-page-button-active': link.active }" />
                    </div>
                </div>
            </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'
import IconButton from '@/Components/IconButton.vue'
import RoomModal from './RoomModal.vue'

const props = defineProps({
    search: {
        type: String,
        default: ''
    },
    searchRoute: {
        type: String,
        default: '/Rooms'
    },
    searchParam: {
        type: String,
        default: 'search'
    },
    extraParams: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['openModal'])
const page = usePage()

// Backend props
const rooms = computed(() => page.props.rooms)
const buildingOptions = computed(() => page.props.allBuildings || page.props.buildings?.data || page.props.buildings || [])
const perPage = ref(String(page.props.filters?.room_per_page || 10))

const isModalVisible = ref(false)
const modalType = ref(null)
const modalData = ref(null)

const handleAdd = () => {
    modalType.value = 'add'
    modalData.value = null
    isModalVisible.value = true
}

const handleEdit = (b) => {
    modalType.value = 'edit'
    modalData.value = b
    isModalVisible.value = true
}

const handleView = (b) => {
    modalType.value = 'view'
    modalData.value = b
    isModalVisible.value = true
}

const handleDelete = (b) => {
    modalType.value = 'delete'
    modalData.value = b
    isModalVisible.value = true
}

// Search input
const search = ref(props.search || '')
const buildingFilter = ref(String(page.props.filters?.building_id || props.extraParams.building_id || ''))

// Debounced search function
const updateSearch = debounce((val) => {
    router.get(props.searchRoute, {
        ...props.extraParams,
        [props.searchParam]: val,
        building_id: buildingFilter.value || undefined,
        room_per_page: perPage.value,
    }, { preserveState: true, replace: true })
}, 300) // 300ms debounce

// Watch the search input
watch(search, (val) => {
    updateSearch(val)
})

watch(buildingFilter, () => {
    updateSearch(search.value)
})

const updatePerPage = () => {
    router.get(props.searchRoute, {
        ...props.extraParams,
        [props.searchParam]: search.value,
        building_id: buildingFilter.value || undefined,
        room_per_page: perPage.value,
        rooms_page: 1,
    }, { preserveState: true, replace: true })
}
</script>

<style scoped>
/* Fixed table layout to prevent movement */
table {
    table-layout: fixed;
    width: 100%;
}

/* Ensure proper text handling */
td,
th {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Keep pagination stable */
.pagination-container {
    position: sticky;
    bottom: 0;
    background: white;
    z-index: 10;
}
</style>
