import axios from 'axios';
import { ref, onUnmounted } from 'vue';

// Each request is bounded; fetch the remaining pages only for the requested range.
export function useScheduleLoader() {
    const records = ref([]);
    const loading = ref(false);
    const error = ref('');
    let controller;
    const load = async (params) => {
        controller?.abort();
        const active = new AbortController();
        controller = active;
        records.value = [];
        loading.value = true;
        error.value = '';
        try {
            const rows = [];
            let page = 1;
            let last = 1;
            do {
                const response = await axios.get('/Schedule/allocations', {
                    params: { ...params, page, per_page: 100 }, signal: active.signal,
                });
                if (active.signal.aborted) return;
                rows.push(...response.data.data);
                last = response.data.meta.last_page;
                page++;
            } while (page <= last);
            records.value = rows;
        } catch (exception) {
            if (!active.signal.aborted) error.value = 'Unable to load schedules. Please try again.';
        } finally {
            if (controller === active) loading.value = false;
        }
    };
    const cancel = () => { controller?.abort(); records.value = []; loading.value = false; };
    onUnmounted(cancel);
    return { records, loading, error, load, cancel };
}
