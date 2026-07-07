<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import { computed } from 'vue';

const props = defineProps({
  summary: {
    type: Array,
    default: () => [],
  },
  roomStatus: {
    type: Array,
    default: () => [],
  },
  userDistribution: {
    type: Array,
    default: () => [],
  },
  scheduleStatus: {
    type: Array,
    default: () => [],
  },
  roomsByBuilding: {
    type: Array,
    default: () => [],
  },
  topRooms: {
    type: Array,
    default: () => [],
  },
  scheduleWindow: {
    type: Object,
    default: () => ({ today: 0, upcoming: 0, approvedUpcoming: 0 }),
  },
  updatedAt: {
    type: String,
    default: '',
  },
});

const accentClasses = {
  green: 'from-emerald-50 to-white text-emerald-700 ring-emerald-100',
  teal: 'from-teal-50 to-white text-teal-700 ring-teal-100',
  amber: 'from-amber-50 to-white text-amber-700 ring-amber-100',
  indigo: 'from-indigo-50 to-white text-indigo-700 ring-indigo-100',
  slate: 'from-slate-50 to-white text-slate-700 ring-slate-100',
};

const scheduleCards = computed(() => [
  { label: 'Today', value: props.scheduleWindow.today ?? 0 },
  { label: 'Upcoming', value: props.scheduleWindow.upcoming ?? 0 },
  { label: 'Approved Upcoming', value: props.scheduleWindow.approvedUpcoming ?? 0 },
]);

const maxOf = (items) => Math.max(1, ...items.map((item) => Number(item.value) || 0));
const totalOf = (items) => items.reduce((sum, item) => sum + (Number(item.value) || 0), 0);

const widthFor = (item, items) => `${Math.max(6, Math.round(((Number(item.value) || 0) / maxOf(items)) * 100))}%`;
const percentOfTotal = (item, items) => {
  const total = totalOf(items);
  return total ? `${Math.round(((Number(item.value) || 0) / total) * 100)}%` : '0%';
};

const hasRows = (items) => items.length > 0;
</script>

<template>
  <AppLayout>
    <div class="space-y-5">
      <header class="app-page-header">
        <Breadcrumbs trail="UPCEBU > ANALYTICS" />
        <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">
          Updated {{ updatedAt }}
        </div>
      </header>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article
          v-for="item in summary"
          :key="item.label"
          class="rounded-2xl border border-slate-200 bg-gradient-to-br p-5 shadow-sm ring-1"
          :class="accentClasses[item.accent] ?? accentClasses.slate"
        >
          <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">{{ item.label }}</p>
          <div class="mt-3 flex items-end justify-between gap-3">
            <strong class="text-3xl font-black text-slate-950">{{ item.value }}</strong>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-bold shadow-sm">{{ item.note }}</span>
          </div>
        </article>
      </section>

      <section class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
        <div class="analytics-card">
          <div class="analytics-card-header">
            <div>
              <p class="analytics-kicker">Room utilization</p>
              <h2>Room Status</h2>
            </div>
            <span>{{ totalOf(roomStatus) }} total</span>
          </div>

          <div v-if="hasRows(roomStatus)" class="space-y-4">
            <div v-for="item in roomStatus" :key="item.label" class="analytics-bar-row">
              <div class="analytics-row-label">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }} rooms</strong>
              </div>
              <div class="analytics-track">
                <div class="analytics-fill analytics-fill-primary" :style="{ width: widthFor(item, roomStatus) }"></div>
              </div>
              <p>{{ percentOfTotal(item, roomStatus) }} of room inventory</p>
            </div>
          </div>
          <p v-else class="analytics-empty">No room records available.</p>
        </div>

        <div class="analytics-card">
          <div class="analytics-card-header">
            <div>
              <p class="analytics-kicker">Schedule activity</p>
              <h2>Booking Window</h2>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1">
            <div v-for="item in scheduleCards" :key="item.label" class="schedule-tile">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>

          <div class="mt-5 space-y-3">
            <div v-for="item in scheduleStatus" :key="item.label" class="analytics-mini-row">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-2">
        <div class="analytics-card">
          <div class="analytics-card-header">
            <div>
              <p class="analytics-kicker">Access profile</p>
              <h2>User Distribution</h2>
            </div>
            <span>{{ totalOf(userDistribution) }} users</span>
          </div>

          <div v-if="hasRows(userDistribution)" class="space-y-4">
            <div v-for="item in userDistribution" :key="item.label" class="analytics-bar-row">
              <div class="analytics-row-label">
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
              </div>
              <div class="analytics-track">
                <div class="analytics-fill analytics-fill-indigo" :style="{ width: widthFor(item, userDistribution) }"></div>
              </div>
            </div>
          </div>
          <p v-else class="analytics-empty">No user records available.</p>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-2">
        <div class="analytics-card">
          <div class="analytics-card-header">
            <div>
              <p class="analytics-kicker">Facilities</p>
              <h2>Rooms by Building</h2>
            </div>
          </div>

          <div v-if="hasRows(roomsByBuilding)" class="analytics-list">
            <div v-for="item in roomsByBuilding" :key="item.label" class="analytics-list-row">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }} rooms</strong>
            </div>
          </div>
          <p v-else class="analytics-empty">No building room data available.</p>
        </div>

        <div class="analytics-card">
          <div class="analytics-card-header">
            <div>
              <p class="analytics-kicker">Demand</p>
              <h2>Most Scheduled Rooms</h2>
            </div>
          </div>

          <div v-if="hasRows(topRooms)" class="analytics-list">
            <div v-for="item in topRooms" :key="item.label" class="analytics-list-row">
              <span>
                {{ item.label }}
                <small>{{ item.meta }}</small>
              </span>
              <strong>{{ item.value }} bookings</strong>
            </div>
          </div>
          <p v-else class="analytics-empty">No room booking data available.</p>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<style scoped>
