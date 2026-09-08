<script>
// Shared across instances so closing a nested dialog keeps the page locked.
let openDialogs = 0
let originalOverflow = ''
</script>

<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'

const dialogElement = ref(null)
onMounted(() => {
  if (openDialogs++ === 0) {
    originalOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
  }
  dialogElement.value.showModal()
})
onBeforeUnmount(() => {
  dialogElement.value?.close()
  if (--openDialogs === 0) document.body.style.overflow = originalOverflow
})
</script>

<template>
  <dialog ref="dialogElement" class="modal-dialog" aria-modal="true" @cancel.prevent>
    <slot />
  </dialog>
</template>

<style>
/* Native dialogs occupy the browser's top layer, outside page stacking contexts. */
dialog.modal-dialog {
  position: fixed;
  inset: 0;
  margin: 0;
  width: 100%;
  max-width: none;
  height: 100dvh;
  max-height: none;
  padding: 1.5rem;
  border: 0;
  color: inherit;
  overflow: auto;
  overscroll-behavior: contain;
}
dialog.modal-dialog[open] {
  display: flex;
  align-items: center;
  justify-content: center;
}
dialog.modal-dialog::backdrop { background: transparent; }
dialog.modal-dialog > :first-child {
  min-width: 0;
  max-height: calc(100dvh - 3rem);
  overflow-y: auto;
  overscroll-behavior: contain;
}
@media (max-width: 639px) {
  dialog.modal-dialog { padding: 0.75rem; }
  dialog.modal-dialog > :first-child { max-height: calc(100dvh - 1.5rem); }
}
</style>
