<script setup>
import { defineProps, defineEmits, computed, ref, watch, onMounted } from 'vue';

const props = defineProps({
    isVisible: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String, // 'add', 'view', 'edit', 'delete'
        default: null,
    },
    user: {
        type: Object,
        default: () => null,
    },
    colleges: {
        type: Array,
        default: () => []
    },
    departments: {
        type: Array,
        default: () => []
    },
    canManageUsers: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'dataUpdated']);

// --- REACTIVE STATE ---
const isLoading = ref(false);
const formErrors = ref({});
const passwordField = ref('');
const confirmPassword = ref('');
const showRawData = ref(false);

// --- CONSTANTS ---
const roles = ['admin', 'faculty', 'staff', 'student', 'guest'];
const permissionsOptions = ['Can Approve', 'Can Edit', 'Can Book', 'Staff Work', 'User Type Only'];

// Map your user roles to display names
const roleDisplayNames = {
    'admin': 'Admin',
    'faculty': 'Faculty',
    'staff': 'Staff',
    'student': 'Student',
    'guest': 'Guest'
};

const defaultPermissionsMap = {
    'admin': ['Can Approve', 'Can Edit', 'Can Book', 'Staff Work', 'User Type Only'],
    'staff': ['Can Book', 'Staff Work'],
    'faculty': ['Can Book', 'User Type Only'],
    'student': ['Can Book', 'User Type Only'],
    'guest': ['User Type Only']
};

// Form data
const formData = ref({
    username: '',
    email: '',
    first_name: '',
    last_name: '',
    middle_name: '',
    role: 'staff',
    department: '',
    college: '',
    account_status: 'active',
    permissions: [],
});

// Get college and department options from props
const collegeOptions = computed(() => {
    return props.colleges.map(college => ({
        id: college.id,
        name: college.college_name,
        value: college.college_name
    }));
});

const departmentOptions = computed(() => {
    return props.departments.map(dept => ({
        id: dept.id,
        name: dept.department_name,
        value: dept.department_name
    }));
});

// Get all user fields for display
const allUserFields = computed(() => {
    if (!props.user) return [];
    return Object.entries(props.user).map(([key, value]) => ({
        field: key,
        value: value,
        type: typeof value
    }));
});

// --- WATCHERS & LOGIC ---

// Watch for changes in the 'user' prop and 'type'
watch(() => [props.user, props.type], ([newUser, newType]) => {
    if (newType === 'add') {
        // Reset for a fresh 'add' form
        formData.value = {
            username: '',
            email: '',
            first_name: '',
            last_name: '',
            middle_name: '',
            role: 'staff',
            department: '',
            college: '',
            account_status: 'active',
            permissions: defaultPermissionsMap['staff'],
        };
        passwordField.value = '';
        confirmPassword.value = '';
        formErrors.value = {};
        showRawData.value = false;
    } else if (newUser) {
        // Deep copy the user object to formData for modification/view
        formData.value = {
            username: newUser.username || '',
            email: newUser.email || '',
            first_name: newUser.first_name || '',
            last_name: newUser.last_name || '',
            middle_name: newUser.middle_name || '',
            role: newUser.role || 'staff',
            department: newUser.department || '',
            college: newUser.college || '',
            account_status: newUser.account_status || 'active',
            permissions: Array.isArray(newUser.permissions) ? newUser.permissions : (newUser.permissions ? [newUser.permissions] : []),
        };
        passwordField.value = '';
        confirmPassword.value = '';
        formErrors.value = {};
    } else {
        formData.value = {};
        formErrors.value = {};
    }
}, { immediate: true, deep: true });

// Watch for changes in 'role' and update 'permissions'
watch(() => formData.value.role, (newRole) => {
    if (props.type === 'add' || props.type === 'edit') {
        formData.value.permissions = defaultPermissionsMap[newRole] || [];
    }
});

// --- COMPUTED PROPERTIES ---
const modalTitle = computed(() => {
    switch (props.type) {
        case 'add': return 'Add New User Account';
        case 'view': return `View Details: ${props.user?.username || 'User'}`;
        case 'edit': return `Edit User Account: ${props.user?.username || 'User'}`;
        case 'delete': return `Confirm Delete: ${props.user?.username || 'User'}`;
        default: return 'User Account Action';
    }
});

const isView = computed(() => props.type === 'view');
const isAdd = computed(() => props.type === 'add');
const isEdit = computed(() => props.type === 'edit');
const isEditOrAdd = computed(() => props.type === 'edit' || props.type === 'add');
const isDelete = computed(() => props.type === 'delete');
const isReadonlyForAction = computed(() => !props.canManageUsers && (isAdd.value || isEdit.value || isDelete.value));

// Check if password is required
const isPasswordRequired = computed(() => isAdd.value || (isEdit.value && passwordField.value));

