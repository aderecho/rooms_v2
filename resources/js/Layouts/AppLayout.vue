<template>
  <div class="bg-gray-200 font-sans min-h-screen">
    <Navbar :user="$page.props.auth.user" @toggle-sidebar="toggleSidebar" />

    <div class="flex pt-14 min-h-screen">
      <Sidebar
        v-show="sidebarVisible"
        :sidebar-open="sidebarVisible"
        :user="$page.props.auth.user"
        class="fixed top-0 left-0 h-full z-20 w-64 lg:relative"
      />

      <main class="flex-1 p-6 transition-all duration-300" :class="sidebarVisible ? 'lg:ml-64' : ''">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';

const sidebarVisible = ref(true);

const toggleSidebar = () => {
  sidebarVisible.value = !sidebarVisible.value;
};
</script>
