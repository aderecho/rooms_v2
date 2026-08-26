<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
  rooms: { type: Array, default: () => [] },
  selectedRoomIds: { type: Array, default: () => [] },
  weekStart: { type: String, required: true },
  weekLabel: { type: String, required: true },
  pages: { type: Array, default: () => [] },
});

const filters = reactive({
  week_start: props.weekStart,
  room_ids: Array.from({ length: 4 }, (_, index) => props.selectedRoomIds[index] ?? ''),
});

const applyFilters = () => {
  router.get('/Reports/Schedule', {
    week_start: filters.week_start,
    room_ids: filters.room_ids.filter(Boolean),
  }, {
    preserveScroll: true,
    replace: true,
  });
};

const printReport = () => window.print();
</script>

<template>
  <AppLayout>
    <div class="report-screen">
      <header class="app-page-header report-controls">
        <div>
          <Breadcrumbs trail="UPCEBU > REPORTS > SCHEDULE" />
          <h1 class="app-page-title mt-2">Schedule Report</h1>
          <p class="mt-1 text-sm font-medium text-slate-500">{{ weekLabel }}</p>
        </div>
        <button type="button" class="app-button-primary" @click="printReport">Print Report</button>
      </header>

      <form class="report-filter report-controls" @submit.prevent="applyFilters">
        <label>
          <span>Week</span>
          <input v-model="filters.week_start" type="date" class="app-field" />
        </label>
        <label v-for="index in 4" :key="index">
          <span>Room {{ index }}</span>
          <select v-model="filters.room_ids[index - 1]" class="app-field">
            <option value="">Select room</option>
            <option v-for="room in rooms" :key="room.id" :value="room.id">
              {{ room.name }}{{ room.code ? ` (${room.code})` : '' }}
            </option>
          </select>
        </label>
        <button type="submit" class="app-button-secondary">Generate</button>
      </form>

      <div v-if="selectedRoomIds.length" class="report-pages" aria-label="Rooms usage weekly schedule report">
        <section v-for="(reportPage, pageIndex) in pages" :key="pageIndex" class="report-page">
          <h2 v-if="pageIndex === 0" class="report-title">ROOMS USAGE</h2>

          <div v-for="day in reportPage.days" :key="day.name" class="day-section">
            <h3>{{ day.name }}</h3>
            <table class="rooms-usage-table">
              <thead>
                <tr>
                  <th>TIME</th>
                  <th v-for="room in day.rooms" :key="room.id">{{ room.name }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in day.rows" :key="row.time">
                  <td>{{ row.time }}</td>
                  <td
                    v-for="(cell, cellIndex) in row.cells"
                    :key="cellIndex"
                    :class="{ 'schedule-cell': cell.occupied }"
                  >
                    {{ cell.text }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <div v-else class="report-empty report-controls">
        Add rooms first, then return here to generate the weekly schedule report.
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.report-filter {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr)) auto;
  gap: .8rem;
  align-items: end;
  margin-bottom: 1.5rem;
  padding: 1rem;
  border: 1px solid #dfe4dc;
  border-radius: .9rem;
  background: #fff;
}

.report-filter label { display: grid; gap: .35rem; }
.report-filter label span { color: #475569; font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.report-filter .app-field { min-width: 0; width: 100%; }
.report-pages { display: grid; gap: 1.5rem; overflow-x: auto; padding-bottom: .25rem; }

.report-page {
  box-sizing: border-box;
  width: 11in;
  min-height: 8.5in;
  margin: 0 auto;
  padding: 20pt 30pt 24pt 24pt;
  color: #262626;
  background: #fff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, .12);
  font-family: Arial, Helvetica, sans-serif;
}

.report-title {
  margin: 0 0 43pt;
  color: #801515;
  font-size: 15px;
  font-weight: 700;
  text-align: center;
}

.day-section + .day-section { margin-top: 9pt; }
.day-section h3 { margin: 0 0 7pt; color: #801515; font-size: 8pt; font-weight: 700; }

.rooms-usage-table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse !important;
  border: 1px solid #111;
  border-radius: 0 !important;
  color: #262626;
  font-size: 7pt;
  line-height: 1.16;
}

.rooms-usage-table th,
.rooms-usage-table td {
  height: 22.5pt;
  padding: 3pt 5pt !important;
  border: 1px solid #111 !important;
  background: #fff !important;
  color: #262626 !important;
  text-align: left;
  vertical-align: middle;
}

.rooms-usage-table th { font-size: 6.5pt !important; font-weight: 700; letter-spacing: 0 !important; }
.rooms-usage-table th:first-child,
.rooms-usage-table td:first-child { width: 20%; }
.rooms-usage-table .schedule-cell { font-weight: 700; }
.report-empty { border: 1px dashed #cbd5e1; border-radius: .8rem; padding: 2rem; text-align: center; color: #64748b; }

@media (max-width: 1280px) {
  .report-filter { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 720px) {
  .report-filter { grid-template-columns: 1fr; }
}

@media print {
  @page { size: letter landscape; margin: 0; }
  :global(body) { background: #fff !important; }
  :global(.admin-topbar),
  :global(.app-frame > aside),
  :global(.app-frame > button),
  .report-controls { display: none !important; }
  :global(.app-frame),
  :global(.app-main),
  :global(.app-content-panel) {
    display: block !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: visible !important;
    border: 0 !important;
    border-radius: 0 !important;
    background: #fff !important;
    box-shadow: none !important;
  }
  .report-pages { display: block; overflow: visible; padding: 0; }
  .report-page {
    width: 11in;
    height: 8.5in;
    min-height: 8.5in;
    margin: 0;
    padding: 20pt 30pt 24pt 24pt;
    break-after: page;
    box-shadow: none;
  }
  .report-page:last-child { break-after: auto; }
}
</style>
