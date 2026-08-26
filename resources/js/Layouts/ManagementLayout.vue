<script setup>
import { ref } from 'vue';
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    breadcrumb: {
        type: String,
        default: '',
    },
});

const sidebarVisible = ref(typeof window === 'undefined' ? true : window.innerWidth >= 1024);

const toggleSidebar = () => {
    sidebarVisible.value = !sidebarVisible.value;
};
</script>

<template>
    <div class="app-shell">
        <Navbar @toggleSidebar="toggleSidebar" />

        <div class="app-frame">
            <Sidebar
                :sidebarOpen="sidebarVisible"
                @toggleSidebar="toggleSidebar"
                :class="[
                    'fixed left-0 top-[4.5rem] z-20 h-[calc(100vh-4.5rem)] lg:fixed lg:top-0 lg:h-screen lg:translate-x-0',
                    sidebarVisible ? 'lg:block' : 'lg:hidden'
                ]"
            />
            <button
                v-if="sidebarVisible"
                type="button"
                class="fixed inset-0 z-[9] bg-slate-950/35 lg:hidden"
                aria-label="Close sidebar"
                @click="sidebarVisible = false"
            ></button>

            <main class="app-main transition-all duration-300" :class="sidebarVisible ? 'lg:ml-[250px]' : 'lg:ml-0'">
                <header class="app-page-header">
                    <div>
                        <Breadcrumbs v-if="breadcrumb" :trail="breadcrumb" />
                        <h1 class="app-page-title mt-2">{{ title }}</h1>
                    </div>
                </header>

                <section class="app-content-panel">
                    <slot />
                </section>
            </main>
        </div>
    </div>
</template>