.analytics-card {
  border: 1px solid #dbe7e1;
  border-radius: 18px;
  background: #ffffff;
  padding: 1.25rem;
  box-shadow: 0 18px 42px rgba(15, 23, 42, 0.06);
}

.analytics-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.analytics-card-header h2 {
  margin: 0.15rem 0 0;
  color: #0f172a;
  font-size: 1.05rem;
  font-weight: 800;
}

.analytics-card-header > span {
  border-radius: 999px;
  background: #f1f5f9;
  padding: 0.35rem 0.75rem;
  color: #475569;
  font-size: 0.78rem;
  font-weight: 800;
}

.analytics-kicker {
  color: #047857;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.analytics-row-label {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  color: #0f172a;
  font-size: 0.9rem;
}

.analytics-row-label span {
  font-weight: 750;
}

.analytics-row-label strong {
  color: #475569;
  font-weight: 800;
}

.analytics-track {
  margin-top: 0.55rem;
  height: 0.75rem;
  overflow: hidden;
  border-radius: 999px;
  background: #edf2f7;
}

.analytics-fill {
  height: 100%;
  border-radius: inherit;
}

.analytics-fill-primary {
  background: linear-gradient(90deg, #005740, #18a070);
}

.analytics-fill-indigo {
  background: linear-gradient(90deg, #4338ca, #818cf8);
}

.analytics-bar-row p {
  margin-top: 0.4rem;
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 650;
}

.schedule-tile {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 1px solid #dbe7e1;
  border-radius: 14px;
  background: linear-gradient(135deg, #f8fafc, #ffffff);
  padding: 0.9rem 1rem;
}

.schedule-tile span,
.analytics-mini-row span {
  color: #475569;
  font-size: 0.82rem;
  font-weight: 750;
}

.schedule-tile strong {
  color: #005740;
  font-size: 1.35rem;
  font-weight: 900;
}

.analytics-mini-row,
.analytics-list-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid #e5e7eb;
  padding: 0.85rem 0;
}

.analytics-mini-row:last-child,
.analytics-list-row:last-child {
  border-bottom: 0;
}

.analytics-mini-row strong,
.analytics-list-row strong {
  color: #005740;
  font-weight: 900;
  white-space: nowrap;
}

.analytics-list-row span {
  display: grid;
  gap: 0.2rem;
  color: #0f172a;
  font-size: 0.9rem;
  font-weight: 800;
}

.analytics-list-row small {
  color: #64748b;
  font-size: 0.76rem;
  font-weight: 650;
}

.analytics-empty {
  border: 1px dashed #cbd5e1;
  border-radius: 14px;
  background: #f8fafc;
  padding: 1rem;
  color: #64748b;
  font-weight: 700;
}
</style>
