<template>
  <div class="relative" ref="rootRef">
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">{{ label }}</label>

    <!-- Pills + input (Gmail-style) -->
    <div
      class="equipment-input-wrap"
      :class="{
        'equipment-input-wrap--disabled': disabled,
        'equipment-input-wrap--focused': isFocused && !disabled,
      }"
      @click="focusInput"
    >
      <span
        v-for="(item, index) in modelValue"
        :key="`${item}-${index}`"
        class="equipment-pill"
      >
        <span
          class="equipment-pill__icon-wrap"
          :class="getEquipmentIconStyle(item)"
          aria-hidden="true"
        >
          <FontAwesomeIcon :icon="getEquipmentIcon(item)" class="w-3 h-3" />
        </span>
        {{ item }}
        <button
          v-if="!disabled"
          type="button"
          class="equipment-pill__remove"
          @click.stop="removePill(index)"
          aria-label="Remove equipment"
        >
          ×
        </button>
      </span>

      <input
        v-if="!disabled"
        ref="inputRef"
        v-model="query"
        type="text"
        class="equipment-input"
        :placeholder="modelValue.length ? '' : placeholder"
        autocomplete="off"
        @input="onInput"
        @keydown="onKeydown"
        @focus="onFocus"
        @blur="onBlur"
      />
    </div>

    <p v-if="!disabled" class="mt-1 text-xs text-gray-500">
      Type to search equipment. Select from suggestions only.
    </p>

    <!-- Suggestion dropdown -->
    <ul
      v-if="showDropdown && query.trim() && !disabled"
      class="equipment-dropdown"
      role="listbox"
    >
      <li
        v-if="loading"
        class="equipment-dropdown__empty"
        role="status"
      >
        Searching…
      </li>
      <li
        v-else-if="!suggestions.length"
        class="equipment-dropdown__empty"
        role="status"
      >
        No equipment found
      </li>
      <template v-else>
        <li
          v-for="(item, index) in suggestions"
          :key="item.id"
          role="option"
          :aria-selected="index === highlightedIndex"
          class="equipment-dropdown__item"
          :class="{ 'equipment-dropdown__item--active': index === highlightedIndex }"
          @mousedown.prevent="selectSuggestion(item)"
          @mouseenter="highlightedIndex = index"
        >
          <span
            class="equipment-dropdown__avatar"
            :class="getEquipmentIconStyle(item.name)"
            aria-hidden="true"
          >
            <FontAwesomeIcon :icon="getEquipmentIcon(item.name)" class="w-4 h-4" />
          </span>
          <span class="equipment-dropdown__text">
            <span class="equipment-dropdown__name" v-html="highlightMatch(item.name, query)" />
            <span class="equipment-dropdown__meta">
              {{ formatEquipmentAvailability(item.inventory_count) }}
            </span>
          </span>
        </li>
      </template>
    </ul>

    <!-- Validation popup: dismiss only via OK (keeps Create/Edit Room modal open behind it) -->
    <Teleport to="body">
      <div
        v-if="showValidationModal"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 px-4"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="equipment-validation-title"
        @keydown.escape.stop.prevent
      >
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6" @click.stop @mousedown.stop>
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-lg font-bold">
              !
            </div>
            <div>
              <h4 id="equipment-validation-title" class="text-lg font-semibold text-gray-900">Invalid equipment</h4>
              <p class="mt-2 text-sm text-gray-600">
                <strong>"{{ invalidEntry }}"</strong> is not a registered equipment type.
                Please choose an item from the suggestions dropdown.
              </p>
              <p v-if="invalidEntry" class="mt-2 text-xs text-gray-500">
                Only registered equipment (e.g. TV, Chairs, Aircon) can be added to a room.
              </p>
            </div>
          </div>
          <div class="mt-6 flex justify-end">
            <button
              ref="okButtonRef"
              type="button"
              class="btn-ok"
              @mousedown.prevent
              @click.stop="closeValidationModal"
            >
              OK
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { getEquipmentIcon, getEquipmentIconStyle } from '@/utils/equipmentIcons'
import { formatEquipmentAvailability } from '@/utils/equipmentAvailability.js'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Add equipment…',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const API_BASE = '/api/equipment'

