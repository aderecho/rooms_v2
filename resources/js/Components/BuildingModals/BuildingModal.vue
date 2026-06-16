<template>
  <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">

      <!-- Header -->
      <div class="flex justify-between items-center border-b pb-3 mb-4">
        <h3 class="text-2xl font-semibold text-gray-800">
          {{
            type === 'view'
              ? 'View Building'
              : type === 'edit'
                ? 'Edit Building'
                : type === 'delete'
                  ? 'Delete Building'
                  : 'Create New Building'
          }}
        </h3>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
      </div>

      <!-- Form -->
      <div class="space-y-4">
        <template v-if="type !== 'delete'">
          <!-- Building Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Building Name</label>
            <input type="text" v-model="formBuilding.building_name" class="mt-1 w-full border rounded-md p-3"
              :readonly="type === 'view'" placeholder="Building Name" />
          </div>

          <!-- Address -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Address</label>
            <textarea v-model="formBuilding.address" rows="2" class="mt-1 w-full border rounded-md p-3"
              :readonly="type === 'view'" placeholder="Enter building address..."></textarea>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <textarea v-model="formBuilding.description" rows="3" class="mt-1 w-full border rounded-md p-3"
              :readonly="type === 'view'" placeholder="Type building description..."></textarea>
          </div>

          <!-- Total Floors & Total Rooms -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Total Floors</label>
              <input type="number" v-model.number="formBuilding.total_floors" class="mt-1 w-full border rounded-md p-2"
                :readonly="type === 'view'" min="0" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Total Rooms</label>
              <input type="number" v-model.number="formBuilding.total_rooms" class="mt-1 w-full border rounded-md p-2"
                :readonly="type === 'view'" min="0" />
            </div>
          </div>

          <!-- Elevator & Parking -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Has Elevator</label>
              <select v-model.number="formBuilding.has_elevator" class="mt-1 w-full border rounded-md p-2"
                :disabled="type === 'view'">
                <option :value="1">Yes</option>
                <option :value="0">No</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Has Parking</label>
              <select v-model.number="formBuilding.has_parking" class="mt-1 w-full border rounded-md p-2"
                :disabled="type === 'view'">
                <option :value="1">Yes</option>
                <option :value="0">No</option>
              </select>
            </div>
          </div>

          <!-- Number of CR & Ramps -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Number of CR</label>
              <input type="number" v-model.number="formBuilding.restroom_count"
                class="mt-1 w-full border rounded-md p-2" :readonly="type === 'view'" min="0" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Ramps</label>
              <input type="number" v-model.number="formBuilding.ramp_count" class="mt-1 w-full border rounded-md p-2"
                :readonly="type === 'view'" min="0" />
            </div>
          </div>

          <!-- Colleges -->
          <div>
            <label class="block text-sm font-medium text-gray-700">College</label>
            <select v-model="formBuilding.college_id" class="mt-1 w-full border rounded-md p-2"
              :disabled="type === 'view'">
              <option value="" disabled>Select College</option>
              <option v-for="c in colleges" :key="c.id" :value="c.id">
                {{ c.college_name }}
              </option>
            </select>
          </div>
        </template>
        <div v-if="type === 'delete'" class="text-red-600 text-sm bg-red-50 p-3 rounded">
          Are you sure you want to delete
          <strong>{{ building?.building_name }}</strong>?
          This action cannot be undone.
        </div>

        <!-- Footer Buttons -->
        <div class="pt-4 border-t flex justify-end space-x-3">
          <button @click="emit('close')" class="bg-gray-500 text-white px-4 py-2 rounded-lg">
            Cancel
          </button>

          <!-- ADD / EDIT -->
          <button v-if="type === 'add' || type === 'edit'" @click="submitForm"
            class="bg-[#7A0C23] text-white px-4 py-2 rounded-lg" :disabled="formBuilding.processing">
            {{ type === 'edit' ? 'Update' : 'Create' }}
          </button>

          <!-- DELETE -->
          <button v-if="type === 'delete'" @click="deleteBuilding" class="bg-red-600 text-white px-4 py-2 rounded-lg">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
  <MessageFunction :show-info="!!localFlash" :info-message="localFlash" :show-error="showError"
    :error-message="errorMessage" @close-info="closeSuccessToast" @close-error="closeErrorToast" />


