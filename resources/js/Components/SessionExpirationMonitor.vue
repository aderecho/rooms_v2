<template>
  <Teleport to="body">
    <Transition name="session-warning">
      <aside
        v-if="showWarning"
        class="session-expiration-warning"
        :class="{ 'is-urgent': remainingSeconds <= 60 }"
        role="alertdialog"
        aria-live="assertive"
        aria-labelledby="session-warning-title"
        aria-describedby="session-warning-description"
      >
        <div class="session-warning-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 8v5m0 3.5v.01M10.2 4.7 3.5 17a2 2 0 0 0 1.76 3h13.48a2 2 0 0 0 1.76-3L13.8 4.7a2.05 2.05 0 0 0-3.6 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>

        <div class="session-warning-content">
          <p class="session-warning-eyebrow">Security notice</p>
          <h2 id="session-warning-title">Your session expires soon</h2>
          <p id="session-warning-description">
            Save your work now. You will be signed out automatically at {{ expiryTimeLabel }}.
          </p>

          <div class="session-warning-countdown">
            <span>Time remaining</span>
            <strong>{{ remainingTimeLabel }}</strong>
          </div>

          <div class="session-warning-track" aria-hidden="true">
            <span :style="{ width: `${warningProgress}%` }"></span>
          </div>

          <button type="button" :disabled="loggingOut" @click="logoutNow">
            {{ loggingOut ? 'Signing out…' : 'Sign out now' }}
          </button>
        </div>
      </aside>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const remainingSeconds = ref(null);
const loggingOut = ref(false);
let timer = null;
let serverOffsetMilliseconds = 0;

const session = computed(() => page.props.auth?.session || null);
const warningSeconds = computed(() => Math.max(1, Number(session.value?.warningSeconds || 300)));
const showWarning = computed(() => (
  Boolean(page.props.auth?.user)
  && remainingSeconds.value !== null
  && remainingSeconds.value > 0
  && remainingSeconds.value <= warningSeconds.value
));

const remainingTimeLabel = computed(() => {
  const seconds = Math.max(0, remainingSeconds.value || 0);
  const minutes = Math.floor(seconds / 60);
  const remainder = seconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
});

const expiryTimeLabel = computed(() => {
  const expiry = Date.parse(session.value?.expiresAt || '');
  return Number.isFinite(expiry)
    ? new Date(expiry).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
    : 'the session limit';
});

const warningProgress = computed(() => Math.max(
  0,
  Math.min(100, ((remainingSeconds.value || 0) / warningSeconds.value) * 100),
));

const logoutNow = () => {
  if (loggingOut.value) return;

  loggingOut.value = true;
  router.post('/logout', {}, {
    replace: true,
    onFinish: () => {
      window.location.assign('/login');
    },
  });
};

const updateRemainingTime = () => {
  const expiry = Date.parse(session.value?.expiresAt || '');
  if (!Number.isFinite(expiry)) {
    remainingSeconds.value = null;
    return;
  }

  const serverNow = Date.now() + serverOffsetMilliseconds;
  remainingSeconds.value = Math.max(0, Math.ceil((expiry - serverNow) / 1000));

  if (remainingSeconds.value === 0) logoutNow();
};

const synchronizeSession = () => {
  const serverTime = Date.parse(session.value?.serverTime || '');
  serverOffsetMilliseconds = Number.isFinite(serverTime) ? serverTime - Date.now() : 0;
  loggingOut.value = false;
  updateRemainingTime();
};

const handleVisibilityChange = () => {
  if (document.visibilityState === 'visible') updateRemainingTime();
};

watch(session, synchronizeSession, { immediate: true, deep: true });

onMounted(() => {
  timer = window.setInterval(updateRemainingTime, 1000);
  document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
  if (timer) window.clearInterval(timer);
  document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<style scoped>
.session-expiration-warning {
  position: fixed;
  right: 1.25rem;
  bottom: 1.25rem;
  z-index: 1000;
  display: flex;
  width: min(25rem, calc(100vw - 2rem));
  gap: 0.9rem;
  border: 1px solid #f1cf7a;
  border-radius: 1.1rem;
  background: #fffdf6;
  padding: 1rem;
  color: #21352f;
  box-shadow: 0 24px 60px rgba(33, 53, 47, 0.22);
}

.session-expiration-warning.is-urgent {
  border-color: #ef9a9a;
  background: #fff8f8;
}

.session-warning-icon {
  display: grid;
  width: 2.75rem;
  height: 2.75rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.8rem;
  background: #fff0c2;
  color: #9a5a00;
}

.is-urgent .session-warning-icon {
  background: #fee2e2;
  color: #b42318;
}

.session-warning-icon svg { width: 1.4rem; height: 1.4rem; }
.session-warning-content { min-width: 0; flex: 1; }
.session-warning-eyebrow { color: #9a5a00; font-size: 0.65rem; font-weight: 900; letter-spacing: 0.12em; text-transform: uppercase; }
.session-warning-content h2 { margin-top: 0.15rem; font-size: 1rem; font-weight: 900; }
.session-warning-content > p:not(.session-warning-eyebrow) { margin-top: 0.35rem; color: #64748b; font-size: 0.76rem; line-height: 1.45; }
.session-warning-countdown { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-top: 0.8rem; }
.session-warning-countdown span { color: #64748b; font-size: 0.7rem; font-weight: 700; }
.session-warning-countdown strong { color: #005740; font-size: 1.25rem; font-variant-numeric: tabular-nums; }
.is-urgent .session-warning-countdown strong { color: #b42318; }
.session-warning-track { height: 0.35rem; overflow: hidden; margin-top: 0.45rem; border-radius: 999px; background: #f2e8cd; }
.session-warning-track span { display: block; height: 100%; border-radius: inherit; background: #d99b22; transition: width 1s linear; }
.is-urgent .session-warning-track span { background: #dc2626; }
.session-warning-content button { margin-top: 0.8rem; border: 1px solid #d8e5df; border-radius: 0.65rem; background: #fff; padding: 0.55rem 0.8rem; color: #005740; font-size: 0.72rem; font-weight: 800; transition: border-color .2s ease, background-color .2s ease; }
.session-warning-content button:hover,
.session-warning-content button:focus-visible { border-color: #005740; background: #edf8f4; outline: none; }
.session-warning-content button:disabled { cursor: wait; opacity: .65; }
.session-warning-enter-active,
.session-warning-leave-active { transition: opacity .2s ease, transform .2s ease; }
.session-warning-enter-from,
.session-warning-leave-to { opacity: 0; transform: translateY(.75rem); }

@media (max-width: 520px) {
  .session-expiration-warning { right: 1rem; bottom: 1rem; }
}

@media (prefers-reduced-motion: reduce) {
  .session-warning-enter-active,
  .session-warning-leave-active,
  .session-warning-track span { transition: none; }
}
</style>
