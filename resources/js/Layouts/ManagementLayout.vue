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

        <div
            :class="[
                'app-frame',
                sidebarVisible ? 'lg:grid lg:grid-cols-[245px_minmax(0,1fr)]' : 'lg:grid lg:grid-cols-[minmax(0,1fr)]'
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
            <button
                v-if="sidebarVisible"
                type="button"
                class="fixed inset-0 z-[9] bg-slate-950/35 lg:hidden"
                aria-label="Close sidebar"
                @click="sidebarVisible = false"
            ></button>

            <main class="app-main">
                <header class="app-page-header">
                    <Breadcrumbs v-if="breadcrumb" :trail="breadcrumb" />
                    <h1 v-else class="app-page-title">{{ title }}</h1>
                </header>

                <section class="app-content-panel">
                    <slot />
                </section>
            </main>
        </div>
    </div>
</template>
