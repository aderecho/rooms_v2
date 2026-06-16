<template>
  <div class="p-4 bg-white min-h-screen">
    <div class="max-w-7xl mx-auto">
      <h6 class="font-bold text-l text-[#7A0C23] mt-4">College List 📃</h6>

      <!-- Add Button -->
  
      <!-- Add Button - Changed to yellow -->
       <div class="pt-7 mb-3 flex justify-end">
        <IconButton 
          @click="handleAdd" 
          icon="plus" 
          title="Add College" 
          size="md" 
          color="green" 
          outlined
          class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-500 transition-colors duration-200"
        >
          Add College
        </IconButton>
      </div>

      <!-- Search -->
      <div class="relative w-full sm:w-96 mb-4">
        <input type="text" v-model="search" placeholder="Search college by name..."
          class="max-w-[300px] border border-yellow-300 rounded-lg pl-10 pr-4 py-2 w-full bg-blue-50" />
        <IconButton icon="search" size="sm" color="gray" class="absolute left-3 top-1/2 -translate-y-1/2"
          @click="search" />
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-2xl border border-yellow-300 overflow-hidden">
        <table class="min-w-full text-sm table-fixed">
          <thead class="bg-[#7A0C23] text-white">
            <tr>
              <th class="px-4 py-3 text-left">College Name</th>
              <th class="px-4 py-3 text-left">Code</th>
              <!-- <th class="px-4 py-3 text-left">Dean</th>
              <th class="px-4 py-3 text-center">Email</th> -->
              <th class="px-4 py-3 text-center">Phone</th>
              <th class="px-4 py-3 text-center">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="colleges.length === 0">
              <td colspan="6" class="text-center py-10 text-gray-500">
                No buildings found.
              </td>
            </tr>

            <tr v-for="college in colleges.data" :key="college.id" class=" odd:bg-white even:bg-gray-200 ">
              <td class="px-4 py-3 ">{{ college.college_name ?? 'N/A' }}</td>
              <td class="px-4 py-3">{{ college.college_code ?? 'N/A' }}</td>
              <!-- <td class="px-4 py-3">{{ college.dean?.username ?? 'N/A' }}</td>
              <td class="px-4 py-3 text-center">{{ college.contact_email ?? 'N/A' }}</td> -->
              <td class="px-4 py-3 text-center">{{ college.contact_phone ?? 'N/A' }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-center gap-2">
                  <IconButton icon="eye" @click="handleView(college)" />
                  <IconButton icon="edit" @click="handleEdit(college)" />
                  <IconButton icon="delete" @click="handleDelete(college)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <CollegeModal v-if="isModalVisible" :type="modalType" :college="modalData" @close="isModalVisible = false" />

        <!-- Pagination -->
        <div v-if="colleges.data" class="bg-gray-100 px-6 py-4 border-t">
          <div class="flex justify-between items-center">
            <div class="text-sm text-gray-600">
              Showing {{ colleges.from }} to {{ colleges.to }} of {{ colleges.total }}
            </div>

            <div class="flex gap-1">
              <button v-for="link in colleges.links" :key="link.label"
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
import CollegeModal from './CollegeModal.vue'

const props = defineProps({
  search: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['openModal'])
const page = usePage()

// Backend props
const colleges = computed(() => page.props.colleges)

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
  router.get('/CollegeDashboard', { search: val }, { preserveState: true, replace: true })
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
