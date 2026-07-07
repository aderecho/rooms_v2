<script setup>
import { computed } from 'vue';

const props = defineProps({
  trail: {
    type: [String, Array],
    default: '',
  },
});

const normalizeLabel = (label) => {
  const value = String(label || '').trim();

  if (!value || value.toUpperCase() === 'UPCEBU') {
    return 'Home';
  }

  return value
    .toLowerCase()
    .split(' ')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

const items = computed(() => {
  const rawItems = Array.isArray(props.trail)
    ? props.trail
    : String(props.trail || '').split(/>|\/|,/);

  const normalized = rawItems
    .map(normalizeLabel)
    .filter(Boolean);

  return normalized.length ? normalized : ['Home'];
});

const iconFor = (label, index) => {
  const key = label.toLowerCase();

  if (index === 0 || key === 'home') return 'home';
  if (key.includes('building') || key.includes('room')) return 'grid';
  if (key.includes('calendar') || key.includes('schedule')) return 'calendar';
  if (key.includes('analytic')) return 'chart';
  if (key.includes('account') || key.includes('team') || key.includes('user')) return 'user';
  if (key.includes('saml') || key.includes('setting') || key.includes('term')) return 'settings';

  return 'dot';
};
</script>

<template>
  <nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
      <li
        v-for="(item, index) in items"
        :key="`${item}-${index}`"
        :class="{ current: index === items.length - 1 }"
      >
        <span class="crumb-icon" aria-hidden="true">
          <svg v-if="iconFor(item, index) === 'home'" viewBox="0 0 24 24" fill="none">
            <path d="M4 11.5 12 5l8 6.5V20a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1v-8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
          </svg>
          <svg v-else-if="iconFor(item, index) === 'grid'" viewBox="0 0 24 24" fill="none">
            <path d="M5 5h5v5H5V5Zm9 0h5v5h-5V5ZM5 14h5v5H5v-5Zm9 0h5v5h-5v-5Z" stroke="currentColor" stroke-width="1.8"/>
          </svg>
          <svg v-else-if="iconFor(item, index) === 'calendar'" viewBox="0 0 24 24" fill="none">
            <path d="M7 4v3M17 4v3M5 9h14M6 6h12a1 1 0 0 1 1 1v12H5V7a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <svg v-else-if="iconFor(item, index) === 'chart'" viewBox="0 0 24 24" fill="none">
            <path d="M5 19V9m7 10V5m7 14v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <svg v-else-if="iconFor(item, index) === 'user'" viewBox="0 0 24 24" fill="none">
            <path d="M16 19a4 4 0 0 0-8 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <svg v-else-if="iconFor(item, index) === 'settings'" viewBox="0 0 24 24" fill="none">
            <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.8"/>
            <path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1.5-1.5M5.5 18.5 7 17M17 17l1.5 1.5M5.5 5.5 7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none">
            <path d="M7 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </span>
        <span>{{ item }}</span>

        <svg v-if="index < items.length - 1" class="crumb-separator" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.breadcrumbs {
  width: fit-content;
  max-width: 100%;
  overflow-x: auto;
  border: 1px solid #dbe3ea;
  border-radius: 0.75rem;
  background: #f1f5f9;
  padding: 0.55rem 0.75rem;
}

.breadcrumbs ol {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  min-width: max-content;
}

.breadcrumbs li {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1;
}

.breadcrumbs li.current {
  color: #65a30d;
  font-style: italic;
}

.crumb-icon {
  display: inline-grid;
  height: 1rem;
  width: 1rem;
  place-items: center;
  color: currentColor;
}

.crumb-icon svg,
.crumb-separator {
  height: 1rem;
  width: 1rem;
}

.crumb-separator {
  color: #94a3b8;
}
</style>