// Format value for display
const formatValue = (value) => {
    if (value === null || value === undefined) return 'N/A';
    if (Array.isArray(value)) return value.join(', ');
    if (typeof value === 'object') return JSON.stringify(value);
    return value.toString();
};

// --- VALIDATION METHODS ---
const validateForm = () => {
    formErrors.value = {};

    if (!formData.value.username.trim()) {
        formErrors.value.username = 'Username is required';
    }

    if (!formData.value.email.trim()) {
        formErrors.value.email = 'Email is required';
    } else if (!isValidEmail(formData.value.email)) {
        formErrors.value.email = 'Please enter a valid email address';
    }

    if (!formData.value.first_name.trim()) {
        formErrors.value.first_name = 'First name is required';
    }

    if (!formData.value.last_name.trim()) {
        formErrors.value.last_name = 'Last name is required';
    }

    if (!formData.value.role) {
        formErrors.value.role = 'Role is required';
    }

    if (isAdd.value) {
        if (!passwordField.value) {
            formErrors.value.password = 'Password is required';
        } else if (passwordField.value.length < 8) {
            formErrors.value.password = 'Password must be at least 8 characters';
        } else if (passwordField.value !== confirmPassword.value) {
            formErrors.value.confirmPassword = 'Passwords do not match';
        }
    } else if (isEdit.value && passwordField.value) {
        if (passwordField.value.length < 8) {
            formErrors.value.password = 'Password must be at least 8 characters';
        } else if (passwordField.value !== confirmPassword.value) {
            formErrors.value.confirmPassword = 'Passwords do not match';
        }
    }

    return Object.keys(formErrors.value).length === 0;
};

const isValidEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

// --- FORM HANDLING METHODS ---
const handleSubmit = async () => {
    if (!validateForm()) {
        return;
    }

    isLoading.value = true;

    try {
        const payload = {
            ...formData.value,
            permissions: formData.value.permissions || []
        };

        // Add password only if provided
        if (isPasswordRequired.value && passwordField.value) {
            payload.password = passwordField.value;
        }

        // Add ID for edit mode
        if (isEdit.value && props.user) {
            payload.id = props.user.id;
        }

        // Emit to parent component
        emit('dataUpdated', payload, props.type);

    } catch (error) {
        console.error('Error in form submission:', error);
        // Let parent handle the error
        emit('dataUpdated', { error: error.message }, props.type);
    } finally {
        isLoading.value = false;
    }
};

const handleDeleteConfirm = async () => {
    isLoading.value = true;

    try {
        // Emit to parent component
        emit('dataUpdated', {
            id: props.user.id,
            username: props.user.username
        }, 'delete');

    } catch (error) {
        console.error('Error in delete confirmation:', error);
        // Let parent handle the error
        emit('dataUpdated', { error: error.message }, 'delete');
    } finally {
        isLoading.value = false;
    }
};

// Clear errors when typing
const clearError = (field) => {
    if (formErrors.value[field]) {
        delete formErrors.value[field];
    }
};

// Helper to get display name for role
const getRoleDisplayName = (role) => {
    return roleDisplayNames[role] || role.charAt(0).toUpperCase() + role.slice(1);
};

// Toggle raw data view
const toggleRawData = () => {
    showRawData.value = !showRawData.value;
};

onMounted(() => {
    if (props.user) {
        console.log('User data loaded in modal:', props.user);
    }
});
</script>

