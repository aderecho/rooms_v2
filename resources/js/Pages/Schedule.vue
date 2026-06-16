<script setup>
import Layout from '@/Layouts/schedulelayout.vue';

defineProps({
    schedules: { type: Array, default: () => [] },
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
