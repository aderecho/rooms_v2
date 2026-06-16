export const FINAL_APPOINTMENT_STATUSES = ['completed', 'rejected', 'cancelled'];

export const APPOINTMENT_STATUS_OPTIONS = [
    { value: 'pending', label: 'Pending', description: 'Waiting for admin review' },
    { value: 'in_progress', label: 'In Progress', description: 'Appointment is currently ongoing' },
    { value: 'approved', label: 'Approved', description: 'Appointment has been approved' },
    { value: 'completed', label: 'Completed', description: 'Appointment has finished' },
    { value: 'rejected', label: 'Rejected', description: 'Appointment was rejected' },
    { value: 'cancelled', label: 'Cancelled', description: 'Appointment was cancelled' },
];

const STATUS_META = {
    pending: {
        label: 'Pending',
        badgeClass: '!bg-gray-200 !text-gray-800 !border-gray-400',
        dotClass: '!bg-gray-600',
        ringClass: 'ring-gray-200',
        currentPanelClass: 'border-gray-300 bg-gray-100',
        mutedBadgeClass: '!bg-gray-200 !text-gray-600 !border-gray-300',
        mutedDotClass: '!bg-gray-600',
        headerBadgeClass: '!bg-gray-500 !text-white !border-gray-400',
        headerDotClass: 'bg-gray-200',
        textClass: 'text-gray-700',
    },
    in_progress: {
        label: 'In Progress',
        badgeClass: '!bg-blue-100 !text-blue-800 !border-blue-400',
        dotClass: '!bg-blue-600',
        ringClass: 'ring-blue-200',
        currentPanelClass: 'border-blue-300 bg-blue-100',
        mutedBadgeClass: '!bg-blue-200 !text-blue-800 !border-blue-400',
        mutedDotClass: '!bg-blue-700',
        headerBadgeClass: '!bg-blue-600 !text-white !border-blue-500',
        headerDotClass: 'bg-blue-200',
        textClass: 'text-blue-800',
    },
    approved: {
        label: 'Approved',
        badgeClass: '!bg-green-100 !text-green-800 !border-green-400',
        dotClass: '!bg-green-600',
        ringClass: 'ring-green-200',
        currentPanelClass: 'border-green-300 bg-green-100',
        mutedBadgeClass: '!bg-green-200 !text-green-800 !border-green-400',
        mutedDotClass: '!bg-green-700',
        headerBadgeClass: '!bg-green-600 !text-white !border-green-500',
        headerDotClass: 'bg-green-200',
        textClass: 'text-green-800',
    },
    completed: {
        label: 'Completed',
        badgeClass: '!bg-emerald-100 !text-emerald-900 !border-emerald-500',
        dotClass: '!bg-emerald-700',
        ringClass: 'ring-green-300',
        currentPanelClass: 'border-green-400 bg-green-100',
        mutedBadgeClass: '!bg-green-200 !text-green-900 !border-green-500',
        mutedDotClass: '!bg-emerald-800',
        headerBadgeClass: '!bg-green-700 !text-white !border-green-600',
        headerDotClass: 'bg-green-200',
        textClass: 'text-emerald-800',
    },
    rejected: {
        label: 'Rejected',
        badgeClass: '!bg-red-100 !text-red-800 !border-red-400',
        dotClass: '!bg-red-600',
        ringClass: 'ring-red-200',
        currentPanelClass: 'border-red-300 bg-red-100',
        mutedBadgeClass: '!bg-red-200 !text-red-800 !border-red-400',
        mutedDotClass: '!bg-red-700',
        headerBadgeClass: '!bg-red-600 !text-white !border-red-500',
        headerDotClass: 'bg-red-200',
        textClass: 'text-red-800',
    },
    cancelled: {
        label: 'Cancelled',
        badgeClass: '!bg-slate-200 !text-slate-800 !border-slate-400',
        dotClass: '!bg-slate-600',
        ringClass: 'ring-gray-200',
        currentPanelClass: 'border-gray-300 bg-gray-100',
        mutedBadgeClass: '!bg-gray-200 !text-gray-600 !border-gray-400',
        mutedDotClass: '!bg-gray-600',
        headerBadgeClass: '!bg-gray-600 !text-white !border-gray-500',
        headerDotClass: 'bg-gray-200',
        textClass: 'text-slate-700',
    },
};

export const isFinalAppointmentStatus = (status) => (
    FINAL_APPOINTMENT_STATUSES.includes(String(status || '').toLowerCase())
);

export const normalizeAppointmentStatus = (status) => String(status || 'pending').trim().toLowerCase();

export const getAppointmentStatusMeta = (status) => {
    const normalized = normalizeAppointmentStatus(status);
    return STATUS_META[normalized] || {
        label: normalized.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
        badgeClass: '!bg-gray-50 !text-gray-800 !border-gray-300',
        dotClass: 'bg-gray-400',
        ringClass: 'ring-gray-200',
        currentPanelClass: 'border-gray-300 bg-gray-100',
        mutedBadgeClass: '!bg-gray-200 !text-gray-600 !border-gray-300',
        mutedDotClass: '!bg-gray-600',
        headerBadgeClass: '!bg-gray-500 !text-white !border-gray-400',
        headerDotClass: 'bg-gray-200',
        textClass: 'text-gray-700',
    };
};

export const getAppointmentStatusLabel = (status) => getAppointmentStatusMeta(status).label;

export const getAppointmentStatusTextClass = (status) => (
    getAppointmentStatusMeta(status).textClass || 'text-gray-800'
);

export const getAppointmentStatusBadgeClass = (status) => getAppointmentStatusMeta(status).badgeClass;

export const getCurrentStatusPanelClass = (status) => (
    getAppointmentStatusMeta(status).currentPanelClass || 'border-gray-300 bg-gray-100'
);

export const getAppointmentStatusOption = (status) => (
    APPOINTMENT_STATUS_OPTIONS.find((option) => option.value === normalizeAppointmentStatus(status))
);

/** Whether a target status cannot be selected given the appointment's current status. */
export const isStatusTransitionDisabled = (currentStatus, targetStatus) => {
    const current = normalizeAppointmentStatus(currentStatus);
    const target = normalizeAppointmentStatus(targetStatus);

    if (current === target) {
        return true;
    }

    if (current === 'approved' && (target === 'pending' || target === 'in_progress')) {
        return true;
    }

    if (current === 'in_progress' && target === 'pending') {
        return true;
    }

    return false;
};
