<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import Navbar from '@/Components/Navbar.vue';
import Sidebar from '@/Components/Sidebar.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';

defineProps({
    roomCount: { type: Number, default: 0 },
    maxRows: { type: Number, default: 500 },
    supportedFormats: { type: Array, default: () => ['CSV', 'XLSX', 'XLS'] },
});

const sidebarOpen = ref(true);
const fileInput = ref(null);
const selectedFile = ref(null);
const isDragging = ref(false);
const isPreviewing = ref(false);
const isImporting = ref(false);
const preview = ref(null);
const errorMessage = ref('');
const successMessage = ref('');

const acceptedExtensions = ['csv', 'xlsx', 'xls'];

const fileSizeLabel = computed(() => {
    if (!selectedFile.value) return '';
    const size = selectedFile.value.size;
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
});

const canImport = computed(() => (
    selectedFile.value
    && preview.value
    && preview.value.summary.invalid_rows === 0
    && !isPreviewing.value
    && !isImporting.value
));

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const resetResults = () => {
    preview.value = null;
    errorMessage.value = '';
    successMessage.value = '';
};

const selectFile = (file) => {
    resetResults();

    if (!file) {
        selectedFile.value = null;
        return;
    }

    const extension = file.name.split('.').pop()?.toLowerCase();
    if (!acceptedExtensions.includes(extension)) {
        selectedFile.value = null;
        errorMessage.value = 'Choose a CSV, XLSX, or XLS file.';
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        selectedFile.value = null;
        errorMessage.value = 'The file must be 10 MB or smaller.';
        return;
    }

    selectedFile.value = file;
};

const handleInput = (event) => selectFile(event.target.files?.[0]);

const handleDrop = (event) => {
    isDragging.value = false;
    selectFile(event.dataTransfer.files?.[0]);
};

const removeFile = () => {
    selectedFile.value = null;
    resetResults();
    if (fileInput.value) fileInput.value.value = '';
};

const upload = async (url) => {
    const formData = new FormData();
    formData.append('file', selectedFile.value);

    return axios.post(url, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
};

const previewFile = async () => {
    if (!selectedFile.value) return;

    isPreviewing.value = true;
    resetResults();

    try {
        const response = await upload('/Schedule/Import/Preview');
        preview.value = response.data;
    } catch (error) {
        preview.value = error.response?.data?.rows
            ? error.response.data
            : null;
        errorMessage.value = error.response?.data?.message || 'The file could not be validated.';
    } finally {
        isPreviewing.value = false;
    }
};

const importFile = async () => {
    if (!canImport.value) return;

    isImporting.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await upload('/Schedule/Import');
        successMessage.value = response.data.message;
        preview.value = {
            ...preview.value,
            imported: true,
            imported_rows: response.data.imported_rows,
            schedule_count: response.data.schedule_count,
        };
    } catch (error) {
        if (error.response?.data?.rows) preview.value = error.response.data;
        errorMessage.value = error.response?.data?.message || 'The schedules could not be imported.';
    } finally {
        isImporting.value = false;
    }
};

const statusClass = (status) => status === 'valid' ? 'is-valid' : 'is-invalid';

