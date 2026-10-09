<template>
  <Teleport to="body">
  <ModalDialog class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-[80] p-3 sm:p-6">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[calc(100dvh-3rem)] flex flex-col overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="room-modal-title">

      <!-- Header -->
      <div class="flex shrink-0 justify-between items-center border-b border-[#005740] bg-[#005740] px-6 py-4">
        <h3 id="room-modal-title" class="text-2xl font-semibold text-white">
          {{
            type === 'view'
              ? 'View Room'
              : type === 'edit'
                ? 'Edit Room'
                : type === 'delete'
                  ? 'Delete Room'
                  : 'Create New Room'
          }}
        </h3>
        <button @click="emit('close')" class="text-white/80 hover:text-white text-xl" aria-label="Close room panel">✕</button>
      </div>

      <!-- FORM -->
      <div class="space-y-4 overflow-y-auto min-h-0 px-6 py-4">
        <template v-if="type !== 'delete'">

          <!-- Room Name -->
          <div>
            <label class="block text-sm font-medium">Room Name</label>
            <input v-model="form.room_name" class="input" :readonly="isView" />
          </div>

          <!-- Room Code -->
          <div>
            <label class="block text-sm font-medium">Room Code</label>
            <input v-model="form.room_code" class="input" :readonly="isView" />
          </div>

          <!-- Floor / Capacity -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium">Floor Number</label>
              <input type="number" v-model.number="form.floor_number" class="input" :readonly="isView" />
            </div>
            <div>
              <label class="block text-sm font-medium">Capacity</label>
              <input type="number" v-model.number="form.capacity" class="input" :readonly="isView" />
            </div>
          </div>

          <div>
            <label for="room-visibility" class="block text-sm font-medium">Room Visibility</label>
            <select id="room-visibility" v-model="form.is_public" class="input" :disabled="isView"
              :aria-invalid="!!form.errors.is_public" :aria-describedby="form.errors.is_public ? 'room-visibility-error' : undefined">
              <option :value="0">Private</option>
              <option :value="1">Public</option>
            </select>
            <p v-if="form.errors.is_public" id="room-visibility-error" class="mt-1 text-sm text-red-600">{{ form.errors.is_public }}</p>
          </div>

          <!-- Location -->
          <div>
            <label class="block text-sm font-medium">Location</label>
            <input v-model="form.location" class="input" :readonly="isView" />
          </div>

          <!-- Relations -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium">Building</label>
              <select v-model="form.building_id" class="input" :disabled="isView">
                <option value="" disabled>Select Building</option>
                <option v-for="b in buildings" :key="b.id" :value="b.id">
                  {{ b.building_name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium">Room Type</label>
              <select v-model="form.room_type_id" class="input" :disabled="isView">
                <option value="" disabled>Select Room Type</option>
                <option v-for="rt in roomTypes" :key="rt.id" :value="rt.id">
                  {{ rt.room_type_name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium">College</label>
              <select v-model="form.college_id" class="input" :title="colleges?.find(c => c.id === form.college_id)?.college_name" :disabled="isView">
                <option value="" disabled>Select College</option>
                <option v-for="c in colleges" :key="c.id" :value="c.id">
                  {{ c.college_name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium">Department</label>
              <select v-model="form.department_id" class="input" :title="departments?.find(d => d.id === form.department_id)?.department_name" :disabled="isView">
                <option value="" disabled>Select Department</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">
                  {{ d.department_name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Assigned User -->
          <div>
            <label class="block text-sm font-medium">Assigned User</label>
            <select v-model="form.assigned_user_id" class="input" :disabled="isView">
              <option value="">None</option>
              <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.first_name }} {{ u.last_name }}
              </option>
            </select>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium">Description</label>
            <textarea v-model="form.description" rows="3" class="input" :readonly="isView" />
          </div>

          <!-- Equipments -->
          <EquipmentTagInput
            v-model="equipmentList"
            label="Equipments"
            placeholder="Type equipment name (e.g. TV, Chairs)…"
            :disabled="isView"
          />
        </template>

        <!-- DELETE CONFIRM -->
        <div v-if="type === 'delete'" class="bg-red-50 text-red-600 p-4 rounded">
          Are you sure you want to delete
          <strong>{{ room?.room_name }}</strong>?
        </div>

        </div>
        <!-- FOOTER -->
        <div class="shrink-0 px-6 py-4 border-t flex justify-end gap-3">
          <button @click="emit('close')" class="btn-gray">Cancel</button>

          <button
            v-if="type === 'add' || type === 'edit'"
            @click="submit"
            class="btn-primary"
            :disabled="form.processing"
          >
            {{ type === 'edit' ? 'Update' : 'Create' }}
          </button>

          <button
            v-if="type === 'delete'"
            @click="destroy"
            class="btn-danger"
          >
            Delete
          </button>
        </div>
    </div>
  </ModalDialog>
  </Teleport>

    <!-- Toast Messages -->
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
import ModalDialog from '@/Components/ModalDialog.vue'
import { computed, watch, ref } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import MessageFunction from '@/Components/MessageFunction.vue'
import EquipmentTagInput from '@/Components/RoomModals/EquipmentTagInput.vue'

const props = defineProps({
  type: String,
  room: Object
})
const emit = defineEmits(['close'])
const page = usePage()

// Backend props
const buildings = computed(() => page.props.allBuildings || page.props.buildings?.data || page.props.buildings || [])
const colleges = computed(() => page.props.colleges)
const departments = computed(() => page.props.departments)
const roomTypes = computed(() => page.props.roomTypes)
const users = computed(() => page.props.users)

const isView = computed(() => props.type === 'view')

/* --------------------
   FLASH / ERROR STATE
-------------------- */
const localFlash = ref(page.props.flash.success || '')
const showError = ref(false)
const errorMessage = ref('')

watch(
  () => page.props.flash.success,
  (val) => (localFlash.value = val || ''),
  { immediate: true }
)

watch(localFlash, (val) => {
  if (val) setTimeout(() => (localFlash.value = ''), 5000)
})

/* --------------------
   FORM
-------------------- */
const initialRoomForm = {
  is_public: 0,
  room_name: '',
  room_code: '',
  building_id: '',
  college_id: '',
  department_id: '',
  room_type_id: '',
  assigned_user_id: '',
  floor_number: 0,
  location: '',
  capacity: 0,
  description: '',
  equipments: [],
}
const form = useForm({ ...initialRoomForm })

const equipmentList = computed({
  get() {
    const value = form.equipments
    if (Array.isArray(value)) return value.map((item) => typeof item === 'string' ? item : item.name).filter(Boolean)
    if (typeof value === 'string' && value.trim()) {
      return value.split(/[\r\n,;]+/).map((s) => s.trim()).filter(Boolean)
    }
    return []
  },
  set(list) {
    form.equipments = list
  },
})

// Populate on edit/view
watch(
  () => [props.room, props.type],
  ([room, type]) => {
    if ((type === 'edit' || type === 'view') && room) {
      form.defaults({ ...initialRoomForm, ...room, is_public: Number(room.is_public ?? 0) })
      form.reset()
    }

    if (type === 'add') {
      form.defaults({ ...initialRoomForm })
      form.reset()
    }

    form.clearErrors()

    // reset messages on open
    localFlash.value = ''
    showError.value = false
  },
  { immediate: true }
)

/* --------------------
   SUBMIT
-------------------- */
const submit = () => {
  const url =
    props.type === 'edit'
      ? `/Rooms/${props.room.id}`
      : '/Rooms'

  const method = props.type === 'edit' ? 'put' : 'post'

  form.equipments = equipmentList.value

  form[method](url, {
    onSuccess: () => {
      setTimeout(() => emit('close'), 1500)
    },
    onError: (errors) => {
      const equipmentError = Object.entries(errors || {}).find(([key]) =>
        key.startsWith('equipments')
      )
      errorMessage.value = equipmentError
        ? equipmentError[1]
        : errors.is_public || 'Failed to save room. Check that all equipment items are valid.'
      showError.value = true
      setTimeout(() => (showError.value = false), 5000)
    },
  })
}

/* --------------------
   DELETE
-------------------- */
const destroy = () => {
  router.delete(`/Rooms/${props.room.id}`, {
    onSuccess: () => {
      setTimeout(() => emit('close'), 1500)
    },
    onError: () => {
      errorMessage.value = 'Failed to delete room.'
      showError.value = true
      setTimeout(() => (showError.value = false), 5000)
    },
  })
}

/* --------------------
   TOAST HANDLERS
-------------------- */
const closeSuccessToast = () => (localFlash.value = '')
const closeErrorToast = () => (showError.value = false)
</script>


<style scoped>
.input {
  @apply mt-1 min-w-0 w-full border rounded-md p-2;
}
.btn-primary {
  @apply bg-[#005740] text-white px-4 py-2 rounded-lg;
}
.btn-gray {
  @apply bg-gray-500 text-white px-4 py-2 rounded-lg;
}
.btn-danger {
  @apply bg-red-600 text-white px-4 py-2 rounded-lg;
}
</style>
