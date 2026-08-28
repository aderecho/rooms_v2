import { reactive } from 'vue';

const dialog = reactive({
    open: false,
    title: '',
    message: '',
    variant: 'info',
    confirmLabel: 'OK',
    cancelLabel: 'Cancel',
    showCancel: false,
});

const queue = [];
let resolveCurrent = null;

const showNext = () => {
    if (dialog.open || queue.length === 0) return;

    const next = queue.shift();
    resolveCurrent = next.resolve;
    Object.assign(dialog, next.options, { open: true });
};

const openDialog = (options) => new Promise((resolve) => {
    queue.push({
        resolve,
        options: {
            title: options.title || 'Notice',
            message: options.message || '',
            variant: options.variant || 'info',
            confirmLabel: options.confirmLabel || 'OK',
            cancelLabel: options.cancelLabel || 'Cancel',
            showCancel: options.showCancel === true,
        },
    });
    showNext();
});

const closeDialog = (confirmed = false) => {
    if (!dialog.open) return;

    dialog.open = false;
    const resolve = resolveCurrent;
    resolveCurrent = null;
    resolve?.(confirmed);
    window.setTimeout(showNext, 0);
};

export const confirmDialog = (message, options = {}) => openDialog({
    ...options,
    message,
    showCancel: true,
    variant: options.variant || 'warning',
    confirmLabel: options.confirmLabel || 'Confirm',
});

export const notifyDialog = (message, options = {}) => openDialog({
    ...options,
    message,
    showCancel: false,
});

export const useAppDialog = () => ({ dialog, closeDialog });