</template>

<script setup>
import { usePage, useForm, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import MessageFunction from '@/Components/MessageFunction.vue'

// --- Props & Emits ---
const props = defineProps({
  type: { type: String, required: true }, // 'add' | 'edit' | 'view' | 'delete'
  building: { type: Object, default: null }
})
const emit = defineEmits(['close'])

// --- Backend props ---
const page = usePage()
const colleges = computed(() => page.props.colleges)

// --- Local reactive flash for success messages ---
const localFlash = ref(page.props.flash.success || '')

// Watch for new backend flashes
watch(
  () => page.props.flash.success,
  (val) => {
    localFlash.value = val || ''
  },
  { immediate: true }
)

// Auto-hide success toast after 5 seconds
watch(localFlash, (val) => {
  if (val) setTimeout(() => (localFlash.value = ''), 5000)
})

// --- Form ---
const formBuilding = useForm({
  building_name: '',
  address: '',
  description: '',
  total_floors: 0,
  total_rooms: 0,
  has_elevator: 1,
  has_parking: 1,
  restroom_count: 0,
  ramp_count: 0,
  college_id: 1,
})

// --- Reset flash & error when modal opens ---
const showError = ref(false)
const errorMessage = ref('')

watch(
  () => props.type,
  () => {
    localFlash.value = '' // reset success message
    showError.value = false // reset error
  },
  { immediate: true }
)

// --- Watch building + type for form population ---
watch(
  () => [props.building, props.type],
  ([b, type]) => {
    if ((type === 'view' || type === 'edit') && b) {
      formBuilding.building_name = b.building_name
      formBuilding.address = b.address
      formBuilding.description = b.description
      formBuilding.total_floors = b.total_floors
      formBuilding.total_rooms = b.total_rooms
      formBuilding.has_elevator = Number(b.has_elevator)
      formBuilding.has_parking = Number(b.has_parking)
      formBuilding.restroom_count = b.restroom_count
      formBuilding.ramp_count = b.ramp_count
      formBuilding.college_id = b.college_id
    }

    if (type === 'add') {
      formBuilding.reset({
        building_name: '',
        address: '',
        description: '',
        total_floors: 0,
        total_rooms: 0,
        has_elevator: 1,
        has_parking: 1,
        restroom_count: 0,
        ramp_count: 0,
        college_id: 1,
      })
    }
  },
  { immediate: true }
)

// --- Submit (ADD / EDIT) ---
function submitForm() {
  if (props.type === 'view' || props.type === 'delete') return

  const method = props.type === 'edit' ? 'put' : 'post'
  const url =
    props.type === 'edit'
      ? `/BuildingDashboard/${props.building.id}`
      : '/BuildingDashboard'

  formBuilding[method](url, {
    onSuccess: () => {
      // backend flash sets success, toast auto-shows
      setTimeout(() => emit('close'), 1500) // optional: close modal shortly after
    },
    onError: () => {
      errorMessage.value = 'Failed to save building.'
      showError.value = true
      setTimeout(() => (showError.value = false), 5000) // auto-hide error
    },
  })
}

// --- DELETE ---
function deleteBuilding() {
  if (!props.building) return

  router.delete(`/BuildingDashboard/${props.building.id}`, {
    onSuccess: () => {
      // backend flash sets success, toast auto-shows
      setTimeout(() => emit('close'), 1500)
    },
    onError: () => {
      errorMessage.value = 'Failed to delete building.'
      showError.value = true
      setTimeout(() => (showError.value = false), 5000) // auto-hide error
    },
  })
}

// --- Toast close handlers ---
const closeSuccessToast = () => (localFlash.value = '')
const closeErrorToast = () => (showError.value = false)
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
