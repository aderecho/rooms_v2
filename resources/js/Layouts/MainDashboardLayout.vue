<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import IconButton from '@/Components/IconButton.vue';
import MessageFunction from '@/Components/MessageFunction.vue';

const props = defineProps({
    totalAccounts: { type: Number, default: 0 },
    totalDepartments: { type: Number, default: 0 },
    totalColleges: { type: Number, default: 0 },
    totalRooms: { type: Number, default: 0 },
    inventoryitems: { type: Object, default: () => ({}) },
});

const page = usePage();
const rooms = computed(() => page.props.rooms);
const searchQuery = ref('');
const managedRoomSearch = ref('');
const allRoomsSearch = ref('');
const dashboardSettingsOpen = ref(false);
const previewRoomEquipmentsText = ref('');
const roomDirectoryRef = ref(null);
const scheduleFormRef = ref(null);
const isCreateRoomPanelOpen = ref(false);
const isAllRoomsPanelVisible = ref(false);

const counts = computed(() => ({
    totalAccounts: page.props.totalAccounts ?? props.totalAccounts,
    totalDepartments: page.props.totalDepartments ?? props.totalDepartments,
    totalColleges: page.props.totalColleges ?? props.totalColleges,
    totalRooms: page.props.totalRooms ?? props.totalRooms,
}));

const showCreateSuccess = ref(false);
const showEditSuccess = ref(false);
const showDeleteSuccess = ref(false);
const showError = ref(false);
const showInfo = ref(false);
const deletedRoomName = ref('');
const errorMessage = ref('');
const infoMessage = ref('');

const isDetailsModalVisible = ref(false);
const currentViewedDetails = ref(null);
const isRoomPreviewPanelVisible = ref(false);
const currentPreviewRoom = ref(null);
const shouldReturnToAllRoomsPanel = ref(false);

const buildings = computed(() => page.props.buildings || []);
const colleges = computed(() => page.props.colleges || []);
const departments = computed(() => page.props.departments || []);
const roomTypes = computed(() => page.props.roomTypes || []);
const users = computed(() => page.props.users || []);

const roomRecords = computed(() => rooms.value?.data || []);
const allRoomRecords = computed(() => page.props.allRooms || roomRecords.value);
const calendarSchedules = computed(() => page.props.calendarSchedules || []);

const parseDateKey = (dateKey) => {
    if (!dateKey) return null;
    const [year, month, day] = String(dateKey).split('-').map(Number);
    if (!year || !month || !day) return null;
    return new Date(year, month - 1, day);
};

const formatDayLabel = (dateKey, options = { month: 'short', day: 'numeric' }) => {
    const date = parseDateKey(dateKey);
    return date ? date.toLocaleDateString('en-US', options) : 'No date';
};

const formatWeekday = (dateKey, options = { weekday: 'short' }) => {
    const date = parseDateKey(dateKey);
    return date ? date.toLocaleDateString('en-US', options) : '';
};

const addDays = (date, days) => {
    const nextDate = new Date(date);
    nextDate.setDate(nextDate.getDate() + days);
    return nextDate;
};

const toDateKey = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const roomMatchesQuery = (room, query) => (
    room.room_name?.toLowerCase().includes(query) ||
    room.room_code?.toLowerCase().includes(query) ||
    room.college?.college_name?.toLowerCase().includes(query) ||
    room.user_account?.username?.toLowerCase().includes(query) ||
    room.user_account?.full_name?.toLowerCase().includes(query) ||
    room.user_account?.email?.toLowerCase().includes(query) ||
    room.location?.toLowerCase().includes(query) ||
    room.building?.building_name?.toLowerCase().includes(query) ||
    room.building?.building_code?.toLowerCase().includes(query)
);

const scheduleMatchesQuery = (schedule, query) => (
    schedule.cfic_id?.toLowerCase().includes(query) ||
    schedule.event_title?.toLowerCase().includes(query) ||
    schedule.course_code?.toLowerCase().includes(query) ||
    schedule.course_name?.toLowerCase().includes(query) ||
    schedule.faculty_name?.toLowerCase().includes(query) ||
    schedule.day?.toLowerCase().includes(query) ||
    schedule.status?.toLowerCase().includes(query)
);

const roomRecordMatchesQuery = (room, query) => (
    roomMatchesQuery(room, query) ||
    room.schedules?.some((schedule) => scheduleMatchesQuery(schedule, query))
);

const filteredRooms = computed(() => {
    if (!searchQuery.value.trim()) return roomRecords.value;

    const query = searchQuery.value.toLowerCase().trim();

    return roomRecords.value.filter((room) => roomRecordMatchesQuery(room, query));
});

const stats = computed(() => [
    {
        label: 'Total Rooms',
        value: counts.value.totalRooms,
        note: 'Available campus spaces',
        target: '/Rooms',
    },
    {
        label: 'User Accounts',
        value: counts.value.totalAccounts,
        note: 'Registered system users',
        target: '/UserAccountPage',
    },
    {
        label: 'Departments',
        value: counts.value.totalDepartments,
        note: 'Academic departments',
        target: '/Departments',
    },
    {
        label: 'Colleges',
        value: counts.value.totalColleges,
        note: 'College records',
        target: '/Analytics',
    },
]);

const projectItems = computed(() => {
    const source = allRoomRecords.value;
    const query = managedRoomSearch.value.toLowerCase().trim();
    const managedRooms = query
        ? source.filter((room) => roomRecordMatchesQuery(room, query))
        : source;

    return managedRooms.slice(0, 8).map((room, index) => ({
        id: room.id ?? index,
        title: room.room_name || `Room ${index + 1}`,
        subtitle: room.college?.college_name || room.location || 'Campus room',
        meta: room.schedules?.length ? `${room.schedules.length} schedules` : 'No schedules yet',
        room,
        color: ['#005740', '#256b57', '#6b7280', '#3f7d68', '#23483c'][index % 5],
    }));
});

const allRoomsPanelItems = computed(() => {
    const query = allRoomsSearch.value.toLowerCase().trim();
    const source = query
        ? allRoomRecords.value.filter((room) => roomRecordMatchesQuery(room, query))
        : allRoomRecords.value;

    return source.map((room, index) => ({
        id: room.id ?? index,
        room,
        color: ['#005740', '#256b57', '#6b7280', '#3f7d68', '#23483c'][index % 5],
    }));
});

const activeRooms = computed(() => filteredRooms.value.slice(0, 6));

const dashboardCalendar = computed(() => {
    const startDate = parseDateKey(page.props.calendarStart) || new Date();
    const endDate = parseDateKey(page.props.calendarEnd) || addDays(startDate, 41);
    const monthLabel = page.props.calendarMonthLabel || startDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const activeMonth = Number.isInteger(page.props.calendarActiveMonth)
        ? page.props.calendarActiveMonth
        : startDate.getMonth();
    const activeYear = Number.isInteger(page.props.calendarActiveYear)
        ? page.props.calendarActiveYear
        : startDate.getFullYear();
    const schedulesByDate = calendarSchedules.value.reduce((groups, schedule) => {
        const key = schedule.date;
        if (!groups[key]) groups[key] = [];
        groups[key].push(schedule);
        return groups;
    }, {});

    const days = [];
    for (let date = new Date(startDate); date <= endDate; date = addDays(date, 1)) {
        const dateKey = toDateKey(date);
        days.push({
            date: dateKey,
            dayNumber: date.getDate(),
            isCurrentMonth: date.getMonth() === activeMonth && date.getFullYear() === activeYear,
            schedules: schedulesByDate[dateKey] || [],
        });
    }

    return {
        monthLabel,
        weekdays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        days,
    };
});

const roomPreviewSchedules = computed(() => currentPreviewRoom.value?.schedules || []);

const roomPreviewScheduleDays = computed(() => {
    const grouped = roomPreviewSchedules.value.reduce((groups, schedule) => {
        const key = schedule.date || 'No date';
        if (!groups[key]) groups[key] = [];
        groups[key].push(schedule);
        return groups;
    }, {});

    return Object.entries(grouped).map(([date, schedules]) => ({
        date,
        label: date === 'No date' ? 'No date' : formatDayLabel(date, { month: 'short', day: 'numeric' }),
        weekday: date === 'No date' ? '' : formatWeekday(date, { weekday: 'short' }),
        schedules,
    }));
});

