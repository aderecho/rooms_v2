<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ManagementLayout from '@/Layouts/ManagementLayout.vue';
import BuildingTable from '@/Components/BuildingModals/BuildingTable.vue';
import RoomTable from '@/Components/RoomModals/RoomTable.vue';

const page = usePage();
const filters = computed(() => page.props.filters || {});
const stats = computed(() => page.props.stats || {});
const activeTab = ref(filters.value.active_tab === 'rooms' ? 'rooms' : 'buildings');

watch(
    () => filters.value.active_tab,
    (tab) => {
        activeTab.value = tab === 'rooms' ? 'rooms' : 'buildings';
    }
);

const setTab = (tab) => {
    activeTab.value = tab;
    router.get('/BuildingDashboard', {
        ...filters.value,
        active_tab: tab,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <ManagementLayout title="Buildings & Rooms" breadcrumb="UPCEBU > BUILDINGS & ROOMS">
        <div class="space-y-5">
            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Buildings</div>
                    <div class="mt-2 text-3xl font-bold text-slate-950">{{ stats.buildings ?? 0 }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Rooms</div>
                    <div class="mt-2 text-3xl font-bold text-[#005740]">{{ stats.rooms ?? 0 }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Current Result</div>
                    <div class="mt-2 text-3xl font-bold text-slate-950">
                        {{ activeTab === 'rooms' ? (stats.filtered_rooms ?? 0) : ($page.props.buildings?.total ?? 0) }}
                    </div>
                </div>
            </section>

            <div class="flex w-fit items-center rounded-xl bg-slate-100 p-1">
                <button
                    type="button"
                    class="rounded-lg px-5 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'buildings' ? 'bg-[#005740] text-white shadow-sm' : 'text-slate-700 hover:bg-white'"
                    @click="setTab('buildings')"
                >
                    Buildings
                </button>
                <button
                    type="button"
                    class="rounded-lg px-5 py-2 text-sm font-semibold transition"
                    :class="activeTab === 'rooms' ? 'bg-[#005740] text-white shadow-sm' : 'text-slate-700 hover:bg-white'"
                    @click="setTab('rooms')"
                >
                    Rooms
                </button>
            </div>

            <BuildingTable
                v-if="activeTab === 'buildings'"
                :search="filters.building_search"
                search-route="/BuildingDashboard"
                search-param="building_search"
                :extra-params="{ active_tab: 'buildings' }"
            />
            <RoomTable
                v-else
                :search="filters.room_search"
                search-route="/BuildingDashboard"
                search-param="room_search"
                :extra-params="{ active_tab: 'rooms', building_id: filters.building_id }"
            />
        </div>
    </ManagementLayout>
</template>
