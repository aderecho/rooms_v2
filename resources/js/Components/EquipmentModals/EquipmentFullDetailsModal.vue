<!-- EquipmentFullDetailsModal.vue -->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    isVisible: {
        type: Boolean,
        default: false,
    },
    equipment: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const close = () => {
    emit('close');
};

// Format currency
const formatCurrency = (value) => {
    if (!value) return 'N/A';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
};

// Format date
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

// Status color mapping
const getStatusColor = (status) => {
    const colors = {
        'Available': 'bg-green-100 text-green-700 border-green-200',
        'In use': 'bg-blue-100 text-blue-700 border-blue-200',
        'Maintenance': 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'Damaged': 'bg-red-100 text-red-700 border-red-200',
        'Retired': 'bg-gray-100 text-gray-700 border-gray-200'
    };
    return colors[status] || 'bg-gray-100 text-gray-700 border-gray-200';
};
</script>

<template>
    <transition name="fade">
        <div v-if="isVisible && equipment" class="fixed inset-0 bg-black bg-opacity-70 z-[60] flex items-center justify-center p-2 sm:p-4">
            <div class="bg-white rounded-lg shadow-2xl w-full max-w-4xl p-4 sm:p-6 relative max-h-[95vh] overflow-hidden flex flex-col">
                
                <!-- Header -->
                <div class="border-b pb-3 sm:pb-4 mb-3 sm:mb-4 flex justify-between items-start">
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-[#800020] mb-1">Equipment Full Details</h3>
                        <p class="text-xs sm:text-sm text-gray-500">Inventory ID: {{ equipment.inventory_id }}</p>
                    </div>
                    <button 
                        @click="close"
                        class="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Content - Scrollable -->
                <div class="flex-1 overflow-y-auto">
                    <!-- Status Badge -->
                    <div class="mb-4 flex justify-between items-center">
                        <span class="text-sm font-semibold text-gray-600">Current Status:</span>
                        <span :class="['px-3 py-1 rounded-full text-sm font-semibold border', getStatusColor(equipment.status)]">
                            {{ equipment.status || 'Unknown' }}
                        </span>
                    </div>

                    <!-- Main Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <!-- Left Column -->
                        <div class="space-y-3">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Basic Information</h4>
                                <div class="space-y-2">
                                    <div>
                                        <span class="text-xs text-gray-500">Equipment Name:</span>
                                        <p class="text-sm font-semibold">{{ equipment.name }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Description:</span>
                                        <p class="text-sm">{{ equipment.description || 'No description provided' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Category:</span>
                                        <p class="text-sm">{{ equipment.category || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Sub-category:</span>
                                        <p class="text-sm">{{ equipment.sub_category || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 p-3 rounded-lg">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Identification Numbers</h4>
                                <div class="space-y-2">
                                    <div>
                                        <span class="text-xs text-gray-500">Inventory ID:</span>
                                        <p class="text-sm font-mono">{{ equipment.inventory_id }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Property ID:</span>
                                        <p class="text-sm font-mono">{{ equipment.property_id }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Serial Number:</span>
                                        <p class="text-sm font-mono">{{ equipment.serial_number || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Asset Tag:</span>
                                        <p class="text-sm font-mono">{{ equipment.asset_tag || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-3">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Manufacturer Details</h4>
                                <div class="space-y-2">
                                    <div>
                                        <span class="text-xs text-gray-500">Brand:</span>
                                        <p class="text-sm">{{ equipment.brand || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Model:</span>
                                        <p class="text-sm">{{ equipment.model || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Year Manufactured:</span>
                                        <p class="text-sm">{{ equipment.year_manufactured || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Country of Origin:</span>
                                        <p class="text-sm">{{ equipment.country_of_origin || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gray-50 p-3 rounded-lg">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Financial Information</h4>
                                <div class="space-y-2">
                                    <div>
                                        <span class="text-xs text-gray-500">Acquisition Cost:</span>
                                        <p class="text-sm font-semibold text-green-600">{{ formatCurrency(equipment.acquisition_cost) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Current Value:</span>
                                        <p class="text-sm">{{ formatCurrency(equipment.current_value) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Fund Source:</span>
                                        <p class="text-sm">{{ equipment.fund_source || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">PO Number:</span>
                                        <p class="text-sm">{{ equipment.po_number || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location and Assignment Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Location Details</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-xs text-gray-500">Building:</span>
                                    <p class="text-sm">{{ equipment.building || 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Room:</span>
                                    <p class="text-sm">{{ equipment.room || 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Floor:</span>
                                    <p class="text-sm">{{ equipment.floor || 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Cabinet/Shelf:</span>
                                    <p class="text-sm">{{ equipment.cabinet || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-3 rounded-lg">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Assignment Information</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-xs text-gray-500">Assigned To:</span>
                                    <p class="text-sm">{{ equipment.assigned_to || 'Unassigned' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Department:</span>
                                    <p class="text-sm">{{ equipment.department || 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">College:</span>
                                    <p class="text-sm">{{ equipment.college || 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Date Assigned:</span>
                                    <p class="text-sm">{{ formatDate(equipment.date_assigned) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acquisition and Warranty Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Acquisition Details</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-xs text-gray-500">Date Acquired:</span>
                                    <p class="text-sm">{{ formatDate(equipment.date_acquired) }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Acquisition Mode:</span>
                                    <p class="text-sm">{{ equipment.acquisition_mode || 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Supplier/Vendor:</span>
                                    <p class="text-sm">{{ equipment.supplier || 'N/A' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Contract Number:</span>
                                    <p class="text-sm">{{ equipment.contract_number || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-3 rounded-lg">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Warranty & Maintenance</h4>
                            <div class="space-y-2">
                                <div>
                                    <span class="text-xs text-gray-500">Warranty Expiry:</span>
                                    <p class="text-sm">{{ formatDate(equipment.warranty_expiry) }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Last Maintenance:</span>
                                    <p class="text-sm">{{ formatDate(equipment.last_maintenance) }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Next Maintenance:</span>
                                    <p class="text-sm">{{ formatDate(equipment.next_maintenance) }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500">Maintenance Provider:</span>
                                    <p class="text-sm">{{ equipment.maintenance_provider || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Technical Specifications -->
                    <div class="bg-gray-50 p-3 rounded-lg mb-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Technical Specifications</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div v-if="equipment.processor">
                                <span class="text-xs text-gray-500">Processor:</span>
                                <p class="text-sm">{{ equipment.processor }}</p>
                            </div>
                            <div v-if="equipment.ram">
                                <span class="text-xs text-gray-500">RAM:</span>
                                <p class="text-sm">{{ equipment.ram }}</p>
                            </div>
                            <div v-if="equipment.storage">
                                <span class="text-xs text-gray-500">Storage:</span>
                                <p class="text-sm">{{ equipment.storage }}</p>
                            </div>
                            <div v-if="equipment.graphics">
                                <span class="text-xs text-gray-500">Graphics:</span>
                                <p class="text-sm">{{ equipment.graphics }}</p>
                            </div>
                            <div v-if="equipment.screen_size">
                                <span class="text-xs text-gray-500">Screen Size:</span>
                                <p class="text-sm">{{ equipment.screen_size }}</p>
                            </div>
                            <div v-if="equipment.resolution">
                                <span class="text-xs text-gray-500">Resolution:</span>
                                <p class="text-sm">{{ equipment.resolution }}</p>
                            </div>
                            <div v-if="equipment.ports">
                                <span class="text-xs text-gray-500">Ports:</span>
                                <p class="text-sm">{{ equipment.ports }}</p>
                            </div>
                            <div v-if="equipment.power_rating">
                                <span class="text-xs text-gray-500">Power Rating:</span>
                                <p class="text-sm">{{ equipment.power_rating }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="bg-gray-50 p-3 rounded-lg mb-4" v-if="equipment.remarks || equipment.notes">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Additional Information</h4>
                        <div class="space-y-2">
                            <div v-if="equipment.remarks">
                                <span class="text-xs text-gray-500">Remarks:</span>
                                <p class="text-sm">{{ equipment.remarks }}</p>
                            </div>
                            <div v-if="equipment.notes">
                                <span class="text-xs text-gray-500">Notes:</span>
                                <p class="text-sm">{{ equipment.notes }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Document Attachments (if any) -->
                    <div class="bg-gray-50 p-3 rounded-lg" v-if="equipment.documents && equipment.documents.length > 0">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Attached Documents</h4>
                        <div class="space-y-2">
                            <div v-for="doc in equipment.documents" :key="doc.id" class="flex items-center justify-between p-2 bg-white rounded border">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-sm">{{ doc.name }}</span>
                                </div>
                                <a :href="doc.url" target="_blank" class="text-xs text-[#7A0C23] hover:underline">View</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-4 pt-3 border-t flex justify-end">
                    <button
                        @click="close"
                        class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-150 text-sm"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </transition>
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