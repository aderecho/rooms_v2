<template>
  <!-- MODAL OVERLAY -->
  <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">

      <!-- HEADER -->
      <div class="flex justify-between items-center border-b pb-3 mb-4">
        <h3 class="text-2xl font-semibold text-gray-800">
          {{
            type === 'view'
              ? 'View Department'
              : type === 'edit'
                ? 'Edit Department'
                : type === 'delete'
                  ? 'Delete Department'
                  : 'Create New Department'
          }}
        </h3>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
      </div>

      <!-- FORM -->
      <div class="space-y-4">
        <template v-if="type !== 'delete'">

          <!-- Department Name -->
          <div>
            <label class="block text-sm font-medium">Department Name</label>
            <input v-model="form.department_name" class="input" :readonly="isView" />
          </div>

          <!-- Department Code -->
          <div>
            <label class="block text-sm font-medium">Department Code</label>
            <input v-model="form.department_code" class="input" :readonly="isView" />
          </div>

          <!-- College -->
          <div>
            <label class="block text-sm font-medium">College</label>
            <select v-model="form.college_id" class="input" :disabled="isView">
              <option value="" disabled>Select College</option>
              <option v-for="c in colleges" :key="c.id" :value="c.id">
                {{ c.college_name }}
              </option>
            </select>
          </div>

          <!-- Department Head -->
          <div>
            <label class="block text-sm font-medium">Department Head</label>
            <select v-model="form.department_head_id" class="input" :disabled="isView">
              <option value="">None</option>
              <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.first_name }} {{ u.last_name }}
              </option>
            </select>
          </div>

          <!-- Office Location -->
          <div>
            <label class="block text-sm font-medium">Office Location</label>
            <input v-model="form.office_location" class="input" :readonly="isView" />
          </div>

          <!-- Contact Email -->
          <div>
            <label class="block text-sm font-medium">Contact Email</label>
            <input type="email" v-model="form.contact_email" class="input" :readonly="isView" />
          </div>

          <!-- Contact Phone -->
          <div>
            <label class="block text-sm font-medium">Contact Phone</label>
            <input v-model="form.contact_phone" class="input" :readonly="isView" />
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium">Description</label>
            <textarea v-model="form.description" rows="3" class="input" :readonly="isView" />
          </div>
        </template>

        <!-- DELETE CONFIRMATION -->
        <div v-if="type === 'delete'" class="bg-red-50 text-red-600 p-4 rounded">
          Are you sure you want to delete
          <strong>{{ department?.department_name }}</strong>?
          This action cannot be undone.
        </div>

        <!-- FOOTER -->
        <div class="pt-4 border-t flex justify-end gap-3">
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
    </div>
  </div>

  <!-- TOAST MESSAGES -->
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
import { computed, ref, watch } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import MessageFunction from '@/Components/MessageFunction.vue'

const props = defineProps({
  type: String, // add | edit | view | delete
  department: Object
})

const emit = defineEmits(['close'])
const page = usePage()

/* Backend props */
const colleges = computed(() => page.props.colleges)
const users = computed(() => page.props.users)

/* View mode */
const isView = computed(() => props.type === 'view')

/* Flash messages */
const localFlash = ref(page.props.flash.success || '')
const showError = ref(false)
const errorMessage = ref('')

watch(
  () => page.props.flash.success,
  val => (localFlash.value = val || ''),
  { immediate: true }
)

watch(localFlash, val => {
  if (val) setTimeout(() => (localFlash.value = ''), 4000)
})

/* Form */
const form = useForm({
  department_name: '',
  department_code: '',
  college_id: '',
  department_head_id: '',
  description: '',
  office_location: '',
  contact_email: '',
  contact_phone: '',
})

/* Populate form */
watch(
  () => [props.department, props.type],
  ([d, type]) => {
    if ((type === 'edit' || type === 'view') && d) {
      form.defaults({ ...d })
      form.reset()
    }
    if (type === 'add') {
      form.reset()
    }
  },
  { immediate: true }
)

/* Submit */
const submit = () => {
  const url =
    props.type === 'edit'
      ? `/Departments/${props.department.id}`
      : '/Departments'

  const method = props.type === 'edit' ? 'put' : 'post'

  form[method](url, {
    onSuccess: () => setTimeout(() => emit('close'), 1500),
    onError: () => {
      errorMessage.value = 'Failed to save department.'
      showError.value = true
      setTimeout(() => (showError.value = false), 4000)
    },
  })
}

/* Delete */
const destroy = () => {
  router.delete(`/Departments/${props.department.id}`, {
    onSuccess: () => setTimeout(() => emit('close'), 1500),
    onError: () => {
      errorMessage.value = 'Failed to delete department.'
      showError.value = true
      setTimeout(() => (showError.value = false), 4000)
    },
  })
}

/* Toast handlers */
const closeSuccessToast = () => (localFlash.value = '')
const closeErrorToast = () => (showError.value = false)
</script>

<style scoped>
.input {
  @apply mt-1 w-full border rounded-md p-2;
}
.btn-primary {
  @apply bg-[#7A0C23] text-white px-4 py-2 rounded-lg;
}
.btn-gray {
  @apply bg-gray-500 text-white px-4 py-2 rounded-lg;
}
.btn-danger {
  @apply bg-red-600 text-white px-4 py-2 rounded-lg;
}
</style>
