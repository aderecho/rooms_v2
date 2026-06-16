<!-- Components/UI/Icons.vue -->
<script setup>
import {
  faEye,
  faPenToSquare,
  faTrash,
  faPlus,
  faSearch,
  faTimes,
  faCheck,
  faChevronLeft,
  faChevronRight,
  faList,
  faInfoCircle,
  faExclamationTriangle
} from '@fortawesome/free-solid-svg-icons';

// Export as a module
export const icons = {
  eye: faEye,
  edit: faPenToSquare,
  delete: faTrash,
  plus: faPlus,
  search: faSearch,
  times: faTimes,
  check: faCheck,
  chevronLeft: faChevronLeft,
  chevronRight: faChevronRight,
  list: faList,
  info: faInfoCircle,
  warning: faExclamationTriangle
};

export const iconColors = {
  eye: 'blue',
  edit: 'green',
  delete: 'red',
  plus: 'green',
  search: 'gray',
  times: 'gray',
  check: 'green',
  chevronLeft: 'gray',
  chevronRight: 'gray',
  list: 'gray',
  info: 'blue',
  warning: 'orange'
};
</script>

<template>
  <div style="display: none;"></div>
</template>
