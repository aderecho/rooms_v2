<template>
  <div class="space-y-4">
    <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="relative w-full sm:max-w-sm">
        <input
          type="text"
          v-model="search"
          placeholder="Search buildings by name..."
          class="w-full rounded-full border border-slate-300 bg-emerald-50/60 py-2.5 pl-10 pr-4 text-sm text-slate-900 outline-none transition focus:border-[#005740] focus:bg-white focus:ring-4 focus:ring-[#005740]/10"
        />
        <IconButton
          icon="search"
          size="sm"
          color="gray"
          class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
        />
      </div>

      <button type="button" class="app-button-primary" @click="handleAdd">
        + Add Building
      </button>
    </section>

      <!-- Table -->
      <div class="modern-table-card">
        <div class="modern-table-header">
          <div>
            <div class="modern-table-title">Buildings</div>
            <p class="modern-table-subtitle">{{ buildings?.total ?? 0 }} building records</p>
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
                <th class="px-4 py-3 text-left">Building</th>
                <th class="px-4 py-3 text-left">Address</th>
                <th class="px-4 py-3 text-left">College</th>
                <th class="px-4 py-3 text-center">Floors</th>
                <th class="px-4 py-3 text-center">Rooms</th>
                <th class="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="!buildings?.data?.length">
                <td colspan="6" class="text-center py-10 text-gray-500">
                  No buildings found.
                </td>
              </tr>

              <tr v-for="building in buildings.data" :key="building.id" class="odd:bg-white even:bg-slate-50">
                <td class="px-4 py-3 font-semibold text-slate-950">{{ building.building_name ?? 'N/A' }}</td>
                <td class="px-4 py-3">{{ building.address ?? 'N/A' }}</td>
                <td class="px-4 py-3">{{ building.college?.college_name ?? 'N/A' }}</td>
                <td class="px-4 py-3 text-center">{{ building.total_floors ?? 'N/A' }}</td>
                <td class="px-4 py-3 text-center">{{ building.total_rooms ?? 'N/A' }}</td>
                <td class="px-4 py-3">
                  <div class="flex justify-center gap-2">
                    <IconButton icon="eye" @click="handleView(building)" />
                    <IconButton icon="list" title="View Rooms" @click="handleRooms(building)" />
                    <IconButton icon="edit" @click="handleEdit(building)" />
                    <IconButton icon="delete" @click="handleDelete(building)" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <BuildingModal v-if="isModalVisible" :type="modalType" :building="modalData" @close="isModalVisible = false" />

        <!-- Pagination -->
        <div v-if="buildings.data" class="modern-table-footer">
          <div>
            Showing {{ buildings.from ?? 0 }} to {{ buildings.to ?? 0 }} of {{ buildings.total ?? 0 }} buildings
          </div>

          <div class="flex flex-wrap justify-center gap-1">
            <button v-for="link in buildings.links" :key="link.label"
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
import BuildingModal from './BuildingModal.vue'

const props = defineProps({
  search: {
    type: String,
    default: ''
  },
  searchRoute: {
    type: String,
    default: '/BuildingDashboard'
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
const buildings = computed(() => page.props.buildings)
const perPage = ref(String(page.props.filters?.building_per_page || 10))

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

const handleRooms = (building) => {
  router.get('/BuildingDashboard', {
    active_tab: 'rooms',
    building_id: building.id,
  }, {
    preserveState: true,
    replace: true,
  })
}

// Search input
const search = ref(props.search || '')

// Debounced search function
const updateSearch = debounce((val) => {
  router.get(props.searchRoute, {
    ...props.extraParams,
    [props.searchParam]: val,
    building_per_page: perPage.value,
  }, { preserveState: true, replace: true })
}, 300) // 300ms debounce

// Watch the search input
watch(search, (val) => {
  updateSearch(val)
})

const updatePerPage = () => {
  router.get(props.searchRoute, {
    ...props.extraParams,
    [props.searchParam]: search.value,
    building_per_page: perPage.value,
    buildings_page: 1,
  }, { preserveState: true, replace: true })
}
</script>

<style scoped>
/* Fixed table layout to prevent movement */
table {
  table-layout: auto;
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

/* Custom hover effect for Add Building button */
.hover-burgundy:hover {
  background-color: #005740 !important;
}
</style>