const progressValue = computed(() => {
    if (!counts.value.totalRooms) return 0;
    return Math.min(100, Math.round((filteredRooms.value.length / counts.value.totalRooms) * 100));
});

const createRoomForm = useForm({
    room_name: '',
    room_code: '',
    building_id: '',
    college_id: '',
    department_id: '',
    room_type_id: '',
    assigned_user_id: '',
    floor_number: 0,
    location: '',
    capacity: 1,
    description: '',
    equipments: [],
});

const previewRoomForm = useForm({
    room_name: '',
    room_code: '',
    building_id: '',
    college_id: '',
    department_id: '',
    room_type_id: '',
    assigned_user_id: '',
    floor_number: 0,
    location: '',
    capacity: 1,
    description: '',
    equipments: [],
});

const scheduleForm = useForm({
    type: 'Meeting',
    title: '',
    date: '',
    startTime: '08:00',
    endTime: '09:00',
    numberParticipants: '',
    requester: '',
    subject: '',
    section: '',
    faculty: '',
    organizer: '',
    description: '',
});

const triggerToast = (type, data = {}) => {
    showCreateSuccess.value = false;
    showEditSuccess.value = false;
    showDeleteSuccess.value = false;
    showError.value = false;
    showInfo.value = false;

    if (type === 'create') showCreateSuccess.value = true;
    if (type === 'edit') showEditSuccess.value = true;
    if (type === 'delete') {
        showDeleteSuccess.value = true;
        deletedRoomName.value = data.name || '';
    }
    if (type === 'error') {
        showError.value = true;
        errorMessage.value = data.message || 'An error occurred!';
    }
    if (type === 'info') {
        showInfo.value = true;
        infoMessage.value = data.message || '';
    }

    setTimeout(() => {
        showCreateSuccess.value = false;
        showEditSuccess.value = false;
        showDeleteSuccess.value = false;
        showError.value = false;
        showInfo.value = false;
    }, 5000);
};

const populatePreviewRoomForm = (room) => {
    previewRoomForm.clearErrors();
    previewRoomForm.defaults({
        room_name: room?.room_name || '',
        room_code: room?.room_code || '',
        building_id: room?.building_id || room?.building?.id || '',
        college_id: room?.college_id || room?.college?.id || '',
        department_id: room?.department_id || '',
        room_type_id: room?.room_type_id || '',
        assigned_user_id: room?.assigned_user_id || room?.user_account?.id || '',
        floor_number: Number(room?.floor_number ?? 0),
        location: room?.location || '',
        capacity: Number(room?.capacity ?? 1),
        description: room?.description || '',
        equipments: Array.isArray(room?.equipments) ? room.equipments : [],
    });
    previewRoomEquipmentsText.value = Array.isArray(room?.equipments) ? room.equipments.join(', ') : '';
    previewRoomForm.reset();
};

const populateScheduleForm = (room) => {
    scheduleForm.clearErrors();
    scheduleForm.defaults({
        type: 'Meeting',
        title: '',
        date: '',
        startTime: '08:00',
        endTime: '09:00',
        numberParticipants: room?.capacity || '',
        requester: page.props.auth?.user?.name || '',
        subject: '',
        section: '',
        faculty: '',
        organizer: room?.college?.college_name || '',
        description: '',
    });
    scheduleForm.reset();
};

const handleSearch = () => {
    const count = filteredRooms.value.length;
    triggerToast('info', {
        message: searchQuery.value.trim()
            ? `Found ${count} room ${count === 1 ? 'record' : 'records'}`
            : 'Showing all room records',
    });

    roomDirectoryRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });

    if (searchQuery.value.trim() && count === 1) {
        handleViewDetails(filteredRooms.value[0]);
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    triggerToast('info', { message: 'Search cleared' });
};

const handleViewDetails = (room) => {
    currentViewedDetails.value = room || null;
    isDetailsModalVisible.value = true;
};

const openRoomPreviewPanel = (room, returnToAllRooms = false) => {
    if (!room) {
        triggerToast('info', { message: 'Room record is not available for this schedule.' });
        return;
    }

    currentPreviewRoom.value = room || null;
    shouldReturnToAllRoomsPanel.value = returnToAllRooms;
    populatePreviewRoomForm(room);
    populateScheduleForm(room);
    isRoomPreviewPanelVisible.value = true;
};

const openScheduleRoom = (schedule) => {
    const room = allRoomRecords.value.find((item) => item.id === schedule.room_id);
    openRoomPreviewPanel(room || null);
};

const closeRoomPreviewPanel = () => {
    isRoomPreviewPanelVisible.value = false;
    currentPreviewRoom.value = null;

    if (shouldReturnToAllRoomsPanel.value) {
        isAllRoomsPanelVisible.value = true;
    }

    shouldReturnToAllRoomsPanel.value = false;
};

const openCompactRoomDetails = (room) => {
    closeRoomPreviewPanel();
    handleViewDetails(room);
};

const openRoomPreviewFromAllRooms = (room) => {
    closeAllRoomsPanel();
    openRoomPreviewPanel(room, true);
};

const openCreateRoomFromAllRooms = () => {
    closeAllRoomsPanel();
    openCreateRoomPanel();
};

