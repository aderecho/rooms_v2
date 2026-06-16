<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faXmark,
    faClock,
    faDoorOpen,
    faTag,
    faCalendarDay,
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    isVisible: Boolean,
    position: { type: Object, default: () => null },
    initialDate: { type: Date, default: null },
    initialRoom: { type: String, default: '' },
    rooms: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'save', 'moreOptions']);

const titleInput = ref(null);

const title = ref('');
const room = ref('');
const startTime = ref('09:00');
const endTime = ref('10:00');
const eventType = ref('Meeting');

const toHHMM = (h, m) => `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;

watch(
    () => props.isVisible,
    (visible) => {
        if (!visible) return;
        title.value = '';
        eventType.value = 'Meeting';
        room.value = props.initialRoom || props.rooms[0] || '';

        const d = props.initialDate ? new Date(props.initialDate) : new Date();
        startTime.value = toHHMM(d.getHours(), d.getMinutes());
        const end = new Date(d);
        end.setHours(end.getHours() + 1);
        endTime.value = toHHMM(end.getHours(), end.getMinutes());

        nextTick(() => titleInput.value?.focus());
    },
    { immediate: true }
);

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const positionStyle = computed(() => {
    const popoverWidth = 340;
    const popoverHeight = 360;
    let x = props.position?.x ?? 0;
    let y = props.position?.y ?? 0;

    x += 12;

    if (typeof window !== 'undefined') {
        if (x + popoverWidth > window.innerWidth) {
            x = window.innerWidth - popoverWidth - 16;
        }
        if (y + popoverHeight > window.innerHeight) {
            y = window.innerHeight - popoverHeight - 16;
        }
        if (x < 12) x = 12;
        if (y < 12) y = 12;
    }

    return { top: `${y}px`, left: `${x}px` };
});

const typeOptions = [
    { id: 'Class', label: 'Class' },
    { id: 'Meeting', label: 'Meeting' },
    { id: 'Event', label: 'Event' },
    { id: 'Other type of activity', label: 'Other' },
];

const onSave = () => {
    if (!title.value.trim()) {
        titleInput.value?.focus();
        return;
    }
    if (!room.value) return;
    if (endTime.value <= startTime.value) {
        alert('End time must be after start time.');
        return;
    }

    emit('save', {
        title: title.value.trim(),
        room: room.value,
        eventType: eventType.value,
        startTime: startTime.value,
        endTime: endTime.value,
        date: props.initialDate,
    });
};

const onMoreOptions = () => {
    emit('moreOptions', {
        title: title.value.trim(),
        room: room.value,
        eventType: eventType.value,
        startTime: startTime.value,
        endTime: endTime.value,
        date: props.initialDate,
    });
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isVisible"
            class="fixed inset-0 z-40"
            @click.self="$emit('close')"
        >
            <div
                class="fixed bg-white rounded-xl shadow-2xl border border-yellow-300 z-50 w-[340px] overflow-hidden"
                :style="positionStyle"
                @click.stop
            >
                <!-- Header -->
                <div class="bg-[#7A0C23] px-4 py-2.5 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-white">
                        <FontAwesomeIcon :icon="faCalendarDay" class="w-3.5 h-3.5" />
                        <h3 class="text-sm font-semibold">New Appointment</h3>
                    </div>
                    <button
                        @click="$emit('close')"
                        class="text-white hover:bg-red-900/40 p-1 rounded transition"
                        title="Close"
                    >
                        <FontAwesomeIcon :icon="faXmark" class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Body -->
                <div class="p-4 space-y-3">
                    <div>
                        <input
                            ref="titleInput"
                            v-model="title"
                            type="text"
                            placeholder="Add title"
                            class="w-full text-base font-medium border-0 border-b-2 border-gray-200 focus:border-[#7A0C23] outline-none py-2 px-1 transition placeholder:text-gray-400"
                            @keyup.enter="onSave"
                        />
                    </div>

                    <div class="flex items-start gap-2">
                        <FontAwesomeIcon :icon="faTag" class="w-3.5 h-3.5 text-gray-400 mt-2" />
                        <div class="flex flex-wrap gap-1.5 flex-1">
                            <button
                                v-for="t in typeOptions"
                                :key="t.id"
                                type="button"
                                @click="eventType = t.id"
                                :class="[
                                    'text-xs px-3 py-1 rounded-full font-medium transition border',
                                    eventType === t.id
                                        ? 'bg-[#7A0C23] text-white border-[#7A0C23]'
                                        : 'bg-white text-gray-600 border-gray-200 hover:border-[#7A0C23] hover:text-[#7A0C23]'
                                ]"
                            >
                                {{ t.label }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-sm">
                        <FontAwesomeIcon :icon="faClock" class="w-3.5 h-3.5 text-gray-400" />
                        <span class="text-gray-700 font-medium">{{ formatDate(initialDate) }}</span>
                    </div>
                    <div class="flex items-center gap-2 ml-6">
                        <input
                            v-model="startTime"
                            type="time"
                            class="text-sm border border-gray-200 rounded-md px-2 py-1.5 w-28 focus:border-[#7A0C23] outline-none"
                        />
                        <span class="text-gray-400">–</span>
                        <input
                            v-model="endTime"
                            type="time"
                            class="text-sm border border-gray-200 rounded-md px-2 py-1.5 w-28 focus:border-[#7A0C23] outline-none"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <FontAwesomeIcon :icon="faDoorOpen" class="w-3.5 h-3.5 text-gray-400" />
                        <select
                            v-model="room"
                            class="text-sm border border-gray-200 rounded-md px-2 py-1.5 flex-1 focus:border-[#7A0C23] outline-none bg-white"
                        >
                            <option v-if="rooms.length === 0" value="">No rooms available</option>
                            <option v-for="r in rooms" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-gray-100 px-4 py-2.5 flex items-center justify-between bg-gray-50">
                    <button
                        type="button"
                        @click="onMoreOptions"
                        class="text-sm text-[#7A0C23] hover:underline font-medium"
                    >
                        More options
                    </button>
                    <button
                        type="button"
                        @click="onSave"
                        class="px-5 py-1.5 bg-[#7A0C23] text-white text-sm rounded-lg hover:bg-red-800 transition font-semibold shadow-sm"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
