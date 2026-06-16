<template>
    <div class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">

            <!-- Header -->
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-2xl font-semibold text-gray-800">
                    {{
                        type === 'view'
                            ? 'View College'
                            : type === 'edit'
                                ? 'Edit College'
                                : type === 'delete'
                                    ? 'Delete College'
                                    : 'Create New College'
                    }}
                </h3>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>

            <!-- Form -->
            <div class="space-y-4">
                <template v-if="type !== 'delete'">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">College Name</label>
                        <input type="text" v-model="formCollege.college_name" class="mt-1 w-full border rounded-md p-3"
                            :readonly="type === 'view'" placeholder="College Name" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Code</label>
                        <textarea v-model="formCollege.college_code" rows="2" class="mt-1 w-full border rounded-md p-3"
                            :readonly="type === 'view'" placeholder="Enter college code..."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea v-model="formCollege.description" rows="3" class="mt-1 w-full border rounded-md p-3"
                            :readonly="type === 'view'" placeholder="Type college description..."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="text" v-model="formCollege.contact_email"
                                class="mt-1 w-full border rounded-md p-2" :readonly="type === 'view'" min="0" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" v-model="formCollege.contact_phone"
                                class="mt-1 w-full border rounded-md p-2" :readonly="type === 'view'" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Dean</label>
                        <select v-model="formCollege.dean_id" class="mt-1 w-full border rounded-md p-2"
                            :disabled="type === 'view'">
                            // <option value="" disabled>Select Dean</option>
                            <option :value="null">No Dean Assigned</option>
                            <option v-for="d in dean" :key="d.id" :value="d.id">
                                {{ d.username }}
                            </option>
                        </select>
                    </div>

                </template>
                <div v-if="type === 'delete'" class="text-red-600 text-sm bg-red-50 p-3 rounded">
                    Are you sure you want to delete
                    <strong>{{ college?.college_name }}</strong>?
                    This action cannot be undone.
                </div>

                <div class="pt-4 border-t flex justify-end space-x-3">
                    <button @click="emit('close')" class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>

                    <button v-if="type === 'add' || type === 'edit'" @click="submitForm"
                        class="bg-[#7A0C23] text-white px-4 py-2 rounded-lg" :disabled="formCollege.processing">
                        {{ type === 'edit' ? 'Update' : 'Create' }}
                    </button>

                    <button v-if="type === 'delete'" @click="deleteCollege"
                        class="bg-red-600 text-white px-4 py-2 rounded-lg">
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

const props = defineProps({
    type: { type: String, required: true },
    college: { type: Object, default: null }
})
const emit = defineEmits(['close'])

const page = usePage()
const dean = computed(() => page.props.dean)

const localFlash = ref(page.props.flash.success || '')

watch(
    () => page.props.flash.success,
    (val) => {
        localFlash.value = val || ''
    },
    { immediate: true }
)

watch(localFlash, (val) => {
    if (val) setTimeout(() => (localFlash.value = ''), 5000)
})

const formCollege = useForm({
    dean_id: null,
    college_name: '',
    college_code: '',
    description: '',
    contact_email: '',
    contact_phone: '',
})

const showError = ref(false)
const errorMessage = ref('')

watch(
    () => props.type,
    () => {
        localFlash.value = ''
        showError.value = false
    },
    { immediate: true }
)

watch(
    () => [props.college, props.type],
    ([b, type]) => {
        if ((type === 'view' || type === 'edit') && b) {
            formCollege.dean_id = b.dean_id
            formCollege.college_name = b.college_name
            formCollege.college_code = b.college_code
            formCollege.description = b.description
            formCollege.contact_email = b.contact_email
            formCollege.contact_phone = b.contact_phone
        }

        if (type === 'add') {
            formCollege.reset({
                dean_id: null,
                college_name: '',
                college_code: '',
                description: '',
                contact_email: '',
                contact_phone: '+63',
            })
        }
    },
    { immediate: true }
)

function submitForm() {
    if (props.type === 'view' || props.type === 'delete') return

    const method = props.type === 'edit' ? 'put' : 'post'
    const url =
        props.type === 'edit'
            ? `/CollegeDashboard/${props.college.id}`
            : '/CollegeDashboard'

    formCollege[method](url, {
        onSuccess: () => {
            setTimeout(() => emit('close'), 1500)
        },
        onError: () => {
            errorMessage.value = 'Failed to save college.'
            showError.value = true
            setTimeout(() => (showError.value = false), 5000)
        },
    })
}

function deleteCollege() {
    if (!props.college) return

    router.delete(`/CollegeDashboard/${props.college.id}`, {
        onSuccess: () => {
            setTimeout(() => emit('close'), 1500)
        },
        onError: () => {
            errorMessage.value = 'Failed to delete college.'
            showError.value = true
            setTimeout(() => (showError.value = false), 5000)
        },
    })
}

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
