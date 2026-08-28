<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useAppDialog } from '@/Composables/useAppDialog.js';

const { dialog, closeDialog } = useAppDialog();
const confirmButton = ref(null);
let previouslyFocused = null;

const icon = {
    info: 'i',
    success: '✓',
    warning: '!',
    danger: '!',
};

const close = (confirmed = false) => closeDialog(confirmed);

const handleKeydown = (event) => {
    if (!dialog.open || event.key !== 'Escape') return;
    event.preventDefault();
    close(false);
};

watch(() => dialog.open, async (open) => {
    if (open) {
        previouslyFocused = document.activeElement;
        await nextTick();
        confirmButton.value?.focus();
    } else {
        previouslyFocused?.focus?.();
        previouslyFocused = null;
    }
});

onMounted(() => window.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="dialog.open"
                class="fixed inset-0 z-[1000] grid place-items-center bg-slate-950/65 p-4 backdrop-blur-[2px]"
                :role="dialog.showCancel ? 'dialog' : 'alertdialog'"
                aria-modal="true"
                aria-labelledby="app-dialog-title"
                aria-describedby="app-dialog-message"
                @click.self="close(false)"
            >
                <section class="w-full max-w-md overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl">
                    <div class="flex items-start gap-4 p-6">
                        <span
                            class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-xl font-black"
                            :class="{
                                'bg-sky-100 text-sky-800': dialog.variant === 'info',
                                'bg-emerald-100 text-emerald-800': dialog.variant === 'success',
                                'bg-amber-100 text-amber-900': dialog.variant === 'warning',
                                'bg-red-100 text-red-800': dialog.variant === 'danger',
                            }"
                            aria-hidden="true"
                        >{{ icon[dialog.variant] || icon.info }}</span>
                        <div class="min-w-0 flex-1">
                            <h2 id="app-dialog-title" class="text-xl font-extrabold text-slate-950">{{ dialog.title }}</h2>
                            <p id="app-dialog-message" class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ dialog.message }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                        <button v-if="dialog.showCancel" type="button" class="app-button-secondary" @click="close(false)">
                            {{ dialog.cancelLabel }}
                        </button>
                        <button
                            ref="confirmButton"
                            type="button"
                            class="rounded-lg px-5 py-2.5 text-sm font-extrabold text-white focus:outline-none focus:ring-2 focus:ring-offset-2"
                            :class="dialog.variant === 'danger'
                                ? 'bg-red-700 hover:bg-red-800 focus:ring-red-700'
                                : 'bg-[#005740] hover:bg-[#004432] focus:ring-[#005740]'"
                            @click="close(true)"
                        >
                            {{ dialog.confirmLabel }}
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