<template>
    <Transition name="modal-fade">
        <div v-if="isVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="emit('close')">
            <div class="bg-white rounded-lg shadow-2xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-100 opacity-100">

                <!-- Loading overlay -->
                <div v-if="isLoading" class="absolute inset-0 bg-white bg-opacity-70 flex items-center justify-center rounded-lg z-10">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#7A0C23] mx-auto mb-4"></div>
                        <p class="text-gray-600">Processing...</p>
                    </div>
                </div>

                <!-- Header -->
                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h3 class="text-2xl font-semibold text-gray-800">{{ modalTitle }}</h3>
                    <div class="flex items-center space-x-2">
                        <button
                            v-if="isView && user"
                            @click="toggleRawData"
                            class="text-gray-500 hover:text-gray-700 text-sm"
                        >
                            {{ showRawData ? 'Hide Raw Data' : 'Show Raw Data' }}
                        </button>
                        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 transition" :disabled="isLoading">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div
                    v-if="isReadonlyForAction"
                    class="mb-4 rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800"
                >
                    You do not have permission to manage user accounts.
                </div>

                <!-- Raw Data View -->
                <div v-if="isView && user && showRawData" class="mb-6">
                    <h4 class="font-semibold text-lg text-gray-700 mb-3">Raw Database Data</h4>
                    <div class="bg-gray-50 p-4 rounded-lg border">
                        <div class="grid grid-cols-2 gap-4">
                            <div v-for="field in allUserFields" :key="field.field" class="border-b pb-2">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">{{ field.field }}</div>
                                <div class="font-mono text-sm break-words mt-1">
                                    {{ formatValue(field.value) }}
                                    <span class="text-xs text-gray-400 ml-2">({{ field.type }})</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-500">
                            Total fields: {{ allUserFields.length }}
                        </div>
                    </div>
                </div>

                <!-- View Mode -->
                <div v-if="isView && user && !showRawData" class="space-y-3 text-gray-700">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">ID</p>
                            <p class="font-medium">{{ user.id || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Username</p>
                            <p class="font-medium">{{ user.username || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Email</p>
                            <p class="font-medium">{{ user.email || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Role</p>
                            <p class="font-medium">{{ getRoleDisplayName(user.role) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">First Name</p>
                            <p class="font-medium">{{ user.first_name || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Last Name</p>
                            <p class="font-medium">{{ user.last_name || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Middle Name</p>
                            <p class="font-medium">{{ user.middle_name || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Employee ID</p>
                            <p class="font-medium">{{ user.employee_id || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Department</p>
                            <p class="font-medium">{{ user.department || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">College</p>
                            <p class="font-medium">{{ user.college || 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Account Status</p>
                            <p class="font-medium">{{ user.account_status || 'active' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Created At</p>
                            <p class="font-medium">{{ user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Updated At</p>
                            <p class="font-medium">{{ user.updated_at ? new Date(user.updated_at).toLocaleDateString() : 'N/A' }}</p>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t">
                        <h4 class="font-semibold mb-2">Permissions</h4>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="p in user.permissions" :key="p"
                                  class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                {{ p }}
                            </span>
                            <span v-if="!user.permissions || user.permissions.length === 0"
                                  class="text-gray-500 italic">
                                No specific permissions granted.
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t flex justify-end">
                        <button @click="emit('close')"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition"
                                :disabled="isLoading">
                            Close
                        </button>
                    </div>
                </div>

                <!-- Add/Edit Form -->
                <form v-else-if="isEditOrAdd && !isReadonlyForAction" @submit.prevent="handleSubmit" class="space-y-6">
                    <!-- Name Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 text-left">First Name <span class="text-red-500">*</span></label>
                            <input type="text" id="first_name" v-model="formData.first_name" required
                                   @input="clearError('first_name')"
                                   :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                            formErrors.first_name ? 'border-red-500' : 'border-gray-300']">
                            <p v-if="formErrors.first_name" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.first_name }}</p>
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 text-left">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" id="last_name" v-model="formData.last_name" required
                                   @input="clearError('last_name')"
                                   :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                            formErrors.last_name ? 'border-red-500' : 'border-gray-300']">
                            <p v-if="formErrors.last_name" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.last_name }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-gray-700 text-left">Middle Name</label>
                            <input type="text" id="middle_name" v-model="formData.middle_name"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Username & Email -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 text-left">
                                Username <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="username" v-model="formData.username" required
                                   @input="clearError('username')"
                                   :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                            formErrors.username ? 'border-red-500' : 'border-gray-300']">
                            <p v-if="formErrors.username" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.username }}</p>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 text-left">
                                Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="email" v-model="formData.email" required
                                   @input="clearError('email')"
                                   :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                            formErrors.email ? 'border-red-500' : 'border-gray-300']">
                            <p v-if="formErrors.email" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.email }}</p>
                        </div>
                    </div>

                    <!-- Password Fields (Only for Add or when password is entered) -->
                    <div v-if="isAdd" class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 text-left">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="password" v-model="passwordField" required
                                   @input="clearError('password')"
                                   :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                            formErrors.password ? 'border-red-500' : 'border-gray-300']">
                            <p v-if="formErrors.password" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.password }}</p>
                        </div>

                        <div>
                            <label for="confirmPassword" class="block text-sm font-medium text-gray-700 text-left">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="confirmPassword" v-model="confirmPassword" required
                                   @input="clearError('confirmPassword')"
                                   :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                            formErrors.confirmPassword ? 'border-red-500' : 'border-gray-300']">
                            <p v-if="formErrors.confirmPassword" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.confirmPassword }}</p>
                        </div>
                    </div>

                    <!-- Password Fields for Edit (Optional) -->
                    <div v-if="isEdit" class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 text-left">
                                New Password <span class="text-gray-400 text-xs">(Leave blank to keep current)</span>
                            </label>
                            <input type="password" id="password" v-model="passwordField"
                                   @input="clearError('password')"
                                   :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                            formErrors.password ? 'border-red-500' : 'border-gray-300']">
                            <p v-if="formErrors.password" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.password }}</p>
                        </div>

                        <div v-if="passwordField">
                            <label for="confirmPassword" class="block text-sm font-medium text-gray-700 text-left">
                                Confirm New Password
                            </label>
                            <input type="password" id="confirmPassword" v-model="confirmPassword"
                                   @input="clearError('confirmPassword')"
                                   :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                            formErrors.confirmPassword ? 'border-red-500' : 'border-gray-300']">
                            <p v-if="formErrors.confirmPassword" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.confirmPassword }}</p>
                        </div>
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 text-left">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select id="role" v-model="formData.role" required
                                @change="clearError('role')"
                                :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                         formErrors.role ? 'border-red-500' : 'border-gray-300']">
                            <option value="" disabled>Select Role</option>
                            <option v-for="role in roles" :key="role" :value="role">
                                {{ getRoleDisplayName(role) }}
                            </option>
                        </select>
                        <p v-if="formErrors.role" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.role }}</p>
                    </div>

                    <!-- Department & College -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="department" class="block text-sm font-medium text-gray-700 text-left">
                                Department
                            </label>
                            <select id="department" v-model="formData.department"
                                    @change="clearError('department')"
                                    :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                             formErrors.department ? 'border-red-500' : 'border-gray-300']">
                                <option value="">Select Department (Optional)</option>
                                <option v-for="dept in departmentOptions" :key="dept.id" :value="dept.name">{{ dept.name }}</option>
                            </select>
                            <p v-if="formErrors.department" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.department }}</p>
                        </div>

                        <div>
                            <label for="college" class="block text-sm font-medium text-gray-700 text-left">
                                College
                            </label>
                            <select id="college" v-model="formData.college"
                                    @change="clearError('college')"
                                    :class="['mt-1 block w-full border rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                                             formErrors.college ? 'border-red-500' : 'border-gray-300']">
                                <option value="">Select College (Optional)</option>
                                <option v-for="col in collegeOptions" :key="col.id" :value="col.name">{{ col.name }}</option>
                            </select>
                            <p v-if="formErrors.college" class="mt-1 text-sm text-red-600 text-left">{{ formErrors.college }}</p>
                        </div>
                    </div>

                    <!-- Account Status -->
                    <div v-if="isEdit">
                        <label for="account_status" class="block text-sm font-medium text-gray-700 text-left">
                            Account Status <span class="text-red-500">*</span>
                        </label>
                        <select id="account_status" v-model="formData.account_status" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>

                    <hr class="border-gray-200">

                    <!-- Permissions -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2 text-left">Permissions</label>
                        <div class="grid grid-cols-2 gap-2 p-3 border border-gray-300 rounded-md bg-gray-50">
                            <div v-for="permission in permissionsOptions" :key="permission" class="flex items-center">
                                <input
                                    :id="permission"
                                    type="checkbox"
                                    :value="permission"
                                    v-model="formData.permissions"
                                    class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500"
                                >
                                <label :for="permission" class="ml-2 block text-sm text-gray-900">{{ permission }}</label>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 text-left">
                            Default permissions set for <strong>{{ getRoleDisplayName(formData.role) }}</strong> role.
                        </p>
                    </div>

                    <!-- Form Actions -->
                    <div class="pt-4 border-t flex justify-end space-x-3">
                        <button type="button" @click="emit('close')"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition disabled:opacity-50"
                                :disabled="isLoading">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition disabled:opacity-50 flex items-center"
                                :disabled="isLoading">
                            <span v-if="isLoading" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
                            {{ isAdd ? 'Add Account' : 'Save Changes' }}
                        </button>
                    </div>
                </form>

                <!-- Delete Confirmation -->
                <div v-else-if="isDelete && user && !isReadonlyForAction" class="space-y-4">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-2.692-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Delete User Account</h3>
                        <p class="text-sm text-gray-500">
                            Are you sure you want to delete the account for <strong class="text-red-600">{{ user.username }}</strong>?
                        </p>
                        <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        This action cannot be undone. All data associated with this account will be permanently removed.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t flex justify-end space-x-3">
                        <button @click="emit('close')"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition disabled:opacity-50"
                                :disabled="isLoading">
                            Cancel
                        </button>
                        <button @click="handleDeleteConfirm"
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition disabled:opacity-50 flex items-center"
                                :disabled="isLoading">
                            <span v-if="isLoading" class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>
                            Delete Permanently
                        </button>
                    </div>
                </div>

                <div v-else-if="isReadonlyForAction" class="mt-6 pt-4 border-t flex justify-end">
                    <button @click="emit('close')"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition"
                            :disabled="isLoading">
                        Close
                    </button>
                </div>

            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

/* Custom scrollbar for modal */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
</style>
