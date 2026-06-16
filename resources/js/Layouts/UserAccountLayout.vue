<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import UserAccountTable from '@/Components/UserAccountModal/UserAccountTable.vue';
import UserModal from '@/Components/UserAccountModal/UserModal.vue';
import MessageFunction from '@/Components/MessageFunction.vue';

// --- Props ---
const props = defineProps({
    initialUsers: {
        type: Array,
        default: () => []
    },
    colleges: {
        type: Array,
        default: () => []
    },
    departments: {
        type: Array,
        default: () => []
    },
    stats: {
        type: Object,
        default: () => ({})
    },
    canManageUsers: {
        type: Boolean,
        default: false
    },
    pagination: {
        type: Object,
        default: () => ({})
    },
    filters: {
        type: Object,
        default: () => ({})
    }
});

// --- Data State ---
const users = ref(props.initialUsers || []);
const isLoading = ref(false);
const isTableLoading = ref(false);
const showAllData = ref(false); // Toggle to show all data

// --- Toast States ---
const showCreateSuccess = ref(false);
const showEditSuccess = ref(false);
const showDeleteSuccess = ref(false);
const showError = ref(false);
const showInfo = ref(false);
const deletedUserName = ref('');
const errorMessage = ref('');
const infoMessage = ref('');

// --- Layout State ---
const sidebarVisible = ref(true)
const toggleSidebar = () => {
    sidebarVisible.value = !sidebarVisible.value
}

// --- Modal State ---
const isModalVisible = ref(false)
const modalType = ref(null)
const modalData = ref(null)

// Use Inertia form for search
const searchForm = useForm({
    search: props.filters.search || '',
    college_id: props.filters.college_id || '',
    department_id: props.filters.department_id || '',
    user_type: props.filters.user_type || '',
    account_status: props.filters.account_status || ''
});

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const parseResponseBody = async (response) => {
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
        return response.json();
    }
    const text = await response.text();
    return { message: text || `Request failed with status ${response.status}` };
};

// --- Database Methods ---
const fetchUsers = () => {
    isTableLoading.value = true;

    searchForm.get(route('user-accounts.index'), {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isTableLoading.value = false;
        }
    });
};

