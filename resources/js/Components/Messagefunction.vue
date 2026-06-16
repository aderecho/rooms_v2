<template>
  <div class="fixed top-6 right-6 space-y-3 z-[9999] max-w-sm">

    <!-- Create Success Toast -->
    <transition name="toast">
      <div
        v-if="showCreateSuccess"
        class="bg-green-600 text-white px-5 py-4 rounded-lg shadow-lg flex items-center justify-between"
      >
        <div class="flex items-center">
          <IconButton icon="check" size="sm" color="white" class="mr-3 hover:bg-green-700/20" />
          <div>
            <p class="font-semibold">Successfully Created!</p>
            <p class="text-green-100 text-sm mt-0.5">
              <slot name="create-message">{{ createMessage }}</slot>
            </p>
          </div>
        </div>
        <IconButton
          icon="times"
          size="sm"
          color="white"
          @click="$emit('close-create')"
          class="hover:bg-green-700/30 ml-2"
        />
      </div>
    </transition>

    <!-- Edit Success Toast -->
    <transition name="toast">
      <div
        v-if="showEditSuccess"
        class="bg-green-600 text-white px-5 py-4 rounded-lg shadow-lg flex items-center justify-between"
      >
        <div class="flex items-center">
          <IconButton icon="edit" size="sm" color="white" class="mr-3 hover:bg-green-700/20" />
          <div>
            <p class="font-semibold">Successfully Updated!</p>
            <p class="text-green-100 text-sm mt-0.5">
              <slot name="edit-message">{{ editMessage }}</slot>
            </p>
          </div>
        </div>
        <IconButton
          icon="times"
          size="sm"
          color="white"
          @click="$emit('close-edit')"
          class="hover:bg-green-700/30 ml-2"
        />
      </div>
    </transition>

    <!-- Delete Success Toast -->
    <transition name="toast">
      <div
        v-if="showDeleteSuccess"
        class="bg-green-600 text-white px-5 py-4 rounded-lg shadow-lg flex items-center justify-between"
      >
        <div class="flex items-center">
          <IconButton icon="check" size="sm" color="white" class="mr-3 hover:bg-green-700/20" />
          <div>
            <p class="font-semibold">Successfully Deleted!</p>
            <p class="text-green-100 text-sm mt-0.5">
              <slot name="delete-message">{{ deleteMessage }}</slot>
            </p>
          </div>
        </div>
        <IconButton
          icon="times"
          size="sm"
          color="white"
          @click="$emit('close-delete')"
          class="hover:bg-green-700/30 ml-2"
        />
      </div>
    </transition>

    <!-- Error Toast -->
    <transition name="toast">
      <div
        v-if="showError"
        class="bg-red-500 text-white px-5 py-4 rounded-lg shadow-lg flex items-center justify-between"
      >
        <div class="flex items-center">
          <IconButton icon="exclamation" size="sm" color="white" class="mr-3 hover:bg-red-600/20" />
          <div>
            <p class="font-semibold">Error!</p>
            <p class="text-red-100 text-sm mt-0.5">
              <slot name="error-message">{{ errorMessage }}</slot>
            </p>
          </div>
        </div>
        <IconButton
          icon="times"
          size="sm"
          color="white"
          @click="$emit('close-error')"
          class="hover:bg-red-600/30 ml-2"
        />
      </div>
    </transition>

    <!-- Info Toast -->
    <transition name="toast">
      <div
        v-if="showInfo"
        class="bg-blue-600 text-white px-5 py-4 rounded-lg shadow-lg flex items-center justify-between"
      >
        <div class="flex items-center">
          <IconButton icon="info" size="sm" color="white" class="mr-3 hover:bg-blue-700/20" />
          <div>
            <p class="font-semibold">Information</p>
            <p class="text-blue-100 text-sm mt-0.5">
              <slot name="info-message">{{ infoMessage }}</slot>
            </p>
          </div>
        </div>
        <IconButton
          icon="times"
          size="sm"
          color="white"
          @click="$emit('close-info')"
          class="hover:bg-blue-700/30 ml-2"
        />
      </div>
    </transition>

  </div>
</template>

<script setup>
import IconButton from './IconButton.vue';
import { watch } from 'vue';

const props = defineProps({
  showCreateSuccess: { type: Boolean, default: false },
  showEditSuccess: { type: Boolean, default: false },
  showDeleteSuccess: { type: Boolean, default: false },
  showError: { type: Boolean, default: false },
  showInfo: { type: Boolean, default: false },

  // Default messages for all toast types
  createMessage: { type: String, default: 'Successfully created!' },
  editMessage: { type: String, default: 'Successfully updated!' },
  deleteMessage: { type: String, default: 'Successfully deleted!' },
  errorMessage: { type: String, default: 'An error occurred!' },
  infoMessage: { type: String, default: 'Information message.' },
});

const emit = defineEmits([
  'close-create',
  'close-edit',
  'close-delete',
  'close-error',
  'close-info'
]);

// Auto-hide function
const autoHide = (propName, emitName, duration = 5000) => {
  watch(() => props[propName], (newVal) => {
    if (newVal) {
      setTimeout(() => {
        emit(emitName);
      }, duration);
    }
  });
};

autoHide('showCreateSuccess', 'close-create');
autoHide('showEditSuccess', 'close-edit');
autoHide('showDeleteSuccess', 'close-delete');
autoHide('showError', 'close-error');
autoHide('showInfo', 'close-info');
</script>


<style scoped>
/* Toast Transition Styles */
.toast-enter-active,
.toast-leave-active {
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%) scale(0.9);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%) scale(0.9);
}
</style>
