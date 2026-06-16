<template>
  <div class="flex pt-14 min-h-screen transition-all duration-300">
    <Sidebar :sidebarOpen="sidebarOpen" />

    <div class="flex flex-col flex-1 overflow-hidden">
      <Navbar @toggleSidebar="toggleSidebar" />

      <main class="flex-1 overflow-y-auto p-0 md:p-6 bg-gray-200">
        <div class="bg-white rounded-lg shadow-md p-0">
          <div class="p-6">
            <div class="mb-2">
              <div class="p-6">
                <div class="mb-1">
                  <h1 class="ml-2 text-xl md:text-2xl font-bold text-[#7A0C23] mb-1">Terms Management</h1>
                  <div class="absolute right-2 top-16 z-20">
                    <div class="text-sm text-gray-500 whitespace-nowrap"></div>
                  </div>

                  <div class="mt-12 absolute right-8 top-10 z-20">
                    <div class="mr-7 mt-12 text-sm text-gray-500 whitespace-nowrap">
                      <span>UPCEBU > TERMS</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="ml-5 flex items-center bg-gray-100 rounded-full w-400 md:w-fit p-1 mb-2">
              <button
                @click="setActiveTab('list')"
                :class="[
                  'flex-1 font-medium py-2 px-6 rounded-full transition text-sm md:text-base',
                  activeTab === 'list' ? 'bg-[#850038] text-white' : 'text-black hover:bg-gray-200'
                ]"
              >
                List of Records
              </button>
            </div>

            <Transition name="fade" mode="out-in">
              <TermsTable
                v-if="activeTab === 'list'"
                :initial-terms="terms"
                :pagination="pagination"
                :filters="filters"
                :stats="stats"
                :current-term="current_term"
                :status-options="status_options"
                :term-type-options="term_type_options"
                @status-updated="handleStatusUpdated"
                @record-deleted="handleRecordDeleted"
                @record-created="handleRecordCreated"
                @record-edited="handleRecordEdited"
                @search="handleSearch"
                @error="handleError"
              />
            </Transition>
          </div>
        </div>
      </main>
    </div>

    <!-- MessageFunction Component -->
    <MessageFunction
      :show-create-success="showCreateSuccess"
      :show-edit-success="showEditSuccess"
      :show-delete-success="showDeleteSuccess"
      :show-error="showError"
      :show-info="showStatusSuccess || showInfo"
      :deleted-room-name="deletedTermName"
      :error-message="errorMessage"
      :info-message="infoMessage"
      @close-create="closeCreateToast"
      @close-edit="closeEditToast"
      @close-delete="closeDeleteToast"
      @close-error="closeErrorToast"
      @close-info="closeInfoToast"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import Navbar from '@/Components/Navbar.vue'
import Sidebar from '@/Components/Sidebar.vue'
import MessageFunction from '@/Components/MessageFunction.vue'
import TermsTable from '@/Components/TermsTable/TermsTable.vue'