const rootRef = ref(null)
const inputRef = ref(null)
const query = ref('')
const suggestions = ref([])
const showDropdown = ref(false)
const highlightedIndex = ref(0)
const isFocused = ref(false)
const showValidationModal = ref(false)
const invalidEntry = ref('')
const loading = ref(false)
const isValidating = ref(false)
const validationLocked = ref(false)
const okButtonRef = ref(null)

let debounceTimer = null
let blurTimer = null

const clearPendingTimers = () => {
  clearTimeout(debounceTimer)
  clearTimeout(blurTimer)
  debounceTimer = null
  blurTimer = null
}

const openValidationModal = (entry) => {
  clearPendingTimers()
  validationLocked.value = true
  invalidEntry.value = entry
  showValidationModal.value = true
  nextTick(() => {
    okButtonRef.value?.focus()
  })
}

const closeValidationModal = () => {
  showValidationModal.value = false
  validationLocked.value = false
  query.value = ''
  invalidEntry.value = ''
  showDropdown.value = false
  clearPendingTimers()
  nextTick(() => {
    inputRef.value?.focus()
  })
}

const focusInput = () => {
  if (!props.disabled && !validationLocked.value) {
    inputRef.value?.focus()
  }
}

const emitValue = (list) => {
  emit('update:modelValue', list)
}

const removePill = (index) => {
  const next = [...props.modelValue]
  next.splice(index, 1)
  emitValue(next)
  fetchSuggestions()
}

const isDuplicate = (name) => {
  const lower = name.toLowerCase()
  return props.modelValue.some((item) => item.toLowerCase() === lower)
}

const fetchSuggestions = async () => {
  const q = query.value.trim()
  if (!q) {
    suggestions.value = []
    showDropdown.value = false
    return
  }

  loading.value = true
  showDropdown.value = true
  try {
    const { data } = await axios.get(`${API_BASE}/suggestions`, {
      params: {
        q,
        exclude: props.modelValue.join(','),
      },
    })

    if (data.success) {
      suggestions.value = data.data
      highlightedIndex.value = 0
    }
  } catch {
    suggestions.value = []
  } finally {
    loading.value = false
  }
}

const onInput = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchSuggestions, 200)
}

const onFocus = () => {
  isFocused.value = true
  if (query.value.trim()) {
    fetchSuggestions()
  }
}

const onBlur = () => {
  isFocused.value = false
  // Do not validate on blur — it races with the alert and can make it disappear.
  // Validation runs only on Enter, comma, or semicolon.
  if (validationLocked.value || showValidationModal.value || isValidating.value) {
    return
  }
  blurTimer = setTimeout(() => {
    showDropdown.value = false
  }, 150)
}

const selectSuggestion = (item) => {
  addEquipment(item.name)
  query.value = ''
  suggestions.value = []
  showDropdown.value = false
  highlightedIndex.value = 0
  inputRef.value?.focus()
}

const validateAndAdd = async (name) => {
  const trimmed = name.trim()
  if (!trimmed) return false

  if (isDuplicate(trimmed)) {
    query.value = ''
    return false
  }

  if (validationLocked.value && invalidEntry.value.toLowerCase() === trimmed.toLowerCase()) {
    return false
  }

  isValidating.value = true
  try {
    const { data } = await axios.post(`${API_BASE}/validate-names`, {
      names: [trimmed],
    })

    if (data.success && data.valid?.length) {
      addEquipment(data.valid[0])
      return true
    }

    openValidationModal(trimmed)
    return false
  } catch {
    openValidationModal(trimmed)
    return false
  } finally {
    isValidating.value = false
  }
}

const addEquipment = (name) => {
  if (isDuplicate(name)) return
  emitValue([...props.modelValue, name])
}

const tryAddFromInput = async () => {
  if (validationLocked.value || showValidationModal.value || isValidating.value) {
    return
  }

  const trimmed = query.value.trim()
  if (!trimmed) return

  const exactMatch = suggestions.value.find(
    (item) => item.name.toLowerCase() === trimmed.toLowerCase()
  )

  if (exactMatch) {
    selectSuggestion(exactMatch)
    return
  }

  const added = await validateAndAdd(trimmed)
  if (added) {
    query.value = ''
  }
}

