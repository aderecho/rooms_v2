<template>
  <div class="p-4 bg-white min-h-screen">
    <div class="max-w-7xl mx-auto">
      <h6 class="font-bold text-l text-[#7A0C23] mt-4">ROOM TYPE LIST 📃</h6>

         <!-- Add Button - Changed to yellow -->
       <div class="pt-7 mb-3 flex justify-end">
        <IconButton 
          @click="handleAdd" 
          icon="plus" 
          title="Add Room Type" 
          size="md" 
          color="green" 
          outlined
          class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-500 transition-colors duration-200"
        >
          Add Room Type
        </IconButton>
      </div>

      <!-- Search -->
      <div class="relative w-full sm:w-96 mb-4">
        <input type="text" v-model="search" placeholder="Search roomtypes by name..."
          class="max-w-[300px] border border-yellow-300 rounded-lg pl-10 pr-4 py-2 w-full bg-blue-50" />
        <IconButton icon="search" size="sm" color="gray" class="absolute left-3 top-1/2 -translate-y-1/2"
          @click="search" />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-2xl border border-yellow-300 overflow-hidden">
        <table class="min-w-full text-sm table-fixed">
          <thead class="bg-[#7A0C23] text-white">
            <tr>
              <th class="px-4 py-3 text-left">Room Type Name</th>
              <th class="px-4 py-3 text-left">Slug</th>
              <th class="px-4 py-3 text-left">Description</th>
              <th class="px-4 py-3 text-left">Features</th>
              <th class="px-4 py-3 text-left">Default Capacity</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="roomtypes.length === 0">
              <td colspan="6" class="text-center py-10 text-gray-500">
                No roomtypes found.
              </td>
            </tr>

            <tr v-for="roomtype in roomtypes.data" :key="roomtype.id" class="odd:bg-white even:bg-gray-100">
              <td class="px-4 py-3">{{ roomtype.room_type_name ?? 'N/A' }}</td>
              <td class="px-4 py-3">{{ roomtype.slug ?? 'N/A' }}</td>
              <td class="px-4 py-3">{{ roomtype.description ?? 'N/A' }}</td>
              <td class="px-4 py-3">{{ roomtype.features ?? 'N/A' }}</td>
              <td class="px-4 py-3 text-center">{{ roomtype.default_capacity ?? 'N/A' }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-center gap-2">
                  <IconButton icon="eye" @click="handleView(roomtype)" />
                  <IconButton icon="edit" @click="handleEdit(roomtype)" />
                  <IconButton icon="delete" @click="handleDelete(roomtype)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <RoomTypesModal v-if="isModalVisible" :type="modalType" :roomtype="modalData" @close="isModalVisible = false" />

        <!-- Pagination -->
        <div v-if="roomtypes.data" class="bg-gray-50 px-6 py-4 border-t">
          <div class="flex justify-between items-center">
            <div class="text-sm text-gray-600">
              Showing {{ roomtypes.from }} to {{ roomtypes.to }} of {{ roomtypes.total }}
            </div>

            <div class="flex gap-1">
              <button v-for="link in roomtypes.links" :key="link.label"
                @click="link.url && router.visit(link.url, { preserveState: true })" v-html="link.label"
                :disabled="!link.url" class="px-3 py-1 border rounded text-sm"
                :class="link.active ? 'bg-[#7A0C23] text-white' : 'bg-white'" />
            </div>
          </div>
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
import RoomTypesModal from './RoomTypesModal.vue'

const props = defineProps({
  search: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['openModal'])
const page = usePage()

// Backend props
const roomtypes = computed(() => page.props.roomtypes)

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

// Debounced search function
const updateSearch = debounce((val) => {
  router.get('/RoomTypes', { search: val }, { preserveState: true, replace: true })
}, 300) // 300ms debounce

// Watch the search input
watch(search, (val) => {
  updateSearch(val)
})
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
</style>
