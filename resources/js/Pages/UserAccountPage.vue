<script setup>
import Layout from '@/Layouts/UserAccountLayout.vue';

defineProps({
  initialUsers: { type: Array, default: () => [] },
  colleges: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({}) },
  canManageUsers: { type: Boolean, default: false },
  pagination: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});
</script>

<template>
  <Layout
    :initial-users="initialUsers"
    :colleges="colleges"
    :departments="departments"
    :stats="stats"
    :can-manage-users="canManageUsers"
    :pagination="pagination"
    :filters="filters"
  />
</template>