const onKeydown = async (event) => {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (!showDropdown.value || !suggestions.value.length) return
    highlightedIndex.value = Math.min(
      highlightedIndex.value + 1,
      suggestions.value.length - 1
    )
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    if (!showDropdown.value || !suggestions.value.length) return
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
    return
  }

  if (event.key === 'Enter') {
    event.preventDefault()
    clearPendingTimers()
    if (validationLocked.value || showValidationModal.value) {
      return
    }
    if (showDropdown.value && suggestions.value[highlightedIndex.value]) {
      selectSuggestion(suggestions.value[highlightedIndex.value])
      return
    }
    await tryAddFromInput()
    return
  }

  if (event.key === 'Escape') {
    event.stopPropagation()
    if (validationLocked.value || showValidationModal.value) {
      return
    }
    showDropdown.value = false
    query.value = ''
    return
  }

  if (event.key === 'Backspace' && query.value === '' && props.modelValue.length) {
    removePill(props.modelValue.length - 1)
  }

  if (event.key === ',' || event.key === ';') {
    event.preventDefault()
    clearPendingTimers()
    await tryAddFromInput()
  }
}

const escapeHtml = (text) => {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

const highlightMatch = (text, term) => {
  const safeText = escapeHtml(text)
  const trimmed = term.trim()
  if (!trimmed) return safeText

  const regex = new RegExp(`(${trimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi')
  return safeText.replace(regex, '<strong>$1</strong>')
}

const onDocumentClick = (event) => {
  if (validationLocked.value || showValidationModal.value) {
    return
  }
  if (!rootRef.value?.contains(event.target)) {
    showDropdown.value = false
  }
}

watch(
  () => props.modelValue,
  () => {
    if (validationLocked.value || showValidationModal.value) {
      return
    }
    if (query.value.trim()) {
      fetchSuggestions()
    }
  }
)

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  clearPendingTimers()
})
</script>

<style scoped>
.equipment-input-wrap {
  @apply flex flex-wrap items-center gap-1.5 min-h-[42px] w-full border border-gray-300 rounded-md px-2 py-1.5 bg-white cursor-text transition-colors;
}

.equipment-input-wrap--focused {
  @apply border-blue-500 ring-2 ring-blue-500/20;
}

.equipment-input-wrap--disabled {
  @apply bg-gray-50 cursor-default;
}

.equipment-pill {
  @apply inline-flex items-center gap-1.5 bg-gray-100 text-gray-800 text-sm px-2 py-0.5 rounded-full border border-gray-200;
}

.equipment-pill__icon-wrap {
  @apply flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center;
}

.equipment-pill__remove {
  @apply text-gray-500 hover:text-gray-800 leading-none text-base ml-0.5;
}

/* Inner field is borderless; only the wrapper shows the highlight */
.equipment-input {
  @apply flex-1 min-w-[120px] text-sm py-1 px-0 m-0 bg-transparent outline-none;
}

.equipment-dropdown {
  @apply absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-56 overflow-y-auto py-1;
}

.equipment-dropdown__item {
  @apply flex items-center gap-3 px-3 py-2 cursor-pointer text-sm;
}

.equipment-dropdown__item--active {
  @apply bg-gray-100;
}

.equipment-dropdown__avatar {
  @apply flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center;
}

.equipment-dropdown__text {
  @apply flex flex-col min-w-0;
}

.equipment-dropdown__name {
  @apply text-gray-900 truncate;
}

.equipment-dropdown__name :deep(strong) {
  @apply font-bold text-gray-900;
}

.equipment-dropdown__meta {
  @apply text-xs text-gray-500;
}

.equipment-dropdown__empty {
  @apply px-3 py-2.5 text-sm text-gray-500 text-center;
}

.btn-ok {
  @apply bg-[#7A0C23] text-white px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90;
}
</style>

<!-- Unscoped: beat @tailwindcss/forms default input border/ring on focus -->
<style>
.equipment-input-wrap .equipment-input,
.equipment-input-wrap .equipment-input:focus,
.equipment-input-wrap .equipment-input:focus-visible {
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  background-color: transparent !important;
  --tw-ring-shadow: 0 0 #0000 !important;
  --tw-ring-offset-shadow: 0 0 #0000 !important;
  --tw-ring-offset-width: 0 !important;
  --tw-ring-width: 0 !important;
}
</style>
