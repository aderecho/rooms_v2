<script setup>
import { computed, ref } from 'vue';
import EquipmentFullDetailsModal from './EquipmentFullDetailsModal.vue';

const props = defineProps({
    isVisible: {
        type: Boolean,
        default: false,
    },
    selectedUsage: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

// State for full details modal
const isFullDetailsModalVisible = ref(false);
const selectedEquipment = ref(null);

const close = () => {
    emit('close');
};

const equipmentList = computed(() => props.selectedUsage?.equipmentUsed || []);

// Function to view full item details
const viewFullItemDetails = (item) => {
    selectedEquipment.value = item;
    isFullDetailsModalVisible.value = true;
};

const closeFullDetailsModal = () => {
    isFullDetailsModalVisible.value = false;
    selectedEquipment.value = null;
};
</script>

<template>
    <transition name="fade">
        <div v-if="isVisible" class="fixed inset-0 bg-black bg-opacity-70 z-50 flex items-center justify-center p-2 sm:p-4">
            <div class="bg-white rounded-lg shadow-2xl w-full max-w-5xl p-4 sm:p-6 relative max-h-[95vh] sm:max-h-[90vh] overflow-hidden flex flex-col">

                <!-- Header - Responsive -->
                <div class="border-b pb-3 sm:pb-4 mb-3 sm:mb-4">
                    <h3 class="text-lg sm:text-xl font-bold text-[#800020] mb-2 truncate pr-8">
                        Equipment Details for: {{ selectedUsage?.name }}
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 text-xs sm:text-sm text-gray-600">
                        <div>
                            <span class="font-semibold">Room:</span> {{ selectedUsage?.room }}
                            <span v-if="selectedUsage?.room_name" class="text-xs">({{ selectedUsage?.room_name }})</span>
                        </div>
                        <div><span class="font-semibold">Building:</span> {{ selectedUsage?.building }}</div>
                        <div><span class="font-semibold">College:</span> {{ selectedUsage?.college }}</div>
                        <div v-if="selectedUsage?.department" class="sm:col-span-2 md:col-span-1">
                            <span class="font-semibold">Department:</span> {{ selectedUsage?.department }}
                        </div>
                        <div class="sm:col-span-2 md:col-span-2">
                            <span class="font-semibold">Total Equipment:</span> {{ equipmentList.length }} items
                        </div>
                    </div>
                </div>

                <!-- Equipment Table - Responsive -->
                <div class="flex-1 overflow-y-auto border border-gray-200 rounded-lg">
                    <!-- Mobile Card View -->
                    <div class="block sm:hidden">
                        <div v-for="item in equipmentList" :key="item.id" class="border-b p-3 hover:bg-gray-50">
                            <div class="flex justify-between items-start mb-2">
                                <div class="font-medium text-sm">{{ item.name }}</div>
                                <span :class="{
                                    'bg-green-100 text-green-700': item.status === 'Available',
                                    'bg-blue-100 text-blue-700': item.status === 'In use',
                                    'bg-yellow-100 text-yellow-700': item.status === 'Maintenance',
                                    'bg-red-100 text-red-700': item.status === 'Damaged',
                                    'bg-gray-100 text-gray-700': item.status === 'Retired' || !item.status
                                }" class="px-2 py-1 text-xs font-semibold rounded-full">
                                    {{ item.status || 'Unknown' }}
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2 mb-2 text-xs">
                                <div>
                                    <span class="text-gray-500">Inventory ID:</span>
                                    <div class="font-mono">{{ item.inventory_id }}</div>
                                </div>
                                <div>
                                    <span class="text-gray-500">Property ID:</span>
                                    <div class="font-mono">{{ item.property_id }}</div>
                                </div>
                                <div>
                                    <span class="text-gray-500">Brand:</span>
                                    <div>{{ item.brand || 'N/A' }}</div>
                                </div>
                                <div>
                                    <span class="text-gray-500">Model:</span>
                                    <div>{{ item.model || 'N/A' }}</div>
                                </div>
                            </div>
                            
                            <div v-if="item.description" class="text-xs text-gray-500 mb-2">
                                {{ item.description }}
                            </div>
                            
                            <div v-if="item.serial_number" class="text-xs text-gray-400 font-mono mb-2">
                                S/N: {{ item.serial_number }}
                            </div>
                            
                            <div class="flex justify-end">
                                <button
                                    @click="viewFullItemDetails(item)"
                                    class="text-xs text-[#7A0C23] hover:text-[#5a071a] font-medium hover:underline"
                                >
                                    View Full Details
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <table class="min-w-full text-sm hidden sm:table">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs sm:text-sm text-gray-600 font-semibold">Inventory ID</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs sm:text-sm text-gray-600 font-semibold">Property ID</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs sm:text-sm text-gray-600 font-semibold">Equipment Name</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs sm:text-sm text-gray-600 font-semibold">Brand/Model</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs sm:text-sm text-gray-600 font-semibold">Status</th>
                                <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs sm:text-sm text-gray-600 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in equipmentList" :key="item.id" class="border-t hover:bg-gray-50">
                                <td class="px-3 sm:px-4 py-2 sm:py-3 font-mono text-xs sm:text-sm text-gray-700">{{ item.inventory_id }}</td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3 font-mono text-xs sm:text-sm text-gray-700">{{ item.property_id }}</td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3">
                                    <div class="font-medium text-xs sm:text-sm">{{ item.name }}</div>
                                    <div v-if="item.description" class="text-xs text-gray-500 mt-1 hidden lg:block">{{ item.description }}</div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3">
                                    <div class="text-xs sm:text-sm">{{ item.brand || 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">{{ item.model || 'N/A' }}</div>
                                    <div v-if="item.serial_number" class="text-xs text-gray-400 font-mono hidden lg:block">
                                        S/N: {{ item.serial_number }}
                                    </div>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3">
                                    <span :class="{
                                        'bg-green-100 text-green-700': item.status === 'Available',
                                        'bg-blue-100 text-blue-700': item.status === 'In use',
                                        'bg-yellow-100 text-yellow-700': item.status === 'Maintenance',
                                        'bg-red-100 text-red-700': item.status === 'Damaged',
                                        'bg-gray-100 text-gray-700': item.status === 'Retired' || !item.status
                                    }" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                        {{ item.status || 'Unknown' }}
                                    </span>
                                </td>
                                <td class="px-3 sm:px-4 py-2 sm:py-3">
                                    <button
                                        @click="viewFullItemDetails(item)"
                                        class="text-xs text-[#7A0C23] hover:text-[#5a071a] font-medium hover:underline"
                                    >
                                        View Full Details
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- No Items State -->
                    <div v-if="equipmentList.length === 0" class="px-4 py-8 text-center text-gray-400">
                        No equipment items found for this user.
                    </div>
                </div>

                <!-- Footer - Responsive -->
                <div class="mt-4 sm:mt-6 pt-3 sm:pt-4 border-t flex flex-col sm:flex-row justify-between items-center gap-3">
                    <div class="text-xs sm:text-sm text-gray-500 order-2 sm:order-1">
                        Showing {{ equipmentList.length }} equipment item(s)
                    </div>
                    <button
                        @click="close"
                        class="w-full sm:w-auto px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-150 text-sm order-1 sm:order-2"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </transition>

    <!-- Full Details Modal -->
    <EquipmentFullDetailsModal
        :is-visible="isFullDetailsModalVisible"
        :equipment="selectedEquipment"
        @close="closeFullDetailsModal"
    />
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

/* Ensure modal is scrollable on very small screens */
@media (max-width: 640px) {
    .fixed {
        overflow-y: auto;
    }
}
</style>