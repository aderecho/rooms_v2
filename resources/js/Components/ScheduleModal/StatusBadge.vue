<script setup>
import { computed } from 'vue';
import { getAppointmentStatusMeta, normalizeAppointmentStatus } from '@/utils/scheduleStatus';

const props = defineProps({
    status: {
        type: String,
        default: 'pending',
    },
    size: {
        type: String,
        default: 'sm',
        validator: (value) => ['sm', 'md'].includes(value),
    },
    showDot: {
        type: Boolean,
        default: true,
    },
    muted: {
        type: Boolean,
        default: false,
    },
    variant: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'muted', 'header'].includes(value),
    },
});

/** Static class maps so Tailwind always includes these utilities in the build. */
const STATUS_BADGE_STYLES = {
    pending: {
        default: { badge: 'bg-gray-200 text-gray-800 border-gray-400', dot: 'bg-gray-600' },
        muted: { badge: 'bg-gray-200 text-gray-600 border-gray-300', dot: 'bg-gray-600' },
        header: { badge: 'bg-gray-500 text-white border-gray-400', dot: 'bg-gray-200' },
    },
    in_progress: {
        default: { badge: 'bg-blue-100 text-blue-800 border-blue-400', dot: 'bg-blue-600' },
        muted: { badge: 'bg-blue-200 text-blue-800 border-blue-400', dot: 'bg-blue-700' },
        header: { badge: 'bg-blue-600 text-white border-blue-500', dot: 'bg-blue-200' },
    },
    approved: {
        default: { badge: 'bg-green-100 text-green-800 border-green-400', dot: 'bg-green-600' },
        muted: { badge: 'bg-green-200 text-green-800 border-green-400', dot: 'bg-green-700' },
        header: { badge: 'bg-green-600 text-white border-green-500', dot: 'bg-green-200' },
    },
    completed: {
        default: { badge: 'bg-emerald-100 text-emerald-900 border-emerald-500', dot: 'bg-emerald-700' },
        muted: { badge: 'bg-green-200 text-emerald-900 border-green-500', dot: 'bg-emerald-800' },
        header: { badge: 'bg-green-700 text-white border-green-600', dot: 'bg-green-200' },
    },
    rejected: {
        default: { badge: 'bg-red-100 text-red-800 border-red-400', dot: 'bg-red-600' },
        muted: { badge: 'bg-red-200 text-red-800 border-red-400', dot: 'bg-red-700' },
        header: { badge: 'bg-red-600 text-white border-red-500', dot: 'bg-red-200' },
    },
    cancelled: {
        default: { badge: 'bg-slate-200 text-slate-800 border-slate-400', dot: 'bg-slate-600' },
        muted: { badge: 'bg-gray-200 text-gray-600 border-gray-400', dot: 'bg-gray-600' },
        header: { badge: 'bg-gray-600 text-white border-gray-500', dot: 'bg-gray-200' },
    },
};

const FALLBACK_STYLES = {
    default: { badge: 'bg-gray-100 text-gray-800 border-gray-300', dot: 'bg-gray-500' },
    muted: { badge: 'bg-gray-200 text-gray-600 border-gray-300', dot: 'bg-gray-500' },
    header: { badge: 'bg-gray-500 text-white border-gray-400', dot: 'bg-gray-200' },
};

const meta = computed(() => getAppointmentStatusMeta(props.status));

const resolvedVariant = computed(() => {
    if (props.variant !== 'default') return props.variant;
    return props.muted ? 'muted' : 'default';
});

const styleSet = computed(() => {
    const key = normalizeAppointmentStatus(props.status);
    return STATUS_BADGE_STYLES[key]?.[resolvedVariant.value] ?? FALLBACK_STYLES[resolvedVariant.value];
});

const badgeClasses = computed(() => styleSet.value.badge);
const dotClasses = computed(() => styleSet.value.dot);

const sizeClasses = computed(() => (
    props.size === 'md'
        ? 'text-sm px-3 py-1.5'
        : 'text-xs px-2.5 py-1'
));

const dotSizeClasses = computed(() => (
    props.size === 'md' ? 'h-2.5 w-2.5' : 'h-2 w-2'
));
</script>

<template>
    <span
        class="status-badge inline-flex items-center gap-1.5 rounded-full border font-semibold whitespace-nowrap"
        :class="[badgeClasses, sizeClasses]"
        :title="`${meta.label} status`"
    >
        <span
            v-if="showDot"
            class="status-badge-dot rounded-full shrink-0"
            :class="[dotClasses, dotSizeClasses]"
            aria-hidden="true"
        />
        {{ meta.label }}
    </span>
</template>