const addUser = async (newUser) => {
    isLoading.value = true;

    try {
        // Find college and department IDs
        const college = props.colleges.find(c => c.college_name === newUser.college);
        const department = props.departments.find(d => d.department_name === newUser.department);

        const formData = {
            username: newUser.username,
            email: newUser.email,
            // password: newUser.password,
            password: newUser.password || 'password123',
            first_name: newUser.first_name,
            last_name: newUser.last_name,
            middle_name: newUser.middle_name || '',
            employee_id: newUser.username, // Using username as employee_id for simplicity
            // user_type: newUser.role,
            user_type: newUser.role || 'faculty', // Default to 'faculty' if role is not provided
            account_status: 'active',
            college_id: college ? college.id : null,
            department_id: department ? department.id : null,
            permissions: newUser.permissions || [],
        };

        const response = await fetch('/user-accounts', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const result = await parseResponseBody(response);

        if (!response.ok) {
            const serverError =
                result?.message ||
                (result?.errors && Object.values(result.errors).flat().join(', ')) ||
                'Failed to create user';
            return { success: false, error: serverError };
        }

        if (result.success) {
            // Refresh the page to get updated user list
            fetchUsers();
            return { success: true, data: result.user };
        } else {
            return {
                success: false,
                error: result.message || 'Failed to create user'
            };
        }
    } catch (error) {
        console.error('Error adding user:', error);
        return {
            success: false,
            error: { message: 'Network error' }
        };
    } finally {
        isLoading.value = false;
    }
};

const updateUser = async (updatedUser) => {
    isLoading.value = true;

    try {
        // Find college and department IDs
        const college = props.colleges.find(c => c.college_name === updatedUser.college);
        const department = props.departments.find(d => d.department_name === updatedUser.department);

        const formData = {
            username: updatedUser.username,
            email: updatedUser.email,
            first_name: updatedUser.first_name,
            last_name: updatedUser.last_name,
            middle_name: updatedUser.middle_name || '',
            employee_id: updatedUser.username, // Using username as employee_id
            user_type: updatedUser.role,
            account_status: updatedUser.account_status || 'active',
            college_id: college ? college.id : null,
            department_id: department ? department.id : null,
            permissions: updatedUser.permissions || [],
            ...(updatedUser.password && { password: updatedUser.password })
        };

        const response = await fetch(`/user-accounts/${updatedUser.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const result = await parseResponseBody(response);

        if (!response.ok) {
            const serverError =
                result?.message ||
                (result?.errors && Object.values(result.errors).flat().join(', ')) ||
                'Failed to update user';
            return { success: false, error: serverError };
        }

        if (result.success) {
            // Refresh the page to get updated user list
            fetchUsers();
            return { success: true, data: result.user };
        } else {
            return {
                success: false,
                error: result.message || 'Failed to update user'
            };
        }
    } catch (error) {
        console.error('Error updating user:', error);
        return {
            success: false,
            error: { message: 'Network error' }
        };
    } finally {
        isLoading.value = false;
    }
};

const deleteUser = async (userId) => {
    isLoading.value = true;

    try {
        const response = await fetch(`/user-accounts/${userId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });

        const result = await parseResponseBody(response);

        if (!response.ok) {
            const serverError =
                result?.message ||
                (result?.errors && Object.values(result.errors).flat().join(', ')) ||
                'Failed to delete user';
            return { success: false, error: serverError };
        }

        if (result.success) {
            // Refresh the page to get updated user list
            fetchUsers();
            return { success: true, username: result.username };
        } else {
            return {
                success: false,
                error: result.message || 'Failed to delete user'
            };
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        return {
            success: false,
            error: 'Network error'
        };
    } finally {
        isLoading.value = false;
    }
};

// --- Event Handlers ---
const handleOpenModal = (type, data = null) => {
    if (!props.canManageUsers && type !== 'view') {
        triggerError('Unauthorized. Only admins can manage user accounts.');
        return;
    }
    modalType.value = type;
    modalData.value = data;
    isModalVisible.value = true;
};

const handleCloseModal = () => {
    isModalVisible.value = false;
    modalData.value = null;
    modalType.value = null;
};

const handleDataUpdated = async (data, type) => {
    let result;

    switch (type) {
        case 'add':
            result = await addUser(data);
            if (result.success) {
                triggerToast("create");
                handleCloseModal();
            } else {
                triggerError(result.error.message || result.error || 'Failed to create user');
            }
            break;

        case 'edit':
            result = await updateUser(data);
            if (result.success) {
                triggerToast("edit");
                handleCloseModal();
            } else {
                triggerError(result.error.message || result.error || 'Failed to update user');
            }
            break;

        case 'delete':
            result = await deleteUser(data.id);
            if (result.success) {
                triggerToast("delete", result.username);
                handleCloseModal();
            } else {
                triggerError(result.error || 'Failed to delete user');
            }
            break;
    }
};

// Toggle to show all data
const toggleShowAllData = () => {
    showAllData.value = !showAllData.value;
    // Log all user data to console for debugging
    console.log('All User Data:', users.value);
    console.log('Database Fields:', users.value.length > 0 ? Object.keys(users.value[0]) : 'No users');

    if (showAllData.value) {
        triggerInfo(`Showing all ${users.value.length} users with full database fields`);
    }
};

// --- Toast Functions ---
const triggerToast = (type, name = "") => {
    // Reset all toasts first
    showCreateSuccess.value = false;
    showEditSuccess.value = false;
    showDeleteSuccess.value = false;
    showError.value = false;
    showInfo.value = false;

    if (type === "create") {
        showCreateSuccess.value = true;
    } else if (type === "edit") {
        showEditSuccess.value = true;
    } else if (type === "delete") {
        deletedUserName.value = name;
        showDeleteSuccess.value = true;
    }

    // Auto hide after 3 seconds
    setTimeout(() => {
        showCreateSuccess.value = false;
        showEditSuccess.value = false;
        showDeleteSuccess.value = false;
        deletedUserName.value = "";
    }, 3000);
};

const triggerError = (message) => {
    errorMessage.value = message;
    showError.value = true;

    setTimeout(() => {
        showError.value = false;
        errorMessage.value = '';
    }, 5000);
};

const triggerInfo = (message) => {
    infoMessage.value = message;
    showInfo.value = true;

    setTimeout(() => {
        showInfo.value = false;
        infoMessage.value = '';
    }, 3000);
};

const closeCreateToast = () => showCreateSuccess.value = false;
const closeEditToast = () => showEditSuccess.value = false;
const closeDeleteToast = () => {
    showDeleteSuccess.value = false;
    deletedUserName.value = '';
};
const closeErrorToast = () => {
    showError.value = false;
    errorMessage.value = '';
};
const closeInfoToast = () => {
    showInfo.value = false;
    infoMessage.value = '';
};

// Watch for props changes to update users
watch(() => props.initialUsers, (newUsers) => {
    users.value = newUsers || [];
    // Log the data structure when users are loaded
    if (newUsers && newUsers.length > 0) {
        console.log('User Data Structure:', {
            count: newUsers.length,
            firstUser: newUsers[0],
            fields: Object.keys(newUsers[0])
        });
    }
}, { immediate: true });

// Lifecycle
onMounted(() => {
    console.log('Users loaded via Inertia:', users.value.length);
    console.log('Colleges:', props.colleges.length);
    console.log('Departments:', props.departments.length);
    console.log('Stats:', props.stats);

    // Show welcome message
    if (users.value.length === 0) {
        triggerInfo('No users found in database. Click "Add New User" to create your first user.');
    } else {
        triggerInfo(`Loaded ${users.value.length} users from database.`);
    }
});
</script>

<template>
    <div class="bg-gray-200 font-sans min-h-screen">
        <!-- Toast Notifications -->
        <MessageFunction
            :show-create-success="showCreateSuccess"
            :show-edit-success="showEditSuccess"
            :show-delete-success="showDeleteSuccess"
            :show-error="showError"
            :show-info="showInfo"
            :deleted-user-name="deletedUserName"
            :error-message="errorMessage"
            :info-message="infoMessage"
            @close-create="closeCreateToast"
            @close-edit="closeEditToast"
            @close-delete="closeDeleteToast"
            @close-error="closeErrorToast"
            @close-info="closeInfoToast"
        />

        <!-- Loading Overlay for modal operations -->
        <div v-if="isLoading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#7A0C23] mx-auto"></div>
                <p class="mt-4 text-gray-600">Processing...</p>
            </div>
        </div>

        <Navbar @toggle-sidebar="toggleSidebar" />

        <div class="flex pt-10 min-h-screen transition-all duration-300">
            <!-- <Sidebar v-show="sidebarVisible" class="fixed top-5 left-0 h-full z-20 w-64 lg:relative" /> -->
             <Sidebar  :sidebarOpen="sidebarVisible" class="fixed top-5 left-0 h-full z-20 w-64 lg:relative"/>

            <main id="main" class="flex-1 overflow-y-auto p-0 md:p-6 bg-gray-200">
                <UserAccountTable
                    :users="users"
                    :loading="isTableLoading"
                    :colleges="colleges"
                    :departments="departments"
                    :can-manage-users="canManageUsers"
                    :show-all-data="showAllData"
                    @open-modal="handleOpenModal"
                    @search="fetchUsers"
                    @search-query-updated="(query) => searchForm.search = query"
                    @toggle-show-all="toggleShowAllData"
                />
            </main>
        </div>

        <UserModal
            :is-visible="isModalVisible"
            :type="modalType"
            :user="modalData"
            :colleges="colleges"
            :departments="departments"
            :can-manage-users="canManageUsers"
            @close="handleCloseModal"
            @data-updated="handleDataUpdated"
        />
    </div>
</template>