const formatDate = (value) => {
    if (!value) return '';
    return new Intl.DateTimeFormat('en-PH', {
        month: 'long', day: 'numeric', year: 'numeric', timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
};

const formatTime = (value) => {
    if (!value) return '';
    const [hours, minutes] = value.split(':').map(Number);
    return new Intl.DateTimeFormat('en-PH', {
        hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'UTC',
    }).format(new Date(Date.UTC(2000, 0, 1, hours, minutes)));
};
</script>

<template>
    <div class="import-page min-h-screen">
        <Navbar @toggleSidebar="toggleSidebar" />

        <div class="app-frame">
            <Sidebar
                :sidebarOpen="sidebarOpen"
                :class="[
                    'fixed left-0 top-[4.5rem] z-20 h-[calc(100vh-4.5rem)] transition-all duration-300 lg:fixed lg:top-0 lg:h-screen',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:hidden'
                ]"
            />
            <button
                v-if="sidebarOpen"
                type="button"
                class="fixed inset-0 z-[19] bg-slate-950/35 lg:hidden"
                aria-label="Close sidebar"
                @click="sidebarOpen = false"
            ></button>

            <main class="app-main import-main transition-all duration-300 lg:ml-[250px]">
                <header class="content-header">
                    <div>
                        <Breadcrumbs trail="UPCEBU > CALENDAR > IMPORT SCHEDULES" />
                        <h1>Import Schedules</h1>
                        <p>Upload schedule records from a prepared CSV or Excel workbook.</p>
                    </div>
                        <button type="button" class="button button-secondary" @click="router.visit('/Schedule', { viewTransition: true })">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/><path d="M9 12h10"/></svg>
                        Back to Calendar
                    </button>
                </header>

                <nav class="workflow-steps" aria-label="Schedule import steps">
                    <div class="workflow-step is-active">
                        <span class="step-icon">1</span>
                        <span><strong>Download template</strong><small>CSV or Excel format</small></span>
                    </div>
                    <div class="workflow-step" :class="{ 'is-active': selectedFile }">
                        <span class="step-icon">2</span>
                        <span><strong>Upload and review</strong><small>No records saved yet</small></span>
                    </div>
                    <div class="workflow-step" :class="{ 'is-active': preview && preview.summary.invalid_rows === 0 }">
                        <span class="step-icon">3</span>
                        <span><strong>Confirm import</strong><small>Save all valid schedules</small></span>
                    </div>
                </nav>

                <section class="import-workspace">
                    <aside class="import-sidebar">
                        <article class="admin-panel template-panel">
                            <div class="panel-heading">
                                <span class="section-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4M9 12h6m-6 4h6"/></svg>
                                </span>
                                <div><h2>Download a template</h2><p>Start with a blank import sheet and separate reference examples.</p></div>
                            </div>
                            <div class="template-list">
                                <a href="/Schedule/Import/Template.xlsx" class="template-button is-primary">
                                    <span class="file-badge">XLSX</span>
                                    <span><strong>Excel template</strong><small>Blank import sheet + reference examples</small></span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
                                </a>
                                <a href="/Schedule/Import/Template.csv" class="template-button">
                                    <span class="file-badge is-muted">CSV</span>
                                    <span><strong>CSV template</strong><small>Headers only, ready for real schedules</small></span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
                                </a>
                            </div>
                        </article>

                        <article class="admin-panel guide-panel">
                            <div class="panel-heading compact"><div><h2>Before you upload</h2><p>Follow these formatting rules.</p></div></div>
                            <ul class="guide-list">
                                <li><span class="guide-check"><svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7"/></svg></span><span>Keep all template headers unchanged.</span></li>
                                <li><span class="guide-check"><svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7"/></svg></span><span>Enter real schedules on the blank import sheet; do not upload reference examples unchanged.</span></li>
                                <li><span class="guide-check"><svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7"/></svg></span><span>Use either room ID or exact room name.</span></li>
                                <li><span class="guide-check"><svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7"/></svg></span><span>Single dates use YYYY-MM-DD and HH:MM.</span></li>
                                <li><span class="guide-check"><svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7"/></svg></span><span>Recurring dates support T-TH academic-year format.</span></li>
                            </ul>
                            <div class="recurring-example">
                                <strong>Recurring example</strong>
                                <code>T-TH from June to May 10:am-11:am 2026-2027</code>
                                <dl>
                                    <div><dt>Days</dt><dd>Tuesday &amp; Thursday</dd></div>
                                    <div><dt>Date range</dt><dd>June 1, 2026 – May 31, 2027</dd></div>
                                    <div><dt>Time</dt><dd>10:00 AM – 11:00 AM</dd></div>
                                </dl>
                            </div>
                            <div class="capacity-note"><strong>{{ maxRows }}</strong><span>maximum rows</span><i></i><strong>{{ roomCount }}</strong><span>registered rooms</span></div>
                        </article>
                    </aside>

                    <div class="import-content">
                        <article class="admin-panel upload-panel">
                            <div class="panel-heading">
                                <span class="section-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 16V4m0 0L7 9m5-5 5 5M5 15v4h14v-4"/></svg>
                                </span>
                                <div><h2>Upload completed file</h2><p>{{ supportedFormats.join(', ') }} · Maximum file size 10 MB</p></div>
                            </div>

                            <input ref="fileInput" class="sr-only" type="file" accept=".csv,.xlsx,.xls" @change="handleInput" />
                            <button
                                type="button"
                                class="upload-zone"
                                :class="{ dragging: isDragging, selected: selectedFile }"
                                @click="fileInput?.click()"
                                @dragenter.prevent="isDragging = true"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleDrop"
                            >
                                <span class="upload-icon">
                                    <svg viewBox="0 0 24 24"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v5h14v-5"/></svg>
                                </span>
                                <template v-if="selectedFile">
                                    <strong>{{ selectedFile.name }}</strong>
                                    <span>{{ fileSizeLabel }} · Ready to validate</span>
                                </template>
                                <template v-else>
                                    <strong>Drop your spreadsheet here, or browse</strong>
                                    <span>CSV, XLSX, or XLS files up to 10 MB</span>
                                    <span class="browse-button">Browse file</span>
                                </template>
                            </button>

                            <div v-if="selectedFile" class="upload-actions">
                                <button type="button" class="button button-danger" @click="removeFile">Remove file</button>
                                <button
                                    type="button"
                                    class="button button-primary"
                                    :disabled="isPreviewing"
                                    @click="previewFile"
                                >
                                    <svg v-if="!isPreviewing" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12s3-6 9-6 9 6 9 6-3 6-9 6-9-6-9-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                                    {{ isPreviewing ? 'Validating file...' : 'Validate and preview' }}
                                </button>
                            </div>
                        </article>

                        <div v-if="errorMessage" class="notice is-error" role="alert">
                            <span class="notice-icon">!</span><div><strong>Import needs attention</strong><p>{{ errorMessage }}</p></div>
                        </div>

                        <div v-if="successMessage" class="notice is-success" role="status">
                            <span class="notice-icon"><svg viewBox="0 0 24 24"><path d="m7 12 3 3 7-7"/></svg></span><div><strong>Import complete</strong><p>{{ successMessage }}</p></div>
                        </div>

                        <article v-if="preview" class="admin-panel review-panel">
                            <div class="review-heading">
                                <div class="panel-heading compact">
                                    <span class="section-icon"><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5zM8 9h8m-8 4h8m-8 4h5"/></svg></span>
                                    <div><h2>Review before importing</h2><p>Check every row before saving schedules.</p></div>
                                </div>
                                <div class="summary-grid">
                                    <div class="summary-chip"><strong>{{ preview.summary.total_rows }}</strong><span>Rows</span></div>
                                    <div class="summary-chip summary-valid"><strong>{{ preview.summary.valid_rows }}</strong><span>Valid</span></div>
                                    <div class="summary-chip" :class="preview.summary.invalid_rows ? 'summary-invalid' : ''"><strong>{{ preview.summary.invalid_rows }}</strong><span>Invalid</span></div>
                                </div>
                            </div>

                            <div class="review-table-wrap" tabindex="0">
                                <table class="review-table">
                                    <thead>
                                        <tr>
                                            <th>Row</th><th>Room</th><th>Schedule</th><th>Date / pattern</th><th>Time</th><th>Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in preview.rows" :key="row.row_number">
                                            <td class="row-number">{{ row.row_number }}</td>
                                            <td><strong>{{ row.room }}</strong></td>
                                            <td>{{ row.event_title || 'Untitled' }}</td>
                                            <td class="date-cell">
                                                <template v-if="row.is_recurring">
                                                    <strong class="date-type">Recurring schedule</strong>
                                                    <span>{{ row.days.join(' & ') }}</span>
                                                    <span>{{ formatDate(row.range_start) }} – {{ formatDate(row.range_end) }}</span>
                                                    <small>{{ row.occurrences }} occurrences</small>
                                                    <code>{{ row.date }}</code>
                                                </template>
                                                <template v-else>
                                                    <strong class="date-type">One-time schedule</strong>
                                                    <span>{{ formatDate(row.date) }}</span>
                                                </template>
                                            </td>
                                            <td class="time-cell">{{ formatTime(row.start_time) }} – {{ formatTime(row.end_time) }}</td>
                                            <td>
                                                <span class="result-badge" :class="statusClass(row.status)">
                                                    {{ row.status === 'valid' ? 'Ready' : 'Fix row' }}
                                                </span>
                                                <ul v-if="row.errors.length" class="row-errors">
                                                    <li v-for="error in row.errors" :key="error">{{ error }}</li>
                                                </ul>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="review-actions">
                                <p>
                                    <template v-if="preview.summary.invalid_rows">Correct all invalid rows before importing.</template>
                                    <template v-else>{{ preview.summary.schedule_occurrences }} schedule occurrences are ready.</template>
                                </p>
                                <button
                                    type="button"
                                    class="button button-primary"
                                    :disabled="!canImport || preview.imported"
                                    @click="importFile"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg>
                                    {{ isImporting ? 'Importing schedules...' : preview.imported ? 'Import completed' : 'Import schedules' }}
                                </button>
                            </div>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<style scoped>
.import-page{--green:#005740;--green-dark:#003d2d;--green-soft:#e8f2ee;--gold:#d99b22;--cream:#f5f2ec;--ink:#21352f;--muted:#6d7a75;--line:#dce3dc;background:var(--cream);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.import-main{padding-bottom:2rem}.content-header{display:flex;align-items:flex-end;justify-content:space-between;gap:1.5rem;margin-bottom:1.25rem}.content-header h1{margin:.45rem 0 0;color:var(--green);font-size:clamp(1.9rem,3vw,2.45rem);font-weight:800;letter-spacing:-.035em}.content-header p{margin:.45rem 0 0;color:var(--muted);font-size:.875rem}.button{display:inline-flex;min-height:2.55rem;align-items:center;justify-content:center;gap:.5rem;border:1px solid var(--green);border-radius:.55rem;padding:.65rem .9rem;font-size:.8rem;font-weight:800;transition:background .18s ease,border-color .18s ease,transform .18s ease}.button svg,.template-button svg,.section-icon svg,.guide-check svg,.notice-icon svg{width:1rem;height:1rem;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.button:hover{transform:translateY(-1px)}.button:focus-visible,.template-button:focus-visible,.upload-zone:focus-visible{outline:3px solid rgba(217,155,34,.4);outline-offset:2px}.button-primary{background:var(--green);color:#fff}.button-primary:hover{background:var(--green-dark)}.button-secondary{background:#fff;color:var(--green)}.button-secondary:hover{background:var(--green-soft)}.button-danger{border-color:#dac6c2;background:#fff;color:#9d352d}.button:disabled{cursor:not-allowed;opacity:.5;transform:none}.workflow-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.45rem;margin-bottom:1.1rem;border:1px solid rgba(0,87,64,.11);border-radius:1rem;padding:.45rem;background:rgba(255,255,255,.88);box-shadow:0 10px 28px rgba(33,53,47,.07)}.workflow-step{display:flex;position:relative;align-items:center;gap:.7rem;border:1px solid transparent;border-radius:.75rem;padding:.7rem .8rem;color:var(--muted)}.workflow-step::after{position:absolute;right:1rem;bottom:-.45rem;left:1rem;height:3px;border-radius:4px 4px 0 0;background:var(--green);content:"";opacity:0}.workflow-step.is-active{border-color:rgba(0,87,64,.13);background:#f2f8f5;color:var(--green);box-shadow:0 6px 14px rgba(0,87,64,.07)}.workflow-step.is-active::after{opacity:1}.step-icon{display:grid;width:2.05rem;height:2.05rem;flex:0 0 2.05rem;place-items:center;border:1px solid rgba(0,87,64,.13);border-radius:.6rem;background:#fff;color:var(--green);font-size:.75rem;font-weight:800}.workflow-step.is-active .step-icon{background:var(--green);color:#fff}.workflow-step>span:last-child{display:grid;min-width:0;gap:.15rem}.workflow-step strong{font-size:.78rem}.workflow-step small{overflow:hidden;color:var(--muted);font-size:.65rem;text-overflow:ellipsis;white-space:nowrap}.import-workspace{display:grid;grid-template-columns:minmax(17rem,21rem) minmax(0,1fr);gap:1.1rem}.import-sidebar,.import-content{display:grid;align-content:start;gap:1.1rem}.admin-panel{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.9);border-radius:1.15rem;background:linear-gradient(145deg,rgba(255,255,255,.97),rgba(255,255,255,.78));box-shadow:0 16px 40px rgba(33,53,47,.075),inset 0 1px #fff}.admin-panel::before{position:absolute;inset:0 0 auto;height:4rem;background:linear-gradient(110deg,rgba(255,255,255,.7),rgba(255,255,255,.12) 55%,transparent);content:"";pointer-events:none}.admin-panel>*{position:relative}.template-panel,.guide-panel,.upload-panel{padding:1.25rem}.panel-heading{display:flex;align-items:flex-start;gap:.8rem;margin-bottom:1rem}.panel-heading.compact{margin-bottom:.85rem}.panel-heading h2{margin:0;color:var(--ink);font-size:1.05rem;font-weight:800;letter-spacing:-.015em}.panel-heading p{margin:.25rem 0 0;color:var(--muted);font-size:.75rem;line-height:1.45}.section-icon{display:grid;width:2.35rem;height:2.35rem;flex:0 0 2.35rem;place-items:center;border:1px solid rgba(0,87,64,.12);border-radius:.7rem;background:var(--green-soft);color:var(--green)}.section-icon svg{width:1.15rem;height:1.15rem}.template-list{display:grid;gap:.65rem}.template-button{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.7rem;border:1px solid var(--line);border-radius:.75rem;padding:.75rem;background:#fff;color:var(--ink);transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}.template-button:hover{border-color:rgba(0,87,64,.35);box-shadow:0 8px 18px rgba(33,53,47,.08);transform:translateY(-1px)}.template-button.is-primary{border-color:rgba(0,87,64,.22);background:#f3f8f5}.template-button>svg{color:var(--green)}.template-button strong,.template-button small{display:block}.template-button strong{font-size:.78rem}.template-button small{margin-top:.15rem;color:var(--muted);font-size:.66rem}.file-badge{display:grid;min-width:2.75rem;height:2rem;place-items:center;border-radius:.45rem;background:var(--green);color:#fff;font-size:.62rem;font-weight:900;letter-spacing:.04em}.file-badge.is-muted{background:#52635e}.guide-list{display:grid;gap:.65rem;margin:0;padding:0;list-style:none}.guide-list li{display:flex;align-items:flex-start;gap:.55rem;color:#52635e;font-size:.75rem;line-height:1.45}.guide-check{display:grid;width:1.25rem;height:1.25rem;flex:0 0 1.25rem;place-items:center;border-radius:50%;background:var(--green-soft);color:var(--green)}.guide-check svg{width:.8rem;height:.8rem;stroke-width:2.4}.capacity-note{display:grid;grid-template-columns:auto 1fr;gap:.05rem .35rem;margin-top:1rem;border-left:3px solid var(--gold);padding:.65rem .75rem;background:#faf9f5}.capacity-note strong{color:var(--green);font-size:.85rem}.capacity-note span{align-self:center;color:var(--muted);font-size:.67rem}.capacity-note i{grid-column:1/-1;height:1px;margin:.15rem 0;background:var(--line)}.upload-zone{display:flex;min-height:15rem;width:100%;flex-direction:column;align-items:center;justify-content:center;border:2px dashed #c8d4cd;border-radius:.85rem;padding:1.75rem;background:#fbfcfa;color:var(--ink);text-align:center;transition:border-color .2s ease,background .2s ease,transform .2s ease}.upload-zone:hover,.upload-zone.dragging{border-color:var(--green);background:#f0f7f3}.upload-zone.dragging{transform:scale(1.005)}.upload-zone.selected{border-style:solid;border-color:rgba(0,87,64,.35);background:#f5faf7}.upload-icon{display:grid;width:3.25rem;height:3.25rem;place-items:center;border:1px solid rgba(0,87,64,.12);border-radius:.85rem;background:var(--green-soft);color:var(--green);box-shadow:0 8px 18px rgba(0,87,64,.08)}.upload-icon svg{width:1.5rem;height:1.5rem;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.upload-zone strong{margin-top:.9rem;font-size:.9rem}.upload-zone>span:not(.upload-icon):not(.browse-button){margin-top:.3rem;color:var(--muted);font-size:.72rem}.browse-button{margin-top:.85rem;border-radius:.5rem;background:var(--green);padding:.55rem .8rem;color:#fff;font-size:.72rem;font-weight:800}.upload-actions,.review-actions{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:.9rem}.notice{display:flex;align-items:flex-start;gap:.7rem;border:1px solid;border-radius:.85rem;padding:.85rem 1rem;font-size:.78rem}.notice p{margin:.2rem 0 0;line-height:1.45}.notice-icon{display:grid;width:1.8rem;height:1.8rem;flex:0 0 1.8rem;place-items:center;border-radius:50%;font-weight:900}.notice.is-error{border-color:#ebc9c4;background:#fff1ef;color:#93362d}.notice.is-error .notice-icon{background:#f8ddd9}.notice.is-success{border-color:#bedfce;background:#edf8f2;color:#236843}.notice.is-success .notice-icon{background:#d7eee2}.review-panel{padding:1.25rem}.review-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.summary-grid{display:grid;grid-template-columns:repeat(3,4.4rem);gap:.45rem}.summary-chip{display:grid;justify-items:center;border:1px solid #e3e8e4;border-radius:.65rem;padding:.5rem;background:#f5f7f5}.summary-chip strong{font-size:1rem}.summary-chip span{color:var(--muted);font-size:.58rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.summary-valid{border-color:#c6e3d3;background:#eef8f2;color:#26704a}.summary-invalid{border-color:#eccdca;background:#fff1ef;color:#a43f36}.review-table-wrap{overflow:auto;border:1px solid var(--line);border-radius:.8rem}.review-table{width:100%;min-width:53rem;border-collapse:collapse;font-size:.75rem}.review-table th,.review-table td{border-bottom:1px solid var(--line);padding:.7rem .65rem;text-align:left;vertical-align:top}.review-table th{background:#f6f5f1;color:var(--muted);font-size:.62rem;letter-spacing:.07em;text-transform:uppercase}.review-table tbody tr:hover{background:#fbfcfa}.review-table tbody tr:last-child td{border-bottom:0}.row-number{color:var(--muted);font-weight:800}.date-cell{max-width:16rem;color:#52635e}.date-cell span,.date-cell small{display:block}.date-cell small{margin-top:.2rem;color:var(--green);font-weight:800}.time-cell{color:#52635e;white-space:nowrap}.result-badge{display:inline-flex;border-radius:999px;padding:.3rem .5rem;font-size:.62rem;font-weight:800}.bg-emerald-50{background:#e6f4ec}.text-emerald-700{color:#216842}.bg-red-50{background:#fbe7e4}.text-red-700{color:#9c382f}.row-errors{display:grid;gap:.2rem;max-width:20rem;margin:.45rem 0 0;padding-left:1rem;color:#9c382f;font-size:.66rem;line-height:1.4}.review-actions{margin-top:1rem}.review-actions p{margin:0;color:var(--muted);font-size:.75rem}@media(max-width:1100px){.import-workspace{grid-template-columns:1fr}.import-sidebar{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:720px){.content-header{align-items:flex-start;flex-direction:column}.workflow-steps{grid-template-columns:1fr}.workflow-step::after{display:none}.import-sidebar{grid-template-columns:1fr}.review-heading{flex-direction:column}.summary-grid{width:100%;grid-template-columns:repeat(3,minmax(0,1fr))}.upload-actions,.review-actions{align-items:stretch;flex-direction:column}.upload-actions .button,.review-actions .button,.content-header .button{width:100%}}@media(prefers-reduced-motion:reduce){.button,.template-button,.upload-zone{transition:none}}
.result-badge.is-valid{background:#e6f4ec;color:#216842}.result-badge.is-invalid{background:#fbe7e4;color:#9c382f}
.recurring-example{display:grid;gap:.45rem;margin-top:1rem;border:1px solid rgba(0,87,64,.13);border-radius:.7rem;padding:.75rem;background:#f4f9f6}.recurring-example>strong{color:var(--green);font-size:.72rem}.recurring-example code,.date-cell code{overflow-wrap:anywhere;border-radius:.35rem;background:#e7f1ec;padding:.38rem;color:#174f3c;font-size:.64rem;line-height:1.4}.recurring-example dl{display:grid;gap:.3rem;margin:0}.recurring-example dl div{display:grid;grid-template-columns:4.3rem 1fr;gap:.4rem}.recurring-example dt{color:var(--muted);font-size:.64rem;font-weight:700}.recurring-example dd{margin:0;color:var(--ink);font-size:.66rem}.date-cell{max-width:19rem}.date-cell span,.date-cell code{display:block}.date-cell span{margin-top:.18rem}.date-cell code{margin-top:.4rem}.date-type{display:block;color:var(--ink);font-size:.7rem}
</style>
