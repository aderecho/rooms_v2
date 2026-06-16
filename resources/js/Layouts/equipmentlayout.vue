<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import Navbar from '@/Components/Navbar.vue'
import Sidebar from '@/Components/Sidebar.vue'
import EquipmentTable from '@/Components/EquipmentModals/EquipmentTable.vue'
import Chart from 'chart.js/auto'

// Sidebar state - Responsive handling
const sidebarOpen = ref(window.innerWidth >= 1024) // Open by default on desktop
const isMobile = ref(window.innerWidth < 768)

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value
}

// Handle window resize for responsive behavior
const handleResize = () => {
    isMobile.value = window.innerWidth < 768
    // Auto close sidebar on mobile when resizing to mobile
    if (isMobile.value) {
        sidebarOpen.value = false
    }
}

// Chart references and instances
const pieChartRef = ref(null)
const barChartRef = ref(null)

let pieChartInstance = null
let barChartInstance = null

// Chart data from EquipmentTable
const chartData = ref({
    pieData: { labels: [], datasets: [] },
    barData: { labels: [], datasets: [] }
})

// Handle chart data updates from EquipmentTable
const handleChartDataUpdate = (data) => {
    chartData.value = data
    updateCharts()
}

// Update charts with new data
const updateCharts = () => {
    if (pieChartInstance) {
        pieChartInstance.data = chartData.value.pieData.labels.length > 0
            ? chartData.value.pieData
            : {
                labels: ['No data'],
                datasets: [{ data: [1], backgroundColor: ['#E5E7EB'] }],
            }
        pieChartInstance.update()
    }

    if (barChartInstance) {
        barChartInstance.data = chartData.value.barData.labels.length > 0
            ? chartData.value.barData
            : {
                labels: ['No data'],
                datasets: [{
                    label: 'Equipment Count',
                    data: [0],
                    backgroundColor: '#7A0C23',
                }],
            }
        barChartInstance.update()
    }
}

// Initialize charts with responsive options
const initializeCharts = () => {
    // Destroy previous instances if they exist
    if (pieChartInstance) pieChartInstance.destroy()
    if (barChartInstance) barChartInstance.destroy()

    // Responsive chart options
    const isSmallScreen = window.innerWidth < 640
    
    // --- Pie Chart: Equipment Distribution by Person ---
    pieChartInstance = new Chart(pieChartRef.value, {
        type: 'pie',
        data: chartData.value.pieData.labels.length > 0 ? chartData.value.pieData : {
            labels: ['Loading...'],
            datasets: [{ data: [100], backgroundColor: ['#CCCCCC'] }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: isSmallScreen ? 'bottom' : 'right',
                    labels: {
                        boxWidth: isSmallScreen ? 8 : 12,
                        padding: isSmallScreen ? 8 : 15,
                        font: { size: isSmallScreen ? 8 : 10 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.raw} equipment items`
                        }
                    }
                }
            }
        }
    })

    // --- Bar Chart: Equipment Count by Building ---
    barChartInstance = new Chart(barChartRef.value, {
        type: 'bar',
        data: chartData.value.barData.labels.length > 0 ? chartData.value.barData : {
            labels: ['Loading...'],
            datasets: [{
                label: 'Equipment Count',
                data: [0],
                backgroundColor: '#7A0C23',
                borderColor: '#7A0C23',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { size: isSmallScreen ? 8 : 10 }
                    }
                },
                x: {
                    ticks: {
                        maxRotation: isSmallScreen ? 45 : 0,
                        minRotation: isSmallScreen ? 45 : 0,
                        font: { size: isSmallScreen ? 8 : 10 }
                    }
                }
            }
        }
    })
}

// Lifecycle hooks
onMounted(() => {
    window.addEventListener('resize', handleResize)
    setTimeout(initializeCharts, 100)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
    if (pieChartInstance) pieChartInstance.destroy()
    if (barChartInstance) barChartInstance.destroy()
})

// Watch for chart data updates
watch(chartData, updateCharts, { deep: true })

// Watch for window resize to update chart options
watch(isMobile, () => {
    initializeCharts()
})
</script>

<template>
    <Head title="Equipment Management" />

    <div class="flex h-screen overflow-hidden bg-gray-200">
        <!-- Sidebar with responsive behavior -->
        <Sidebar 
            :sidebarOpen="sidebarOpen" 
            :class="[
                'fixed lg:relative z-30 h-full transition-transform duration-300 ease-in-out',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
            ]"
        />

        <!-- Overlay for mobile when sidebar is open -->
        <div 
            v-if="sidebarOpen && isMobile" 
            @click="sidebarOpen = false"
            class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden"
        ></div>

        <div class="flex-1 flex flex-col w-full lg:w-auto overflow-hidden">
            <Navbar @toggleSidebar="toggleSidebar" :sidebarOpen="sidebarOpen" />

            <main class="flex-1 overflow-y-auto p-2 sm:p-4">
                <div class="space-y-3 sm:space-y-4">
                    <!-- Main Equipment Table -->
                    <div>
                        <EquipmentTable @chart-data-update="handleChartDataUpdate" />
                    </div>

                    <!-- Charts Section - Responsive Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4">
                        <!-- Pie Chart Container -->
                        <div class="bg-white shadow-lg rounded-xl p-3 sm:p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2 sm:mb-3 gap-2">
                                <h2 class="text-sm sm:text-lg font-bold text-gray-800">Equipment Distribution by Person</h2>
                                <div class="flex items-center text-xs sm:text-sm text-gray-500">
                                    <div class="w-2 h-2 sm:w-3 sm:h-3 bg-blue-500 rounded-full mr-1"></div>
                                    <span>Total: {{ chartData.pieData.datasets[0]?.data?.reduce((a, b) => a + b, 0) || 0 }} items</span>
                                </div>
                            </div>
                            <div class="h-[200px] sm:h-[280px] lg:h-[320px] relative">
                                <canvas id="pieChart" ref="pieChartRef" class="w-full h-full"></canvas>
                            </div>
                        </div>

                        <!-- Bar Chart Container -->
                        <div class="bg-white shadow-lg rounded-xl p-3 sm:p-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2 sm:mb-3 gap-2">
                                <h2 class="text-sm sm:text-lg font-bold text-gray-800">Equipment Count by Building</h2>
                                <div class="flex items-center text-xs sm:text-sm text-gray-500">
                                    <div class="w-2 h-2 sm:w-3 sm:h-3 bg-[#7A0C23] rounded-full mr-1"></div>
                                    <span>Buildings: {{ chartData.barData.labels?.length || 0 }}</span>
                                </div>
                            </div>
                            <div class="h-[200px] sm:h-[280px] lg:h-[320px] relative">
                                <canvas id="barChart" ref="barChartRef" class="w-full h-full"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Responsive transitions */
.sidebar-transition {
    transition: transform 0.3s ease-in-out;
}

/* Mobile optimizations */
@media (max-width: 640px) {
    .p-2 {
        padding: 0.5rem;
    }
    
    .space-y-3 > * + * {
        margin-top: 0.75rem;
    }
}
</style>