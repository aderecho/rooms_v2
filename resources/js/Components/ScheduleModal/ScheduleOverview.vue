<script setup>
import { computed } from 'vue';
const props = defineProps({ events: { type: Array, default: () => [] } });
const count = (status) => props.events.filter(event => (event.extendedProps?.status || 'pending') === status).length;
const metrics = computed(() => [
    { label: 'All Schedules', value: props.events.length, description: 'Total schedule records', color: '#005740', icon: '▤', bar: 1 },
    { label: 'Pending', value: count('pending'), description: 'Awaiting your review', color: '#df9414', icon: '◷', bar: 0 },
    { label: 'Approved', value: count('approved'), description: 'Confirmed schedules', color: '#237c60', icon: '✓', bar: 1 },
    { label: 'Rejected', value: count('rejected'), description: 'Schedules declined', color: '#bd3935', icon: '×', bar: 2 },
]);
const distribution = computed(() => [metrics.value[1], metrics.value[2], metrics.value[3]]);
const distributionTotal = computed(() => distribution.value.reduce((sum, item) => sum + item.value, 0));
const otherCount = computed(() => props.events.length - distributionTotal.value);
const max = computed(() => Math.max(1, ...distribution.value.map(item => item.value)));
const vertices = [[110, 35], [184, 163], [36, 163]];
const points = (scale) => vertices.map(([x, y]) => `${110 + (x - 110) * scale},${120 + (y - 120) * scale}`).join(' ');
const dataPoints = computed(() => distribution.value.map((item, index) => {
    const ratio = item.value / max.value;
    return { x: 110 + (vertices[index][0] - 110) * ratio, y: 120 + (vertices[index][1] - 120) * ratio };
}));
const polygon = computed(() => dataPoints.value.map(point => `${point.x},${point.y}`).join(' '));
const percentage = (value) => distributionTotal.value ? Math.round(value / distributionTotal.value * 100) : 0;
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[1.65fr_1fr]">
        <div class="grid gap-4 sm:grid-cols-2">
            <article v-for="metric in metrics" :key="metric.label" class="relative min-h-[158px] overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" :class="metric.label === 'Pending' ? 'ring-1 ring-[#005740] !border-[#005740]' : ''">
                <p class="text-sm text-slate-500">{{ metric.label }}</p>
                <div class="mt-3 flex items-center gap-3">
                    <p class="text-4xl font-semibold tracking-tight text-slate-900">{{ metric.value }}</p>
                    <span class="grid h-8 w-8 place-items-center rounded-full border border-slate-200 text-base" :style="{ color: metric.color }" aria-hidden="true">{{ metric.icon }}</span>
                </div>
                <p class="mt-3 pr-16 text-xs text-slate-500">{{ metric.description }}</p>
                <div class="absolute bottom-5 right-5 flex items-end gap-1.5" aria-hidden="true">
                    <span v-for="(height, index) in [8, 48, 16]" :key="index" class="w-5 rounded-md" :style="{ height: `${height}px`, backgroundColor: metric.label === 'All Schedules' || index === metric.bar ? metric.color : '#edf3f0' }"></span>
                </div>
            </article>
        </div>
        <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-semibold text-slate-900">Schedule overview</h2>
            <p class="mt-1 text-xs text-slate-500">Current schedule distribution</p>
            <div class="my-auto flex flex-col items-center gap-3 py-4 sm:flex-row">
                <svg viewBox="0 0 220 200" class="w-full max-w-[220px] shrink-0 sm:w-[44%]" role="img" :aria-label="`Schedule counts: ${distribution.map(item => `${item.label} ${item.value}`).join(', ')}`">
                    <polygon v-for="scale in [0.25, 0.5, 0.75, 1]" :key="scale" :points="points(scale)" fill="none" stroke="#dce7e2" stroke-dasharray="3 3" />
                    <line v-for="(vertex, index) in vertices" :key="index" x1="110" y1="120" :x2="vertex[0]" :y2="vertex[1]" stroke="#dce7e2" stroke-dasharray="3 3" />
                    <polygon :points="polygon" fill="#005740" fill-opacity="0.13" stroke="#005740" stroke-width="2" />
                    <circle v-for="(point, index) in dataPoints" :key="index" :cx="point.x" :cy="point.y" r="3" fill="#005740" />
                    <g fill="#52627a" font-size="11" text-anchor="middle"><text x="110" y="18">Pending</text><text x="185" y="187">Approved</text><text x="35" y="187">Rejected</text></g>
                </svg>
                <div class="w-full space-y-5">
                    <div v-for="item in distribution" :key="item.label" class="flex items-center justify-between gap-3 text-sm">
                        <span class="flex items-center gap-2 text-slate-600"><i class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: item.color }"></i>{{ item.label }}</span>
                        <span class="whitespace-nowrap font-semibold text-slate-900">{{ percentage(item.value) }}% <span class="text-xs font-normal text-slate-500">({{ item.value }})</span></span>
                    </div>
                </div>
            </div>
            <p class="text-xs text-slate-500">Chart total {{ distributionTotal }}<span v-if="otherCount"> · Other statuses {{ otherCount }}</span></p>
        </article>
    </div>
</template>
