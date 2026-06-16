<script setup>
import { ref } from 'vue'
import Navbar from '@/Components/Navbar.vue'
import Sidebar from '@/Components/Sidebar.vue'
import CollegeTable from '@/Components/CollegeModals/CollegeTable.vue'

const sidebarVisible = ref(true)

const toggleSidebar = () => {
  sidebarVisible.value = !sidebarVisible.value
}
</script>

<template>
  <div class="flex flex-col h-screen bg-gray-200">

    <Navbar @toggleSidebar="toggleSidebar" />

    <div
      :class="[
        'flex flex-1 h-full overflow-hidden relative mt-14',
        sidebarVisible ? 'lg:grid lg:grid-cols-[256px_1fr]' : 'flex'
      ]"
    >
      <Sidebar
        :sidebarOpen="sidebarVisible"
        @toggleSidebar="toggleSidebar"
        :class="[
          'lg:relative lg:translate-x-0 lg:h-full',
          sidebarVisible ? 'lg:block' : 'lg:hidden'
        ]"
      />

      <main class="flex-1 p-6 overflow-y-auto transition-all duration-300">
        <div class="mb-6">
          <div class="flex justify-between items-start">
            <h1 class="text-xl md:text-2xl font-bold text-[#7A0C23]">
              College Management
            </h1>

            <div class="text-sm text-gray-500">
              <span>UPCEBU &gt; COLLEGE</span>
            </div>
          </div>
        </div>
        <CollegeTable />
      </main>
    </div>
  </div>
</template>