// Receive props from Laravel
const props = defineProps({
    terms: {
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
    current_term: {
        type: Object,
        default: null
    },
    status_options: {
        type: Array,
        default: () => []
    },
    term_type_options: {
        type: Array,
        default: () => []
    }
})

// Debug: Log props to console
console.log('TermsLayout Props Received:', {
    terms: props.terms,
    pagination: props.pagination,
    filters: props.filters,
    stats: props.stats,
    current_term: props.current_term
})

// State for sidebar visibility
const sidebarOpen = ref(true)

// Function to toggle the sidebar's state
const toggleSidebar = () => (sidebarOpen.value = !sidebarOpen.value)

// Tab state
const activeTab = ref('list')

const setActiveTab = (tab) => {
  activeTab.value = tab
}

// Toast states
const showCreateSuccess = ref(false)
const showEditSuccess = ref(false)
const showDeleteSuccess = ref(false)
const showStatusSuccess = ref(false)
const showError = ref(false)
const showInfo = ref(false)
const deletedTermName = ref('')
const errorMessage = ref('')
const infoMessage = ref('')
const statusUpdateInfo = ref({})

// Toast trigger functions
const triggerToast = (type, data = {}) => {
    // Reset all toast states first
    showCreateSuccess.value = false
    showEditSuccess.value = false
    showDeleteSuccess.value = false
    showStatusSuccess.value = false
    showError.value = false
    showInfo.value = false

    // Set the appropriate toast state
    if (type === "create") {
        showCreateSuccess.value = true
        infoMessage.value = `Successfully created term: ${data.name}`
    } else if (type === "edit") {
        showEditSuccess.value = true
        infoMessage.value = `Successfully updated term: ${data.name}`
    } else if (type === "delete") {
        deletedTermName.value = data.name
        showDeleteSuccess.value = true
    } else if (type === "status-updated") {
        showStatusSuccess.value = true
        statusUpdateInfo.value = data
        infoMessage.value = `Status updated from ${data.oldStatus} to ${data.newStatus}`
    } else if (type === "error") {
        errorMessage.value = data.message || 'An error occurred'
        showError.value = true
    } else if (type === "info") {
        infoMessage.value = data.message || 'Operation successful'
        showInfo.value = true
    }

    // Auto-hide the toast after 3 seconds
    setTimeout(() => {
        showCreateSuccess.value = false
        showEditSuccess.value = false
        showDeleteSuccess.value = false
        showStatusSuccess.value = false
        showError.value = false
        showInfo.value = false
        deletedTermName.value = ''
        errorMessage.value = ''
        infoMessage.value = ''
        statusUpdateInfo.value = {}
    }, 3000)
}

// Event handlers for TermsTable
const handleStatusUpdated = (data) => {
    if (data.action === 'set-current') {
        // Set as current term
        router.patch(`/terms/${data.id}/status`, {
            status: 'set-current'
        }, {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast("info", {
                    message: `${data.name} has been set as the current term`
                })
            },
            onError: (errors) => {
                triggerToast("error", {
                    message: errors.message || 'Failed to set current term'
                })
            }
        })
    } else {
        // Change status
        router.patch(`/terms/${data.id}/status`, {
            status: data.status
        }, {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast("status-updated", {
                    name: data.name,
                    oldStatus: data.oldStatus,
                    newStatus: data.newStatus
                })
            },
            onError: (errors) => {
                triggerToast("error", {
                    message: errors.message || 'Failed to update status'
                })
            }
        })
    }
}

const handleRecordDeleted = (termId) => {
    router.delete(`/terms/${termId}`, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast("delete", { name: 'Term' })
        },
        onError: (errors) => {
            triggerToast("error", {
                message: errors.error || 'Failed to delete term'
            })
        }
    })
}

const handleRecordCreated = (formData) => {
    // Prepare data for backend
    const termData = {
        name: formData.name,
        code: formData.code,
        type: formData.type,
        startDate: formData.startDate,
        endDate: formData.endDate,
        status: formData.status,
        academic_year: formData.academic_year,
        enrollmentStart: formData.enrollmentStart,
        enrollmentEnd: formData.enrollmentEnd,
        classesStart: formData.classesStart,
        classesEnd: formData.classesEnd,
        examinationStart: formData.examinationStart,
        examinationEnd: formData.examinationEnd,
        notes: formData.notes
    }

    router.post('/terms', termData, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast("create", { name: formData.name })
        },
        onError: (errors) => {
            triggerToast("error", {
                message: Object.values(errors).join(', ') || 'Failed to create term'
            })
        }
    })
}

const handleRecordEdited = (termId, formData) => {
    // Prepare data for backend
    const termData = {
        name: formData.name,
        code: formData.code,
        type: formData.type,
        startDate: formData.startDate,
        endDate: formData.endDate,
        status: formData.status,
        academic_year: formData.academic_year,
        enrollmentStart: formData.enrollmentStart,
        enrollmentEnd: formData.enrollmentEnd,
        classesStart: formData.classesStart,
        classesEnd: formData.classesEnd,
        examinationStart: formData.examinationStart,
        examinationEnd: formData.examinationEnd,
        notes: formData.notes,
        is_current: formData.isCurrent
    }

    router.put(`/terms/${termId}`, termData, {
        preserveScroll: true,
        onSuccess: () => {
            triggerToast("edit", { name: formData.name })
        },
        onError: (errors) => {
            triggerToast("error", {
                message: Object.values(errors).join(', ') || 'Failed to update term'
            })
        }
    })
}

const handleSearch = (searchParams) => {
    router.get('/terms', searchParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true
    })
}

const handleError = (message) => {
    triggerToast("error", { message })
}

// Close toast functions
const closeCreateToast = () => showCreateSuccess.value = false
const closeEditToast = () => showEditSuccess.value = false
const closeDeleteToast = () => {
    showDeleteSuccess.value = false
    deletedTermName.value = ''
}
const closeErrorToast = () => {
    showError.value = false
    errorMessage.value = ''
}
const closeInfoToast = () => {
    showInfo.value = false
    infoMessage.value = ''
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

main {
  padding: 0;
}

@media (min-width: 768px) {
  main {
    padding: 1.5rem;
  }
}
</style>
