<template>
  <div 
    class="login-page min-h-screen bg-cover bg-center bg-no-repeat px-4 py-8 sm:px-6 lg:px-8"
    :style="{ backgroundImage: 'url(/image/upimage4.webp)' }"
  >
    <div class="absolute inset-0 bg-slate-950/40"></div>
    <div class="absolute inset-0 bg-gradient-to-br from-[#002d24]/88 via-[#005740]/50 to-slate-950/40"></div>
    <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-[#001f19]/75 to-transparent"></div>

    <main class="relative z-10 mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-6xl items-center justify-center">
      <section class="login-shell grid w-full min-w-0 overflow-hidden rounded-[28px] border border-white/20 bg-white/10 shadow-[0_28px_90px_rgba(0,31,25,0.44)] backdrop-blur-xl lg:grid-cols-[1.05fr_0.95fr]">
        <div class="hidden min-h-[620px] flex-col justify-between p-10 text-white lg:flex">
          <div>
            <div class="inline-flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold shadow-lg backdrop-blur-md">
              <span class="h-2.5 w-2.5 rounded-full bg-[#f4c542] shadow-[0_0_0_4px_rgba(244,197,66,0.16)]"></span>
              University of the Philippines Cebu
            </div>
          </div>

          <div class="max-w-xl">
            <h1 class="text-5xl font-bold leading-tight tracking-normal">
              Room management for a smarter campus.
            </h1>
            <p class="mt-5 max-w-lg text-base leading-7 text-white/80">
              Manage rooms, schedules, equipment, and campus spaces from one secure administrative portal.
            </p>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-md">
              <p class="text-2xl font-bold">Live</p>
              <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-white/65">Schedules</p>
            </div>
            <div class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-md">
              <p class="text-2xl font-bold">Secure</p>
              <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-white/65">Access</p>
            </div>
            <div class="rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-md">
              <p class="text-2xl font-bold">Fast</p>
              <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-white/65">Reports</p>
            </div>
          </div>
        </div>

        <div class="login-panel flex min-w-0 items-center justify-center bg-white px-5 py-8 shadow-2xl sm:px-10 lg:px-12">
          <div class="w-full min-w-0 max-w-md">
            <div class="mb-8 text-center">
              <div class="mx-auto mb-6 flex h-32 w-32 items-center justify-center rounded-[2rem] border border-[#005740]/10 bg-white shadow-[0_18px_48px_rgba(0,87,64,0.18)] sm:h-36 sm:w-36">
                <img src="/image/uplogo.png" alt="UP Cebu Logo" class="h-28 w-28 object-contain sm:h-32 sm:w-32">
              </div>
              <h2 class="text-3xl font-bold text-[#005740]">Welcome back</h2>
              <p class="login-subtitle mt-2 text-xs font-medium uppercase tracking-[0.14em] text-gray-500 sm:text-sm sm:tracking-[0.18em]">
                UPCEBU ROOM MANAGEMENT SYSTEM
              </p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
              <div v-if="errorMessage" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ errorMessage }}
              </div>

              <div>
                <label for="username" class="mb-2 block text-sm font-semibold text-gray-800">
                  Username
                </label>
                <div class="relative">
                  <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="1.8"/>
                  </svg>
                  <input
                    id="username"
                    v-model="form.username"
                    type="text"
                    required
                    autocomplete="username"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3.5 pl-12 pr-4 text-slate-900 shadow-inner outline-none transition duration-200 placeholder:text-slate-400 focus:border-[#005740] focus:bg-white focus:ring-4 focus:ring-[#005740]/10"
                    placeholder="Enter your username or email"
                  >
                </div>
              </div>

              <div>
                <label for="password" class="mb-2 block text-sm font-semibold text-gray-800">
                  Password
                </label>
                <div class="relative">
                  <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M6.5 11h11A1.5 1.5 0 0 1 19 12.5v6A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-6A1.5 1.5 0 0 1 6.5 11Z" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M12 15v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  </svg>
                  <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-2xl border border-slate-200 bg-slate-50/80 py-3.5 pl-12 pr-4 text-slate-900 shadow-inner outline-none transition duration-200 placeholder:text-slate-400 focus:border-[#005740] focus:bg-white focus:ring-4 focus:ring-[#005740]/10"
                    placeholder="Enter your password"
                  >
                </div>
              </div>

              <button
                type="submit"
                :disabled="processing"
                class="group mt-2 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#005740] px-4 py-3.5 text-sm font-bold uppercase tracking-[0.14em] text-white shadow-[0_14px_30px_rgba(0,87,64,0.32)] transition duration-200 hover:bg-[#003f30] hover:shadow-[0_18px_38px_rgba(0,87,64,0.38)] focus:outline-none focus:ring-4 focus:ring-[#005740]/25 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <span v-if="processing" class="flex items-center justify-center">
                  <svg class="mr-2 h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Logging in...
                </span>
                <span v-else>Sign In</span>
              </button>
            </form>

            <div class="my-6 flex items-center gap-4" aria-hidden="true">
              <div class="h-px flex-1 bg-gray-200"></div>
              <span class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">or</span>
              <div class="h-px flex-1 bg-gray-200"></div>
            </div>

            <a
              :href="route('auth.google.redirect')"
              class="flex w-full items-center justify-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3.5 text-sm font-bold text-gray-700 shadow-sm transition duration-200 hover:border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-200"
            >
              <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.39-.18-2.05H12v3.87h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.98-4.33 2.98-7.35Z"/>
                <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.62-2.42l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.59A10 10 0 0 0 12 22Z"/>
                <path fill="#FBBC05" d="M6.39 13.9A6.02 6.02 0 0 1 6.08 12c0-.66.11-1.3.31-1.9V7.51H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.49l3.35-2.59Z"/>
                <path fill="#EA4335" d="M12 5.97c1.47 0 2.79.51 3.83 1.5l2.87-2.88A9.65 9.65 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.59C7.18 7.73 9.39 5.97 12 5.97Z"/>
              </svg>
              Continue with Google
            </a>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  errors: {
    type: Object,
    default: () => ({}),
  },
});

const processing = ref(false);
const localError = ref('');
const errorMessage = computed(() => localError.value || props.errors.sso || '');

const form = reactive({
  username: '',
  password: '',
});

const submit = async () => {
  processing.value = true;
  localError.value = '';

  try {
    await router.post('/login', form, {
      onSuccess: () => {
        processing.value = false;
      },
      onError: (error) => {
        processing.value = false;
        if (error.username) {
          localError.value = error.username;
        } else {
          localError.value = 'Invalid username or password';
        }
      },
    });
  } catch (error) {
    processing.value = false;
    localError.value = 'Login failed. Please try again.';
  }
};
</script>

<style scoped>
.login-page {
  position: relative;
  overflow: hidden;
}

.login-shell {
  min-height: min(720px, calc(100vh - 4rem));
}

@media (max-width: 1023px) {
  .login-shell {
    width: calc(100vw - 2rem);
    max-width: 520px;
    min-height: auto;
  }

  .login-panel {
    width: 100%;
  }

  .login-subtitle {
    overflow-wrap: anywhere;
  }
}
</style>
