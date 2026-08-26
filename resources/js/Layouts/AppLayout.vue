<template>
  <div class="app-shell">
    <Navbar :user="$page.props.auth.user" @toggle-sidebar="toggleSidebar" />

    <div class="app-frame pt-0">
      <Sidebar
        v-show="sidebarVisible"
        :sidebar-open="sidebarVisible"
        :user="$page.props.auth.user"
        class="fixed left-0 top-[4.5rem] z-20 h-[calc(100vh-4.5rem)] lg:fixed lg:top-0 lg:h-screen"
      />
      <button
        v-if="sidebarVisible"
        type="button"
        class="fixed inset-0 z-[19] bg-slate-950/35 lg:hidden"
        aria-label="Close sidebar"
        @click="sidebarVisible = false"
      ></button>

      <main class="app-main transition-all duration-300" :class="sidebarVisible ? 'lg:ml-[250px]' : 'lg:ml-0'">
        <section class="app-content-panel">
          <slot />
        </section>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';

const sidebarVisible = ref(typeof window === 'undefined' ? true : window.innerWidth >= 1024);

const toggleSidebar = () => {
  sidebarVisible.value = !sidebarVisible.value;
};
</script>