const submitPreviewRoomUpdate = () => {
    if (!currentPreviewRoom.value?.id) return;

    previewRoomForm
        .transform((data) => ({
            ...data,
            equipments: previewRoomEquipmentsText.value
                .split(/[\n,;]+/)
                .map((item) => item.trim())
                .filter(Boolean),
        }))
        .put(`/Rooms/${currentPreviewRoom.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('edit', { message: 'Room details updated successfully.' });
            },
            onError: () => {
                triggerToast('error', { message: 'Unable to update room. Please check the required fields.' });
            },
        });
};

const submitInlineSchedule = () => {
    if (!currentPreviewRoom.value) return;

    scheduleForm
        .transform((data) => ({
            ...data,
            room: currentPreviewRoom.value.room_name || currentPreviewRoom.value.room_code,
            startTime: data.startTime?.length === 5 ? `${data.startTime}:00` : data.startTime,
            endTime: data.endTime?.length === 5 ? `${data.endTime}:00` : data.endTime,
            numberParticipants: data.numberParticipants ? Number(data.numberParticipants) : null,
        }))
        .post('/Schedule', {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('create', { message: 'Schedule request created successfully.' });
                populateScheduleForm(currentPreviewRoom.value);
            },
            onError: () => {
                triggerToast('error', { message: 'Unable to create schedule. Please check the schedule fields.' });
            },
        });
};

const handleEditRoom = (room) => {
    triggerToast('edit', { message: `Room "${room?.room_name || 'Unnamed'}" updated successfully!` });
};

const handleDeleteRoom = (room) => {
    if (confirm('Are you sure you want to delete this room record?')) {
        triggerToast('delete', { name: room?.room_name || 'Room' });
    }
};

const closeDetailsModal = () => {
    isDetailsModalVisible.value = false;
    currentViewedDetails.value = null;
};

const openCreateRoomPanel = () => {
    createRoomForm.reset();
    isCreateRoomPanelOpen.value = true;
};

const closeCreateRoomPanel = () => {
    isCreateRoomPanelOpen.value = false;
};

const focusScheduleForm = () => {
    scheduleFormRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const openAllRoomsPanel = () => {
    allRoomsSearch.value = managedRoomSearch.value;
    isAllRoomsPanelVisible.value = true;
};

const closeAllRoomsPanel = () => {
    isAllRoomsPanelVisible.value = false;
};

const submitCreateRoom = () => {
    createRoomForm.post('/Rooms', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateRoomPanelOpen.value = false;
            triggerToast('create', { message: 'Room created successfully.' });
        },
        onError: () => {
            triggerToast('error', { message: 'Failed to create room. Please check the required fields.' });
        },
    });
};

const goToPage = (url) => {
    if (!url) return;
    router.visit(url, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

const goToCardTarget = (target) => {
    if (target) router.visit(target);
};

onMounted(() => {
    const initialSearch = new URLSearchParams(window.location.search).get('search');
    if (initialSearch) {
        searchQuery.value = initialSearch;
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950">
        <MessageFunction
            :show-create-success="showCreateSuccess"
            :show-edit-success="showEditSuccess"
            :show-delete-success="showDeleteSuccess"
            :show-error="showError"
            :show-info="showInfo"
            :deleted-room-name="deletedRoomName"
            :error-message="errorMessage"
            :info-message="infoMessage"
            @close-create="showCreateSuccess = false"
            @close-edit="showEditSuccess = false"
            @close-delete="showDeleteSuccess = false"
            @close-error="showError = false"
            @close-info="showInfo = false"
        />

        <div class="dashboard-shell grid min-h-screen w-full grid-cols-1 lg:grid-cols-[245px_minmax(0,1fr)]">
            <aside class="hidden min-h-screen bg-[#005740] px-6 py-7 text-white lg:block">
                <nav class="space-y-2 text-sm">
                    <a href="/MainDashboard" class="nav-item active">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M4 5h6v6H4V5Zm10 0h6v6h-6V5ZM4 13h6v6H4v-6Zm10 0h6v6h-6v-6Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        </span>
                        Dashboard
                    </a>
                    <a href="/BuildingDashboard" class="nav-item">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M4 20V8l8-4 8 4v12H4Z" stroke="currentColor" stroke-width="1.7"/><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.7"/></svg>
                        </span>
                        Buildings & Rooms
                    </a>
                    <a href="/Schedule" class="nav-item">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M7 3v4M17 3v4M4 9h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/></svg>
                        </span>
                        Calendar
                    </a>
                    <a href="/Analytics" class="nav-item">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M5 19V9m7 10V5m7 14v-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        </span>
                        Analytics
                    </a>
                    <a href="/UserAccountPage" class="nav-item">
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M16 19a4 4 0 0 0-8 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6.5 7a3 3 0 0 0-3-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        </span>
                        Team
                    </a>
                </nav>

                <div class="mt-10 text-xs font-semibold uppercase tracking-[0.12em] text-white/50">General</div>
                <nav class="mt-4 space-y-2 text-sm">
                    <button
                        type="button"
                        class="nav-item nav-toggle w-full text-left"
                        :class="{ active: dashboardSettingsOpen }"
                        :aria-expanded="dashboardSettingsOpen"
                        @click="dashboardSettingsOpen = !dashboardSettingsOpen"
                    >
                        <span class="nav-icon">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.7"/><path d="M19 12h2M3 12h2M12 3v2M12 19v2M17 7l1.5-1.5M5.5 18.5 7 17M17 17l1.5 1.5M5.5 5.5 7 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        </span>
                        <span class="nav-label">Settings</span>
                        <svg class="nav-chevron" :class="{ open: dashboardSettingsOpen }" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <Transition name="submenu">
                        <div v-show="dashboardSettingsOpen" class="ml-4 space-y-1 border-l border-white/15 pl-3">
                            <a href="/SamlIntegration" class="nav-subitem">SAML Config</a>
                        </div>
                    </Transition>
                    <form @submit.prevent="router.post('/logout')">
                        <button type="submit" class="nav-item w-full text-left">
                            <span class="nav-icon">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M10 6H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4M15 8l4 4-4 4M19 12H9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <span class="nav-label">Logout</span>
                        </button>
                    </form>
                </nav>

                <!-- <div class="mt-10 rounded-xl border border-white/15 bg-white/10 p-4 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.18)]">
                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-white/60">System Status</p>
                    <p class="mt-3 text-sm font-semibold text-white">UP Cebu Room Management</p>
                    <p class="mt-2 text-xs leading-5 text-white/65">Administrative dashboard for rooms, schedules, and campus resources.</p>
                    <div class="mt-4 flex items-center gap-2 text-xs font-medium text-white">
                        <span class="h-2 w-2 rounded-full bg-emerald-200"></span>
                        Operational
                    </div>
                </div> -->
            </aside>

            <main class="min-w-0 px-4 py-4 sm:px-5 lg:min-h-screen lg:px-6">
                <div class="topbar mb-4 hidden items-center justify-between gap-4 text-white lg:flex">
                    <div class="relative w-full max-w-[420px]">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search rooms, college, location..."
                            class="topbar-search"
                            @keyup.enter="handleSearch"
                        />
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#005740]" viewBox="0 0 24 24" fill="none">
                            <path d="m21 21-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md bg-[#005740]/8 px-2 py-1 text-[10px] font-bold text-[#005740]">⌘F</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" class="topbar-icon" aria-label="Messages">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4V6Zm1.5 1.5 6.5 5 6.5-5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                        </button>
                        <button type="button" class="topbar-icon" aria-label="Notifications">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7Zm-4 10a2 2 0 0 1-4 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        </button>
                        <div class="topbar-profile">
                            <div class="grid h-9 w-9 place-items-center rounded-lg bg-white text-sm font-bold text-[#005740]">A</div>
                            <div class="hidden leading-tight xl:block">
                                <p class="text-sm font-semibold text-white">{{ page.props.auth?.user?.name || 'System Administrator' }}</p>
                                <p class="text-[11px] text-white/65">{{ page.props.auth?.user?.email || 'admin@upcebu.edu.ph' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:gap-4">
                    <article
                        v-for="item in stats"
                        :key="item.label"
                        class="metric-card"
                    >
                        <div class="flex items-start justify-between">
                            <p class="text-sm font-semibold text-white">{{ item.label }}</p>
                            <button
                                type="button"
                                class="grid h-8 w-8 place-items-center rounded-lg border border-white/40 bg-white text-[#005740] transition hover:scale-105"
                                @click="goToCardTarget(item.target)"
                                :aria-label="`Open ${item.label}`"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 17 17 7m0 0H9m8 0v8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </div>
                        <p class="mt-3 text-5xl font-light leading-none text-white">{{ item.value }}</p>
                        <p class="mt-2 flex items-center gap-1 text-xs text-green-100">
                            <span class="rounded border border-green-100/50 px-1 text-[10px]">6%</span>
                            {{ item.note }}
                        </p>
                    </article>
                </section>

                <section class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.75fr)_minmax(300px,0.7fr)] 2xl:gap-4">
                    <article class="dashboard-card dashboard-calendar-card">
                        <div class="dashboard-calendar-header">
                            <div>
                                <h2 class="text-base font-semibold text-black">Calendar</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ calendarSchedules.length }} schedules visible on dashboard</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="calendar-month-label">{{ dashboardCalendar.monthLabel }}</span>
                                <button type="button" class="room-preview-primary" @click="openAllRoomsPanel">Set Schedule</button>
                            </div>
                        </div>
                        <div class="calendar-weekdays">
                            <span v-for="weekday in dashboardCalendar.weekdays" :key="weekday">{{ weekday }}</span>
                        </div>
                        <div class="calendar-month-grid">
                            <div
                                v-for="day in dashboardCalendar.days"
                                :key="day.date"
                                class="calendar-day-cell"
                                :class="{ 'calendar-day-muted': !day.isCurrentMonth }"
                            >
                                <div class="calendar-day-top">
                                    <span>{{ day.dayNumber }}</span>
                                    <strong v-if="day.schedules.length">{{ day.schedules.length }}</strong>
                                </div>
                                <div class="calendar-events">
                                    <button
                                        v-for="schedule in day.schedules.slice(0, 3)"
                                        :key="schedule.id"
                                        type="button"
                                        class="calendar-event-pill"
                                        @click="openScheduleRoom(schedule)"
                                    >
                                        <span>{{ schedule.start_time }}</span>
                                        <strong>{{ schedule.room_code || schedule.room_name || 'Room' }}</strong>
                                        <em>{{ schedule.course_name || schedule.event_title || 'Schedule' }}</em>
                                    </button>
                                    <span v-if="day.schedules.length > 3" class="calendar-more">+{{ day.schedules.length - 3 }} more</span>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="dashboard-card">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-base font-semibold text-black">Managed Rooms</h2>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700"
                                    @click="openAllRoomsPanel"
                                >
                                    All Rooms
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700"
                                    @click="openCreateRoomPanel"
                                >
                                    + New
                                </button>
                            </div>
                        </div>
                        <div class="relative mb-3">
                            <input
                                v-model="managedRoomSearch"
                                type="text"
                                placeholder="Search managed rooms..."
                                class="managed-room-search"
                            />
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none">
                                <path d="m21 21-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                            <button
                                v-if="managedRoomSearch"
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 hover:text-[#005740]"
                                @click="managedRoomSearch = ''"
                            >
                                Clear
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div
                                v-for="project in projectItems"
                                :key="project.id"
                                class="managed-room-row"
                            >
                                <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg text-white" :style="{ backgroundColor: project.color }">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M7 17 17 7M8 7h9v9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </span>
                                <button type="button" class="min-w-0 flex-1 text-left" @click="openRoomPreviewPanel(project.room)">
                                    <span class="block truncate text-sm font-semibold leading-tight text-black">{{ project.title }}</span>
                                    <span class="block truncate text-xs leading-4 text-slate-500">{{ project.subtitle }}</span>
                                    <span class="block text-[11px] leading-3 text-slate-400">{{ project.meta }}</span>
                                </button>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <button type="button" class="room-action-button room-action-primary" @click="openRoomPreviewPanel(project.room)">
                                        Preview
                                    </button>
                                    <button type="button" class="room-action-button" @click="router.visit('/Rooms')">
                                        Open
                                    </button>
                                </div>
                            </div>
                            <div v-if="projectItems.length === 0" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                No managed rooms found.
                            </div>
                        </div>
                    </article>
                </section>

                <section class="mt-3 grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.25fr)_minmax(330px,0.8fr)] 2xl:gap-4">
                    <article ref="roomDirectoryRef" class="dashboard-card">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-base font-semibold text-black">Room Directory</h2>
                            <div class="relative w-full sm:w-80">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search rooms, college, location..."
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-10 text-sm outline-none transition focus:border-[#005740] focus:ring-2 focus:ring-[#005740]/10"
                                    @keyup.enter="handleSearch"
                                />
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none">
                                    <path d="m21 21-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                                <button v-if="searchQuery" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700" @click="clearSearch">x</button>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-[24px] border border-slate-100">
                            <div
                                v-for="room in activeRooms"
                                :key="room.id"
                                class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 border-b border-slate-100 bg-white px-4 py-3 last:border-b-0 hover:bg-primary-50/40"
                            >
                                <button type="button" class="min-w-0 text-left" @click="handleViewDetails(room)">
                                    <p class="truncate text-sm font-semibold text-black">{{ room.room_name || 'Unnamed Room' }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ room.college?.college_name || room.location || 'Campus location' }}</p>
                                </button>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="icon-action text-[#005740]" @click="handleEditRoom(room)">
                                        <svg viewBox="0 0 24 24" fill="none"><path d="m4 20 4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Z" stroke="currentColor" stroke-width="1.7"/></svg>
                                    </button>
                                    <button type="button" class="icon-action text-red-600" @click="handleDeleteRoom(room)">
                                        <svg viewBox="0 0 24 24" fill="none"><path d="M6 7h12m-9 0V5h6v2m-7 3v8m4-8v8m4-8v8M8 21h8a2 2 0 0 0 2-2V7H6v12a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div v-if="activeRooms.length === 0" class="bg-white px-4 py-10 text-center text-sm text-slate-500">
                                No matching room records found.
                            </div>
                        </div>

                        <div v-if="rooms?.links && !searchQuery" class="mt-4 flex justify-end">
                            <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500">
                                <span v-if="rooms.from">{{ rooms.from }}-{{ rooms.to }} of {{ rooms.total }}</span>
                                <button
                                    v-for="link in rooms.links"
                                    :key="link.label"
                                    type="button"
                                    class="rounded-lg px-2 py-1 transition"
                                    :class="link.active ? 'bg-[#005740] text-white' : 'hover:bg-slate-100'"
                                    :disabled="!link.url"
                                    @click="goToPage(link.url)"
                                    v-html="link.label"
                                ></button>
                            </div>
                        </div>
                    </article>

                    <article class="dashboard-card">
                        <h2 class="text-base font-semibold text-black">Records Overview</h2>
                        <div class="mt-6 grid place-items-center">
                            <div class="progress-ring" :style="{ '--progress': `${progressValue * 3.6}deg` }">
                                <div class="grid h-32 w-32 place-items-center rounded-full bg-white">
                                    <div class="text-center">
                                        <p class="text-3xl font-semibold text-black">{{ progressValue }}%</p>
                                        <p class="text-xs text-slate-500">Visible records</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-primary-50 p-4">
                                <p class="text-xs text-slate-500">Visible</p>
                                <p class="text-2xl font-semibold text-[#005740]">{{ filteredRooms.length }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-100 p-4">
                                <p class="text-xs text-slate-500">Total</p>
                                <p class="text-2xl font-semibold text-black">{{ counts.totalRooms }}</p>
                            </div>
                        </div>
                    </article>
                </section>
            </main>
        </div>

        <transition name="fade">
            <div v-if="isCreateRoomPanelOpen" class="fixed inset-0 z-50 bg-slate-950/45" @click="closeCreateRoomPanel"></div>
        </transition>

        <transition name="slide-panel">
            <aside v-if="isCreateRoomPanelOpen" class="fixed right-0 top-0 z-[60] h-screen w-full max-w-xl overflow-y-auto border-l border-slate-200 bg-white shadow-2xl">
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-white px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-bold text-slate-950">Create New Room</h2>
                            <p class="mt-1 text-sm text-slate-500">Add a room record to the campus directory.</p>
                        </div>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="closeCreateRoomPanel">
                            Close
                        </button>
                    </div>
                </div>

                <form class="space-y-5 px-6 py-6" @submit.prevent="submitCreateRoom">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="form-field">
                            <span>Room Name</span>
                            <input v-model="createRoomForm.room_name" required type="text" placeholder="e.g. AVR 201" />
                            <small v-if="createRoomForm.errors.room_name">{{ createRoomForm.errors.room_name }}</small>
                        </label>
                        <label class="form-field">
                            <span>Room Code</span>
                            <input v-model="createRoomForm.room_code" required type="text" placeholder="e.g. AVR-201" />
                            <small v-if="createRoomForm.errors.room_code">{{ createRoomForm.errors.room_code }}</small>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="form-field">
                            <span>Building</span>
                            <select v-model="createRoomForm.building_id" required>
                                <option value="" disabled>Select building</option>
                                <option v-for="building in buildings" :key="building.id" :value="building.id">
                                    {{ building.building_name }}
                                </option>
                            </select>
                            <small v-if="createRoomForm.errors.building_id">{{ createRoomForm.errors.building_id }}</small>
                        </label>
                        <label class="form-field">
                            <span>Room Type</span>
                            <select v-model="createRoomForm.room_type_id" required>
                                <option value="" disabled>Select type</option>
                                <option v-for="roomType in roomTypes" :key="roomType.id" :value="roomType.id">
                                    {{ roomType.room_type_name }}
                                </option>
                            </select>
                            <small v-if="createRoomForm.errors.room_type_id">{{ createRoomForm.errors.room_type_id }}</small>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="form-field">
                            <span>College</span>
                            <select v-model="createRoomForm.college_id" required>
                                <option value="" disabled>Select college</option>
                                <option v-for="college in colleges" :key="college.id" :value="college.id">
                                    {{ college.college_name }}
                                </option>
                            </select>
                            <small v-if="createRoomForm.errors.college_id">{{ createRoomForm.errors.college_id }}</small>
                        </label>
                        <label class="form-field">
                            <span>Department</span>
                            <select v-model="createRoomForm.department_id">
                                <option value="">None</option>
                                <option v-for="department in departments" :key="department.id" :value="department.id">
                                    {{ department.department_name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <label class="form-field">
                            <span>Floor</span>
                            <input v-model.number="createRoomForm.floor_number" required min="0" type="number" />
                            <small v-if="createRoomForm.errors.floor_number">{{ createRoomForm.errors.floor_number }}</small>
                        </label>
                        <label class="form-field">
                            <span>Capacity</span>
                            <input v-model.number="createRoomForm.capacity" required min="1" type="number" />
                            <small v-if="createRoomForm.errors.capacity">{{ createRoomForm.errors.capacity }}</small>
                        </label>
                        <label class="form-field">
                            <span>Assigned User</span>
                            <select v-model="createRoomForm.assigned_user_id">
                                <option value="">None</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.first_name }} {{ user.last_name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <label class="form-field">
                        <span>Location</span>
                        <input v-model="createRoomForm.location" type="text" placeholder="Building wing, floor, or campus location" />
                    </label>

                    <label class="form-field">
                        <span>Description</span>
                        <textarea v-model="createRoomForm.description" rows="4" placeholder="Optional notes about room use, equipment, or access."></textarea>
                    </label>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                        <button type="button" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="closeCreateRoomPanel">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-[#005740] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#006b4f] disabled:cursor-not-allowed disabled:opacity-60" :disabled="createRoomForm.processing">
                            {{ createRoomForm.processing ? 'Creating...' : 'Create Room' }}
                        </button>
                    </div>
                </form>
            </aside>
        </transition>

        <transition name="fade">
            <div v-if="isAllRoomsPanelVisible" class="fixed inset-0 z-50 bg-slate-950/55" @click="closeAllRoomsPanel"></div>
        </transition>

        <transition name="scale-panel">
            <section
                v-if="isAllRoomsPanelVisible"
                class="all-rooms-panel"
                role="dialog"
                aria-modal="true"
                aria-label="All rooms"
            >
                <div class="all-rooms-header">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#005740]">Room Access</p>
                        <h2 class="mt-2 text-3xl font-bold text-slate-950">All Rooms</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ allRoomsPanelItems.length }} of {{ allRoomRecords.length }} rooms visible</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button type="button" class="room-preview-action" @click="openCreateRoomFromAllRooms">+ New Room</button>
                        <button type="button" class="room-preview-action" @click="router.visit('/Rooms')">Manage Rooms</button>
                        <button type="button" class="room-preview-close" @click="closeAllRoomsPanel">Close</button>
                    </div>
                </div>

                <div class="all-rooms-toolbar">
                    <div class="relative w-full max-w-xl">
                        <input
                            v-model="allRoomsSearch"
                            type="text"
                            placeholder="Search all rooms by name, code, building, college..."
                            class="all-rooms-search"
                        />
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none">
                            <path d="m21 21-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        <button
                            v-if="allRoomsSearch"
                            type="button"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 hover:text-[#005740]"
                            @click="allRoomsSearch = ''"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <div class="all-rooms-list">
                    <div
                        v-for="item in allRoomsPanelItems"
                        :key="item.id"
                        class="all-room-row"
                    >
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-white" :style="{ backgroundColor: item.color }">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M4 20V8l8-4 8 4v12H4Z" stroke="currentColor" stroke-width="1.8"/><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.8"/></svg>
                        </span>
                        <button type="button" class="min-w-0 flex-1 text-left" @click="openRoomPreviewFromAllRooms(item.room)">
                            <span class="block truncate text-sm font-bold text-slate-950">{{ item.room.room_name || 'Unnamed Room' }}</span>
                            <span class="mt-1 block truncate text-xs text-slate-500">{{ item.room.room_code || 'No code' }} · {{ item.room.building?.building_name || item.room.location || 'No location' }}</span>
                        </button>
                        <div class="hidden min-w-0 flex-1 text-sm text-slate-600 md:block">
                            <p class="truncate">{{ item.room.college?.college_name || 'No college assigned' }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ item.room.user_account?.username || 'No assigned user' }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="hidden rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-[#005740] sm:inline-flex">
                                {{ item.room.status || 'available' }}
                            </span>
                            <button type="button" class="room-action-button room-action-primary" @click="openRoomPreviewFromAllRooms(item.room)">Preview</button>
                            <button type="button" class="room-action-button" @click="router.visit('/Rooms')">Open</button>
                        </div>
                    </div>

                    <div v-if="allRoomsPanelItems.length === 0" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-12 text-center text-sm text-slate-500">
                        No rooms match your search.
                    </div>
                </div>
            </section>
        </transition>

        <transition name="fade">
            <div v-if="isRoomPreviewPanelVisible" class="fixed inset-0 z-50 bg-slate-950/55" @click="closeRoomPreviewPanel"></div>
        </transition>

        <transition name="scale-panel">
            <section
                v-if="isRoomPreviewPanelVisible"
                class="room-preview-panel"
                role="dialog"
                aria-modal="true"
                aria-label="Room preview"
            >
                <div class="room-preview-header">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#005740]">Room Preview</p>
                        <h2 class="mt-2 truncate text-3xl font-bold text-slate-950">{{ currentPreviewRoom?.room_name || 'Unnamed Room' }}</h2>
                        <p class="mt-1 truncate text-sm text-slate-500">
                            {{ currentPreviewRoom?.college?.college_name || currentPreviewRoom?.location || 'Campus room' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button type="button" class="room-preview-action" @click="submitPreviewRoomUpdate">Save Room</button>
                        <button type="button" class="room-preview-action" @click="focusScheduleForm">Set Schedule</button>
                        <button type="button" class="room-preview-close" @click="closeRoomPreviewPanel">
                            {{ shouldReturnToAllRoomsPanel ? 'Back to All Rooms' : 'Close' }}
                        </button>
                    </div>
                </div>

                <div v-if="currentPreviewRoom" class="room-preview-body">
                    <section class="room-preview-hero">
                        <div>
                            <p class="text-sm font-semibold text-white/70">Room Code</p>
                            <p class="mt-2 text-4xl font-light text-white">{{ currentPreviewRoom.room_code || 'N/A' }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="room-preview-stat">
                                <span>Capacity</span>
                                <strong>{{ currentPreviewRoom.capacity || 'N/A' }}</strong>
                            </div>
                            <div class="room-preview-stat">
                                <span>Status</span>
                                <strong>{{ currentPreviewRoom.status || 'N/A' }}</strong>
                            </div>
                            <div class="room-preview-stat">
                                <span>Schedules</span>
                                <strong>{{ (currentPreviewRoom.schedules || []).length }}</strong>
                            </div>
                            <div class="room-preview-stat">
                                <span>User</span>
                                <strong>{{ currentPreviewRoom.user_account?.username || 'None' }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="room-workspace-grid">
                        <article class="room-preview-card room-edit-card">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3>Editable Room Fields</h3>
                                    <p class="mt-1 text-xs text-slate-500">Tabulated room information for quick checking and editing.</p>
                                </div>
                                <button type="button" class="room-preview-primary" :disabled="previewRoomForm.processing" @click="submitPreviewRoomUpdate">
                                    {{ previewRoomForm.processing ? 'Saving...' : 'Save Changes' }}
                                </button>
                            </div>
                            <div class="room-edit-table">
                                <label class="room-edit-row">
                                    <span>Room Name</span>
                                    <input v-model="previewRoomForm.room_name" required type="text" />
                                    <small v-if="previewRoomForm.errors.room_name">{{ previewRoomForm.errors.room_name }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Room Code</span>
                                    <input v-model="previewRoomForm.room_code" required type="text" />
                                    <small v-if="previewRoomForm.errors.room_code">{{ previewRoomForm.errors.room_code }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Building</span>
                                    <select v-model="previewRoomForm.building_id" required>
                                        <option value="" disabled>Select building</option>
                                        <option v-for="building in buildings" :key="building.id" :value="building.id">{{ building.building_name }}</option>
                                    </select>
                                    <small v-if="previewRoomForm.errors.building_id">{{ previewRoomForm.errors.building_id }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Room Type</span>
                                    <select v-model="previewRoomForm.room_type_id" required>
                                        <option value="" disabled>Select room type</option>
                                        <option v-for="roomType in roomTypes" :key="roomType.id" :value="roomType.id">{{ roomType.room_type_name }}</option>
                                    </select>
                                    <small v-if="previewRoomForm.errors.room_type_id">{{ previewRoomForm.errors.room_type_id }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>College</span>
                                    <select v-model="previewRoomForm.college_id" required>
                                        <option value="" disabled>Select college</option>
                                        <option v-for="college in colleges" :key="college.id" :value="college.id">{{ college.college_name }}</option>
                                    </select>
                                    <small v-if="previewRoomForm.errors.college_id">{{ previewRoomForm.errors.college_id }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Department</span>
                                    <select v-model="previewRoomForm.department_id">
                                        <option value="">None</option>
                                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.department_name }}</option>
                                    </select>
                                </label>
                                <label class="room-edit-row">
                                    <span>Assigned User</span>
                                    <select v-model="previewRoomForm.assigned_user_id">
                                        <option value="">None</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.first_name }} {{ user.last_name }}</option>
                                    </select>
                                </label>
                                <label class="room-edit-row">
                                    <span>Location</span>
                                    <input v-model="previewRoomForm.location" type="text" />
                                </label>
                                <label class="room-edit-row">
                                    <span>Floor</span>
                                    <input v-model.number="previewRoomForm.floor_number" required min="0" type="number" />
                                    <small v-if="previewRoomForm.errors.floor_number">{{ previewRoomForm.errors.floor_number }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Capacity</span>
                                    <input v-model.number="previewRoomForm.capacity" required min="1" type="number" />
                                    <small v-if="previewRoomForm.errors.capacity">{{ previewRoomForm.errors.capacity }}</small>
                                </label>
                                <label class="room-edit-row room-edit-row-wide">
                                    <span>Description</span>
                                    <textarea v-model="previewRoomForm.description" rows="3"></textarea>
                                </label>
                                <label class="room-edit-row room-edit-row-wide">
                                    <span>Equipment</span>
                                    <textarea v-model="previewRoomEquipmentsText" rows="2" placeholder="Separate equipment with commas or new lines"></textarea>
                                    <small v-if="previewRoomForm.errors.equipments">{{ previewRoomForm.errors.equipments }}</small>
                                </label>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" class="room-preview-secondary" @click="openCompactRoomDetails(currentPreviewRoom)">Compact View</button>
                                <button type="button" class="room-preview-danger" @click="handleDeleteRoom(currentPreviewRoom)">Delete</button>
                            </div>
                        </article>

                        <article ref="scheduleFormRef" class="room-preview-card room-schedule-side">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3>Schedule</h3>
                                    <p class="mt-1 text-xs text-slate-500">Calendar-style room availability and quick scheduling.</p>
                                </div>
                                <button type="button" class="room-preview-primary" :disabled="scheduleForm.processing" @click="submitInlineSchedule">
                                    {{ scheduleForm.processing ? 'Saving...' : 'Create Schedule' }}
                                </button>
                            </div>

                            <div class="room-mini-calendar">
                                <div v-if="roomPreviewScheduleDays.length === 0" class="room-empty-calendar">
                                    No schedules found for this room.
                                </div>
                                <template v-else>
                                    <div
                                        v-for="day in roomPreviewScheduleDays"
                                        :key="day.date"
                                        class="room-mini-day"
                                    >
                                        <div class="room-mini-date">
                                            <span>{{ day.weekday }}</span>
                                            <strong>{{ day.label }}</strong>
                                        </div>
                                        <div class="room-mini-events">
                                            <div v-for="schedule in day.schedules" :key="schedule.id" class="room-mini-event">
                                                <span>{{ schedule.start_time || 'N/A' }} - {{ schedule.end_time || 'N/A' }}</span>
                                                <strong>{{ schedule.course_name || schedule.event_title || schedule.cfic_id || 'Schedule' }}</strong>
                                                <em>{{ schedule.status || 'scheduled' }}</em>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="schedule-table-form">
                                <label class="room-edit-row">
                                    <span>Type</span>
                                    <select v-model="scheduleForm.type" required>
                                        <option>Class</option>
                                        <option>Meeting</option>
                                        <option>Event</option>
                                        <option>Other type of activity</option>
                                    </select>
                                    <small v-if="scheduleForm.errors.type">{{ scheduleForm.errors.type }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Title</span>
                                    <input v-model="scheduleForm.title" required type="text" placeholder="Schedule title" />
                                    <small v-if="scheduleForm.errors.title">{{ scheduleForm.errors.title }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Date</span>
                                    <input v-model="scheduleForm.date" required type="date" />
                                    <small v-if="scheduleForm.errors.date">{{ scheduleForm.errors.date }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Participants</span>
                                    <input v-model="scheduleForm.numberParticipants" min="1" type="number" />
                                </label>
                                <label class="room-edit-row">
                                    <span>Start Time</span>
                                    <input v-model="scheduleForm.startTime" required type="time" step="1" />
                                    <small v-if="scheduleForm.errors.startTime">{{ scheduleForm.errors.startTime }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>End Time</span>
                                    <input v-model="scheduleForm.endTime" required type="time" step="1" />
                                    <small v-if="scheduleForm.errors.endTime">{{ scheduleForm.errors.endTime }}</small>
                                </label>
                                <label class="room-edit-row">
                                    <span>Subject / Course</span>
                                    <input v-model="scheduleForm.subject" type="text" />
                                </label>
                                <label class="room-edit-row">
                                    <span>Section</span>
                                    <input v-model="scheduleForm.section" type="text" />
                                </label>
                                <label class="room-edit-row">
                                    <span>Faculty</span>
                                    <input v-model="scheduleForm.faculty" type="text" />
                                </label>
                                <label class="room-edit-row">
                                    <span>Requester</span>
                                    <input v-model="scheduleForm.requester" type="text" />
                                </label>
                                <label class="room-edit-row room-edit-row-wide">
                                    <span>Description</span>
                                    <textarea v-model="scheduleForm.description" rows="3"></textarea>
                                </label>
                            </div>
                        </article>
                    </section>
                </div>
            </section>
        </transition>

        <transition name="fade">
            <div v-if="isDetailsModalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
                    <div class="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
                        <h3 class="text-xl font-bold text-[#005740]">Room Details</h3>
                        <IconButton icon="times" size="sm" color="gray" title="Close" @click="closeDetailsModal" />
                    </div>

                    <div v-if="currentViewedDetails" class="space-y-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">Room Name</label>
                                <p class="mt-1 font-medium text-slate-950">{{ currentViewedDetails.room_name || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">Location</label>
                                <p class="mt-1 text-slate-700">{{ currentViewedDetails.location || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">College</label>
                                <p class="mt-1 text-slate-700">{{ currentViewedDetails.college?.college_name || currentViewedDetails.college || 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">User Account</label>
                                <p class="mt-1 text-slate-700">{{ currentViewedDetails.user_account?.username || 'N/A' }}</p>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <h4 class="font-semibold text-slate-950">Schedules</h4>
                            <div v-if="(currentViewedDetails.schedules || []).length === 0" class="mt-3 rounded-xl bg-slate-50 p-4 text-center text-sm text-slate-500">
                                No schedules found for this room.
                            </div>
                            <div v-else class="mt-3 max-h-60 space-y-2 overflow-y-auto pr-2">
                                <div v-for="sched in currentViewedDetails.schedules" :key="sched.id" class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                    <p class="text-sm font-semibold text-slate-950">{{ sched.course_name || sched.cfic_id || 'Schedule' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ sched.day || 'N/A' }} · {{ sched.start_time || 'N/A' }} - {{ sched.end_time || 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <button v-if="currentViewedDetails" type="button" class="rounded-lg bg-[#005740] px-5 py-2 text-sm font-semibold text-white" @click="handleEditRoom(currentViewedDetails)">
                            Edit Room
                        </button>
                        <button type="button" class="rounded-lg border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600" @click="closeDetailsModal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.dashboard-shell {
    font-family: Inter, Figtree, ui-sans-serif, system-ui, sans-serif;
}

.topbar {
    min-height: 4rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 1.25rem;
    background: #005740;
    padding: 0.65rem 0.85rem;
    box-shadow:
        0 14px 30px rgba(0, 87, 64, 0.16),
        inset 0 1px 0 rgba(255, 255, 255, 0.16);
}

.topbar-search {
    height: 2.6rem;
    width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.94);
    padding: 0 3.3rem 0 2.85rem;
    color: #0f172a;
    font-size: 0.88rem;
    outline: none;
    transition: box-shadow 0.2s ease, background-color 0.2s ease;
}

.topbar-search:focus {
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.24);
}

.topbar-icon {
    display: grid;
    height: 2.6rem;
    width: 2.6rem;
    place-items: center;
    border-radius: 0.85rem;
    background: rgba(255, 255, 255, 0.94);
    color: #005740;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.topbar-icon:hover {
    background: #ffffff;
    transform: translateY(-1px);
}

.topbar-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.95rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.35rem 0.75rem 0.35rem 0.35rem;
}

.nav-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 2.75rem;
    border-radius: 0.75rem;
    padding: 0.68rem 0.85rem;
    color: rgba(255, 255, 255, 0.78);
    outline: none;
    overflow: hidden;
    transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}

.nav-item::before {
    content: '';
    position: absolute;
    left: 0.35rem;
    top: 50%;
    width: 0.2rem;
    height: 1.35rem;
    border-radius: 999px;
    background: #d9f99d;
    opacity: 0;
    transform: translateY(-50%) scaleY(0.35);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.nav-item:hover,
.nav-item:focus-visible,
.nav-item.active {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.14);
    transform: translateX(0.18rem);
}

.nav-item:active {
    transform: translateX(0.18rem) scale(0.99);
}

.nav-item:hover .nav-icon,
.nav-item:focus-visible .nav-icon,
.nav-item.active .nav-icon {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

.nav-item.active::before {
    opacity: 1;
    transform: translateY(-50%) scaleY(1);
}

.nav-subitem {
    display: flex;
    align-items: center;
    border-radius: 0.6rem;
    padding: 0.52rem 0.75rem;
    color: rgba(255, 255, 255, 0.72);
    transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
}

.nav-subitem:hover,
.nav-subitem:focus-visible {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(0.15rem);
}

.nav-icon {
    display: grid;
    height: 1.35rem;
    width: 1.35rem;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 0.5rem;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.nav-label {
    min-width: 0;
    flex: 1;
}

.nav-toggle {
    justify-content: flex-start;
}

.nav-chevron {
    height: 1rem;
    width: 1rem;
    flex: 0 0 auto;
    color: rgba(255, 255, 255, 0.72);
    transition: transform 0.2s ease;
}

.nav-chevron.open {
    transform: rotate(90deg);
}

.nav-icon svg,
.icon-action svg,
.utility-button svg,
.topbar-icon svg {
    height: 1.1rem;
    width: 1.1rem;
}

.submenu-enter-active,
.submenu-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.submenu-enter-from,
.submenu-leave-to {
    opacity: 0;
    transform: translateY(-0.25rem);
}

.utility-button {
    display: grid;
    height: 2.75rem;
    width: 2.75rem;
    place-items: center;
    border-radius: 0.75rem;
    background: white;
    border: 1px solid #e2e8f0;
    color: #0f172a;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.utility-button:hover {
    background: #e7f5f0;
    color: #005740;
}

.metric-card,
.dashboard-card {
    border-radius: 1rem;
    border: 1px solid rgba(0, 87, 64, 0.14);
    box-shadow: 0 16px 36px rgba(15, 23, 42, 0.06);
}

.metric-card {
    position: relative;
    min-height: 132px;
    overflow: hidden;
    padding: 1rem;
    color: #ffffff;
    background:
        linear-gradient(115deg, rgba(255, 255, 255, 0.2), transparent 34%, rgba(255, 255, 255, 0.08) 72%),
        linear-gradient(135deg, #005740 0%, #006b4f 52%, #00785a 100%);
    box-shadow:
        0 18px 38px rgba(0, 87, 64, 0.18),
        inset 0 1px 0 rgba(255, 255, 255, 0.24);
}

.metric-card::before {
    position: absolute;
    inset: -30% auto auto 50%;
    width: 12rem;
    height: 12rem;
    content: "";
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    filter: blur(10px);
}

.metric-card > * {
    position: relative;
    z-index: 1;
}

.dashboard-card {
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(246, 252, 249, 0.98)),
        #ffffff;
    padding: 1rem;
    box-shadow:
        0 16px 36px rgba(15, 23, 42, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.dashboard-calendar-card {
    padding: 0;
    overflow: hidden;
}

.dashboard-calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1px solid #e5eee9;
    padding: 1rem 1.1rem;
}

.calendar-month-label {
    border: 1px solid #d8e5df;
    border-radius: 0.75rem;
    background: #ffffff;
    padding: 0.5rem 0.75rem;
    color: #005740;
    font-size: 0.8rem;
    font-weight: 800;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    border-bottom: 1px solid #e5eee9;
    background: #f8fbfa;
}

.calendar-weekdays span {
    padding: 0.6rem 0.75rem;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
}

.calendar-month-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
}

.calendar-day-cell {
    min-height: 122px;
    border-bottom: 1px solid #e5eee9;
    border-right: 1px solid #e5eee9;
    background: #ffffff;
    padding: 0.55rem;
}

.calendar-day-cell:nth-child(7n) {
    border-right: 0;
}

.calendar-day-muted {
    background: #f8fafc;
}

.calendar-day-muted .calendar-day-top span {
    color: #94a3b8;
}

.calendar-day-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    min-height: 1.5rem;
}

.calendar-day-top span {
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 800;
}

.calendar-day-top strong {
    display: inline-flex;
    min-width: 1.35rem;
    height: 1.35rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: #e7f5f0;
    color: #005740;
    font-size: 0.72rem;
}

.calendar-events {
    margin-top: 0.45rem;
    display: grid;
    gap: 0.35rem;
}

.calendar-event-pill {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 0 0.35rem;
    border-radius: 0.5rem;
    background: #e7f5f0;
    padding: 0.35rem 0.45rem;
    text-align: left;
    color: #005740;
    transition: background-color 0.2s ease, transform 0.2s ease;
}

.calendar-event-pill:hover {
    background: #d9efe7;
    transform: translateY(-1px);
}

.calendar-event-pill span {
    color: #256b57;
    font-size: 0.64rem;
    font-weight: 800;
}

.calendar-event-pill strong {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #005740;
    font-size: 0.68rem;
}

.calendar-event-pill em {
    grid-column: 1 / -1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #475569;
    font-size: 0.66rem;
    font-style: normal;
}

.calendar-more {
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 700;
}

.managed-room-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    border-radius: 0.9rem;
    padding: 0.55rem;
    transition: background-color 0.2s ease;
}

.managed-room-row:hover {
    background: rgba(0, 87, 64, 0.045);
}

.managed-room-search {
    height: 2.45rem;
    width: 100%;
    border: 1px solid #dbe4df;
    border-radius: 0.85rem;
    background: #f8fafc;
    padding: 0 3.7rem 0 2.45rem;
    color: #0f172a;
    font-size: 0.82rem;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.managed-room-search:focus {
    border-color: #005740;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(0, 87, 64, 0.1);
}

.room-action-button {
    border: 1px solid #cbd5e1;
    border-radius: 0.65rem;
    background: #ffffff;
    padding: 0.35rem 0.55rem;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.room-action-button:hover {
    border-color: rgba(0, 87, 64, 0.35);
    color: #005740;
}

.room-action-primary {
    border-color: #005740;
    background: #005740;
    color: #ffffff;
}

.room-action-primary:hover {
    background: #006b4f;
    color: #ffffff;
}

.room-preview-panel {
    position: fixed;
    inset: 4vh 4vw;
    z-index: 60;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(0, 87, 64, 0.16);
    border-radius: 1.35rem;
    background: #ffffff;
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28);
}

.all-rooms-panel {
    position: fixed;
    inset: 4vh 4vw;
    z-index: 60;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(0, 87, 64, 0.16);
    border-radius: 1.35rem;
    background: #ffffff;
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28);
}

.all-rooms-header,
.all-rooms-toolbar {
    border-bottom: 1px solid #e2e8f0;
    padding: 1.25rem 1.5rem;
}

.all-rooms-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.all-rooms-toolbar {
    background: #f8fafc;
}

.all-rooms-search {
    height: 2.75rem;
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 0.9rem;
    background: #ffffff;
    padding: 0 4rem 0 2.75rem;
    color: #0f172a;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.all-rooms-search:focus {
    border-color: #005740;
    box-shadow: 0 0 0 3px rgba(0, 87, 64, 0.1);
}

.all-rooms-list {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 1.5rem 1.5rem;
}

.all-room-row {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    border-bottom: 1px solid #edf2f7;
    padding: 0.85rem 0;
}

.all-room-row:last-child {
    border-bottom: 0;
}

.all-room-row:hover {
    background: linear-gradient(90deg, rgba(0, 87, 64, 0.045), transparent);
}

.room-preview-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    border-bottom: 1px solid #e2e8f0;
    padding: 1.25rem 1.5rem;
}

.room-preview-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.room-preview-hero {
    display: grid;
    grid-template-columns: minmax(0, 0.7fr) minmax(0, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
    border-radius: 1.15rem;
    background:
        linear-gradient(115deg, rgba(255, 255, 255, 0.16), transparent 40%),
        linear-gradient(135deg, #005740 0%, #007a5c 100%);
    padding: 1.35rem;
}

.room-preview-stat {
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.12);
    padding: 0.85rem;
    color: #ffffff;
}

.room-preview-stat span,
.room-preview-field span {
    display: block;
    color: inherit;
    font-size: 0.72rem;
    font-weight: 700;
    opacity: 0.72;
}

.room-preview-stat strong {
    display: block;
    margin-top: 0.35rem;
    font-size: 1.15rem;
}

.room-workspace-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.08fr) minmax(390px, 0.72fr);
    gap: 1rem;
    align-items: start;
}

.room-preview-card {
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    background: linear-gradient(180deg, #ffffff, #f8fbfa);
    padding: 1rem;
}

.room-preview-card h3 {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 800;
}

.room-edit-card,
.room-schedule-side {
    min-width: 0;
}

.room-edit-table,
.schedule-table-form {
    margin-top: 1rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    overflow: hidden;
    border: 1px solid #dfe8e3;
    border-radius: 0.95rem;
    background: #ffffff;
}

.schedule-table-form {
    grid-template-columns: 1fr;
}

.schedule-table-form .room-edit-row {
    border-right: 0;
}

.room-edit-row {
    display: grid;
    min-width: 0;
    grid-template-columns: minmax(108px, 0.38fr) minmax(0, 1fr);
    align-items: center;
    gap: 0.75rem;
    border-bottom: 1px solid #edf2f0;
    padding: 0.65rem 0.75rem;
}

.room-edit-row:nth-child(odd) {
    border-right: 1px solid #edf2f0;
}

.room-edit-row-wide {
    grid-column: 1 / -1;
    border-right: 0 !important;
}

.room-edit-row span {
    color: #334155;
    font-size: 0.74rem;
    font-weight: 800;
}

.room-edit-row input,
.room-edit-row select,
.room-edit-row textarea {
    min-width: 0;
    width: 100%;
    border: 1px solid #d7e2dd;
    border-radius: 0.65rem;
    background: #f8fafc;
    padding: 0.55rem 0.65rem;
    color: #0f172a;
    font-size: 0.82rem;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.room-edit-row input:focus,
.room-edit-row select:focus,
.room-edit-row textarea:focus {
    border-color: #005740;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(0, 87, 64, 0.1);
}

.room-edit-row small {
    grid-column: 2;
    color: #dc2626;
    font-size: 0.72rem;
}

.room-schedule-side {
    position: sticky;
    top: 1rem;
}

.room-mini-calendar {
    margin-top: 1rem;
    display: grid;
    max-height: 280px;
    gap: 0.65rem;
    overflow-y: auto;
    border: 1px solid #dfe8e3;
    border-radius: 0.95rem;
    background: #f8fbfa;
    padding: 0.65rem;
}

.room-empty-calendar {
    border: 1px dashed #cbd5e1;
    border-radius: 0.8rem;
    background: #ffffff;
    padding: 1.5rem;
    text-align: center;
    color: #64748b;
    font-size: 0.84rem;
}

.room-mini-day {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr);
    gap: 0.7rem;
}

.room-mini-date {
    border-radius: 0.8rem;
    background: #005740;
    padding: 0.65rem 0.5rem;
    color: #ffffff;
    text-align: center;
}

.room-mini-date span {
    display: block;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
}

.room-mini-date strong {
    display: block;
    margin-top: 0.15rem;
    font-size: 0.82rem;
}

.room-mini-events {
    display: grid;
    gap: 0.45rem;
    min-width: 0;
}

.room-mini-event {
    border-left: 3px solid #005740;
    border-radius: 0.75rem;
    background: #ffffff;
    padding: 0.55rem 0.65rem;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
}

.room-mini-event span,
.room-mini-event em {
    display: block;
    color: #64748b;
    font-size: 0.7rem;
    font-style: normal;
}

.room-mini-event strong {
    display: block;
    margin: 0.15rem 0;
    color: #0f172a;
    font-size: 0.82rem;
}

.room-preview-field {
    border-radius: 0.85rem;
    background: #f8fafc;
    padding: 0.85rem;
}

.room-preview-field span {
    color: #64748b;
}

.room-preview-field strong {
    display: block;
    margin-top: 0.3rem;
    color: #0f172a;
    font-size: 0.9rem;
}

.room-preview-action,
.room-preview-close,
.room-preview-primary,
.room-preview-secondary,
.room-preview-danger {
    border-radius: 0.75rem;
    padding: 0.55rem 0.8rem;
    font-size: 0.8rem;
    font-weight: 800;
}

.room-preview-action,
.room-preview-secondary {
    border: 1px solid #cbd5e1;
    color: #334155;
}

.room-preview-close {
    border: 1px solid #005740;
    background: #005740;
    color: #ffffff;
}

.room-preview-primary {
    background: #005740;
    color: #ffffff;
}

.room-preview-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.room-preview-schedule {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 0.9rem;
    background: #f8fafc;
    padding: 0.85rem;
}

.room-preview-schedule span {
    border-radius: 999px;
    background: #e7f5f0;
    padding: 0.25rem 0.55rem;
    color: #005740;
    font-size: 0.72rem;
    font-weight: 800;
}

.icon-action {
    display: grid;
    height: 2rem;
    width: 2rem;
    place-items: center;
    border-radius: 0.65rem;
    background: #f8fafc;
    transition: background-color 0.2s ease, transform 0.2s ease;
}

.icon-action:hover {
    background: #e7f5f0;
    transform: translateY(-1px);
}

.progress-ring {
    display: grid;
    height: 10rem;
    width: 10rem;
    place-items: center;
    border-radius: 999px;
    background: conic-gradient(#005740 var(--progress), #edf2ef 0);
}

.form-field {
    display: grid;
    gap: 0.4rem;
}

.form-field span {
    color: #334155;
    font-size: 0.78rem;
    font-weight: 700;
}

.form-field input,
.form-field select,
.form-field textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 0.75rem;
    background: #ffffff;
    padding: 0.65rem 0.75rem;
    color: #0f172a;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
    border-color: #005740;
    box-shadow: 0 0 0 3px rgba(0, 87, 64, 0.12);
}

.form-field small {
    color: #dc2626;
    font-size: 0.75rem;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-panel-enter-active,
.slide-panel-leave-active {
    transition: opacity 0.22s ease, transform 0.22s ease;
}

.slide-panel-enter-from,
.slide-panel-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.scale-panel-enter-active,
.scale-panel-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.scale-panel-enter-from,
.scale-panel-leave-to {
    opacity: 0;
    transform: scale(0.97);
}

@media (max-width: 900px) {
    .room-preview-panel,
    .all-rooms-panel {
        inset: 1rem;
    }

    .room-preview-header,
    .room-preview-hero,
    .all-rooms-header {
        grid-template-columns: 1fr;
    }

    .room-preview-header,
    .all-rooms-header {
        flex-direction: column;
    }

    .all-room-row {
        align-items: flex-start;
    }

    .dashboard-calendar-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .calendar-month-grid,
    .calendar-weekdays {
        min-width: 760px;
    }

    .dashboard-calendar-card {
        overflow-x: auto;
    }

    .room-workspace-grid,
    .room-preview-hero,
    .room-edit-table,
    .schedule-table-form {
        grid-template-columns: 1fr;
    }

    .room-edit-row,
    .room-mini-day {
        grid-template-columns: 1fr;
    }

    .room-edit-row:nth-child(odd) {
        border-right: 0;
    }

    .room-schedule-side {
        position: static;
    }
}
</style>
