<template>
  <div class="app-shell">
    <Navbar @toggleSidebar="toggleSidebar" />

    <div class="app-frame">
      <Sidebar :sidebarOpen="sidebarOpen" class="fixed left-0 top-[5.5rem] z-20 h-[calc(100vh-5.5rem)] w-64 lg:relative lg:top-0 lg:h-auto lg:min-h-full" />
      <button
        v-if="sidebarOpen"
        type="button"
        class="fixed inset-0 z-[19] bg-slate-950/35 lg:hidden"
        aria-label="Close sidebar"
        @click="sidebarOpen = false"
      ></button>

      <main class="app-main">
        <div class="app-page-header">
          <Breadcrumbs trail="UPCEBU > TERMS" />
        </div>

        <div class="app-content-panel">
          <div>
            <div class="mb-4 flex w-fit items-center rounded-xl bg-slate-100 p-1">
              <button
                @click="setActiveTab('list')"
                :class="[
                  'rounded-lg px-5 py-2 text-sm font-semibold transition',
                  activeTab === 'list' ? 'bg-[#005740] text-white shadow-sm' : 'text-slate-700 hover:bg-white'
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
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
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

// State for sidebar visibility
const sidebarOpen = ref(typeof window === 'undefined' ? true : window.innerWidth >= 1024)

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
