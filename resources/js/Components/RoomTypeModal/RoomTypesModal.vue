<template>
  <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">

      <!-- Header -->
      <div class="flex justify-between items-center border-b pb-3 mb-4">
        <h3 class="text-2xl font-semibold text-gray-800">
          {{
            type === 'view'
              ? 'View Room Type'
              : type === 'edit'
                ? 'Edit Room Type'
                : type === 'delete'
                  ? 'Delete Room Type'
                  : 'Create New Room Type'
          }}
        </h3>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
      </div>

      <!-- Form -->
      <div class="space-y-4">
        <template v-if="type !== 'delete'">
          <!-- Room Type Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Room Type Name</label>
            <input
              type="text"
              v-model="formRoomType.room_type_name"
              class="mt-1 w-full border rounded-md p-3"
              :readonly="type === 'view'"
              placeholder="Room Type Name"
            />
          </div>

          <!-- Slug -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Slug</label>
            <input
              type="text"
              v-model="formRoomType.slug"
              class="mt-1 w-full border rounded-md p-3"
              :readonly="type === 'view'"
              placeholder="room-type-slug"
            />
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <textarea
              v-model="formRoomType.description"
              rows="3"
              class="mt-1 w-full border rounded-md p-3"
              :readonly="type === 'view'"
              placeholder="Room type description..."
            />
          </div>

          <!-- Features -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Features</label>
            <textarea
              v-model="formRoomType.features"
              rows="3"
              class="mt-1 w-full border rounded-md p-3"
              :readonly="type === 'view'"
              placeholder="e.g. Aircon, Projector, Whiteboard"
            />
          </div>

          <!-- Default Capacity -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Default Capacity</label>
            <input
              type="number"
              v-model.number="formRoomType.default_capacity"
              class="mt-1 w-full border rounded-md p-2"
              :readonly="type === 'view'"
              min="0"
            />
          </div>
        </template>

        <!-- Delete Confirmation -->
        <div v-if="type === 'delete'" class="text-red-600 text-sm bg-red-50 p-3 rounded">
          Are you sure you want to delete
          <strong>{{ roomtype?.room_type_name }}</strong>?
          This action cannot be undone.
        </div>

        <!-- Footer Buttons -->
        <div class="pt-4 border-t flex justify-end space-x-3">
          <button @click="emit('close')" class="bg-gray-500 text-white px-4 py-2 rounded-lg">
            Cancel
          </button>

          <!-- ADD / EDIT -->
          <button
            v-if="type === 'add' || type === 'edit'"
            @click="submitForm"
            class="bg-[#7A0C23] text-white px-4 py-2 rounded-lg"
            :disabled="formRoomType.processing"
          >
            {{ type === 'edit' ? 'Update' : 'Create' }}
          </button>

          <!-- DELETE -->
          <button
            v-if="type === 'delete'"
            @click="deleteRoomType"
            class="bg-red-600 text-white px-4 py-2 rounded-lg"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toasts -->
  <MessageFunction
    :show-info="!!localFlash"
    :info-message="localFlash"
    :show-error="showError"
    :error-message="errorMessage"
    @close-info="closeSuccessToast"
    @close-error="closeErrorToast"
  />
</template>

<script setup>
import { usePage, useForm, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import MessageFunction from '@/Components/MessageFunction.vue'

// --- Props & Emits ---
const props = defineProps({
  type: { type: String, required: true }, // add | edit | view | delete
  roomtype: { type: Object, default: null }
})
const emit = defineEmits(['close'])

// --- Backend props ---
const page = usePage()

// --- Flash messages ---
const localFlash = ref(page.props.flash.success || '')

watch(
  () => page.props.flash.success,
  (val) => (localFlash.value = val || ''),
  { immediate: true }
)

watch(localFlash, (val) => {
  if (val) setTimeout(() => (localFlash.value = ''), 5000)
})

// --- Error handling ---
const showError = ref(false)
const errorMessage = ref('')

// --- Form ---
const formRoomType = useForm({
  room_type_name: '',
  slug: '',
  description: '',
  features: '',
  default_capacity: 0,
})

// Reset flash/errors on modal open
watch(
  () => props.type,
  () => {
    localFlash.value = ''
    showError.value = false
  },
  { immediate: true }
)

// Populate form
watch(
  () => [props.roomtype, props.type],
  ([r, type]) => {
    if ((type === 'view' || type === 'edit') && r) {
      formRoomType.room_type_name = r.room_type_name
      formRoomType.slug = r.slug
      formRoomType.description = r.description
      formRoomType.features = r.features
      formRoomType.default_capacity = r.default_capacity
    }

    if (type === 'add') {
      formRoomType.reset()
    }
  },
  { immediate: true }
)

// --- Submit ---
function submitForm() {
  if (props.type === 'view' || props.type === 'delete') return

  const method = props.type === 'edit' ? 'put' : 'post'
  const url =
    props.type === 'edit'
      ? `/RoomTypes/${props.roomtype.id}`
      : '/RoomTypes'

  formRoomType[method](url, {
    onSuccess: () => setTimeout(() => emit('close'), 1500),
    onError: () => {
      errorMessage.value = 'Failed to save room type.'
      showError.value = true
      setTimeout(() => (showError.value = false), 5000)
    },
  })
}

// --- Delete ---
function deleteRoomType() {
  if (!props.roomtype) return

  router.delete(`/RoomTypes/${props.roomtype.id}`, {
    onSuccess: () => setTimeout(() => emit('close'), 1500),
    onError: () => {
      errorMessage.value = 'Failed to delete room type.'
      showError.value = true
      setTimeout(() => (showError.value = false), 5000)
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
