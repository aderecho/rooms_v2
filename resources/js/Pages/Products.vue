<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
    const page = usePage();
    const products = computed(() => page.props.products);
</script>

<template>
    <pre>{{ products }}</pre>
</template>