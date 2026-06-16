<!-- Components/UI/IconButton.vue -->
<script setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
  faEye, faPenToSquare, faTrash, faPlus,
  faSearch, faTimes, faCheck, faWarning,
  faChevronLeft, faChevronRight, faList,
  faInfoCircle, faExclamationTriangle
} from '@fortawesome/free-solid-svg-icons';
import { computed } from 'vue';

const props = defineProps({
  icon: {
    type: String,
    required: true,
    validator: (value) => [
      'eye', 'edit', 'delete', 'plus', 'search', 'times',
      'check', 'warning', 'chevronLeft', 'chevronRight',
      'list', 'info', 'exclamation'
    ].includes(value)
  },
  title: String,
  size: {
    type: String,
    default: 'sm',
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  disabled: { type: Boolean, default: false },
  color: String,
  outlined: { type: Boolean, default: false },
  loading: { type: Boolean, default: false }
});

// Emit click events
const emit = defineEmits(['click']);

const handleClick = (event) => {
  if (!props.disabled && !props.loading) {
    emit('click', event);
  }
};

// Local icon map
const iconMap = {
  eye: faEye,
  edit: faPenToSquare,
  delete: faTrash,
  plus: faPlus,
  search: faSearch,
  times: faTimes,
  check: faCheck,
  warning: faWarning,
  chevronLeft: faChevronLeft,
  chevronRight: faChevronRight,
  list: faList,
  info: faInfoCircle,
  exclamation: faExclamationTriangle
};

// Automatic color mapping (edit: green, eye: blue, delete: red)
const autoColors = {
  eye: 'blue',
  edit: 'green',
  delete: 'red',
  plus: 'green',
  search: 'gray',
  times: 'gray',
  check: 'green',
  warning: 'orange',
  chevronLeft: 'gray',
  chevronRight: 'gray',
  list: 'gray',
  info: 'blue',
  exclamation: 'orange'
};

// Get the color - using specific color classes instead of dynamic ones
const getColorClass = () => {
  const color = props.color || autoColors[props.icon] || 'gray';

  // Return specific Tailwind classes for each color
  const colorMap = {
    blue: 'text-blue-500 hover:bg-blue-50 border-blue-300',
    green: 'text-green-500 hover:bg-green-50 border-green-300',
    red: 'text-red-500 hover:bg-red-50 border-red-300',
    gray: 'text-gray-500 hover:bg-gray-50 border-gray-300',
    orange: 'text-orange-500 hover:bg-orange-50 border-orange-300',
    white: 'text-white hover:bg-white/10 border-white'
  };

  return colorMap[color] || colorMap.gray;
};

// Get text color class for outlined variant
const getOutlinedTextColor = () => {
  const color = props.color || autoColors[props.icon] || 'gray';

  const colorMap = {
    blue: 'text-blue-600',
    green: 'text-green-600',
    red: 'text-red-600',
    gray: 'text-gray-600',
    orange: 'text-orange-600',
    white: 'text-white'
  };

  return colorMap[color] || colorMap.gray;
};

// Size mapping with more options
const sizeMap = {
  xs: 'h-3 w-3',
  sm: 'h-4 w-4',
  md: 'h-5 w-5',
  lg: 'h-6 w-6',
  xl: 'h-8 w-8'
};

// Button size padding
const paddingMap = {
  xs: 'p-1',
  sm: 'p-1.5',
  md: 'p-2',
  lg: 'p-2.5',
  xl: 'p-3'
};

// Determine button classes based on color and variant
const buttonClasses = computed(() => {
  const colorClass = getColorClass();
  const textColorClass = getOutlinedTextColor();
  const classes = [
    'inline-flex items-center justify-center',
    'transition-all duration-200',
    'focus:outline-none focus:ring-2 focus:ring-offset-1',
    (props.disabled || props.loading) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:scale-105 active:scale-95'
  ];

  if (props.outlined) {
    classes.push(
      'border rounded-md',
      'hover:bg-gray-50 font-medium'
    );

    // Handle special white color for outlined
    if (props.color === 'white') {
      classes.push('border-white text-white hover:bg-white/10');
    } else {
      classes.push(`border-gray-300 ${textColorClass}`);
    }

    // Size-specific padding
    if (props.size === 'xs') classes.push('px-2 py-0.5 text-xs');
    else if (props.size === 'sm') classes.push('px-3 py-1.5 text-sm');
    else if (props.size === 'md') classes.push('px-4 py-2');
    else classes.push('px-5 py-2.5');
  } else {
    classes.push('rounded-full', paddingMap[props.size], colorClass);
  }

  return classes;
});

// Focus ring color based on button type
const focusRingColor = computed(() => {
  if (props.color === 'white') return 'focus:ring-white/50';
  return 'focus:ring-gray-200';
});
</script>

<template>
  <button
    :title="title"
    :disabled="disabled || loading"
    :class="[...buttonClasses, focusRingColor]"
    @click="handleClick"
  >
    <!-- Loading spinner -->
    <svg
      v-if="loading"
      class="animate-spin h-4 w-4 text-current"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>

    <!-- Icon -->
    <FontAwesomeIcon
      v-else
      :icon="iconMap[icon]"
      :class="sizeMap[size]"
    />

    <!-- Text for outlined buttons -->
    <span
      v-if="outlined && !loading"
      :class="[
        'ml-1.5 font-medium',
        size === 'xs' ? 'text-xs' :
        size === 'sm' ? 'text-sm' :
        'text-base'
      ]"
    >
      <slot>{{ title }}</slot>
    </span>
  </button>
</template>
