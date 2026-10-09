<script setup>
import Layout from '@/Layouts/schedulelayout.vue';

defineProps({
    schedules: { type: Array, default: () => [] },
    schedulePagination: { type: Object, default: () => null },
    scheduleFilters: { type: Object, default: () => ({}) },
    scheduleCounts: { type: Object, default: () => ({}) },
    matchingPendingCount: { type: Number, default: 0 },
    rooms: { type: Array, default: () => [] },
    roomEquipmentQuantities: { type: Object, default: () => ({}) },
    globalEquipmentQuantities: { type: Object, default: () => ({}) },
    faculty: { type: Array, default: () => [] },
    requesters: { type: Array, default: () => [] },
    currentRequester: { type: String, default: '' },
    currentUserRole: { type: String, default: '' },
    terms: { type: Array, default: () => [] },
});
</script>

<template>
    <Layout
        :schedules="schedules"
        :schedule-pagination="schedulePagination" :schedule-filters="scheduleFilters"
        :schedule-counts="scheduleCounts" :matching-pending-count="matchingPendingCount"
        :rooms="rooms"
        :room-equipment-quantities="roomEquipmentQuantities"
        :global-equipment-quantities="globalEquipmentQuantities"
        :faculty="faculty"
        :requesters="requesters"
        :current-requester="currentRequester"
        :current-user-role="currentUserRole"
        :terms="terms"
    />
</template>
