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

          <div>
            <div class="grid grid-cols-3 gap-3" role="tablist" aria-label="Portal highlights">
              <button
                v-for="(feature, index) in features"
                :id="`feature-tab-${feature.id}`"
                :key="feature.id"
                type="button"
                role="tab"
                :aria-selected="activeFeature === feature.id"
                :aria-controls="`feature-panel-${feature.id}`"
                :tabindex="activeFeature === feature.id ? 0 : -1"
                class="feature-card group relative overflow-hidden rounded-2xl border p-4 text-left backdrop-blur-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#f4c542] focus-visible:ring-offset-2 focus-visible:ring-offset-[#003f30]"
                :class="activeFeature === feature.id ? 'feature-card--active border-white/45 bg-white/20' : 'border-white/20 bg-white/10'"
                @mouseenter="activeFeature = feature.id"
                @focus="activeFeature = feature.id"
                @click="activeFeature = feature.id"
                @keydown.left.prevent="selectAdjacentFeature(index, -1)"
                @keydown.right.prevent="selectAdjacentFeature(index, 1)"
              >
                <span class="feature-card__glow" aria-hidden="true"></span>
                <span class="relative flex items-start justify-between gap-3">
                  <span>
                    <span class="block text-2xl font-bold">{{ feature.title }}</span>
                    <span class="mt-1 block text-xs font-medium uppercase tracking-[0.18em] text-white/65">{{ feature.label }}</span>
                  </span>
                  <span class="feature-card__icon flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-[#f4c542]" v-html="feature.icon"></span>
                </span>
                <span class="feature-card__line absolute inset-x-4 bottom-0 h-0.5 origin-left rounded-full bg-[#f4c542]" aria-hidden="true"></span>
              </button>
            </div>

            <div class="mt-3 min-h-[52px] rounded-2xl border border-white/15 bg-black/10 px-4 py-3 backdrop-blur-md">
              <div
                v-for="feature in features"
                v-show="activeFeature === feature.id"
                :id="`feature-panel-${feature.id}`"
                :key="`${feature.id}-panel`"
                role="tabpanel"
                :aria-labelledby="`feature-tab-${feature.id}`"
                class="feature-detail flex items-center gap-3 text-sm leading-6 text-white/80"
              >
                <span class="h-2 w-2 shrink-0 rounded-full bg-[#f4c542] shadow-[0_0_0_5px_rgba(244,197,66,0.12)]"></span>
                {{ feature.description }}
              </div>
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

            <div v-if="errorMessage" class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">
              {{ errorMessage }}
            </div>

            <div class="rounded-2xl border border-[#005740]/15 bg-[#f3faf7] p-5 text-center">
              <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#005740] text-white shadow-[0_10px_24px_rgba(0,87,64,0.24)]">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 3 4.5 6.3v5.2c0 4.5 3.1 8.2 7.5 9.5 4.4-1.3 7.5-5 7.5-9.5V6.3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                  <path d="m8.8 12 2 2 4.4-4.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
              <h3 class="mt-4 text-lg font-bold text-slate-900">Secure Google access</h3>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                Sign in with the Google account linked to your UP Cebu room management account.
              </p>
            </div>

            <a
              :href="googleLoginUrl"
              class="group mt-5 flex w-full items-center justify-center gap-3 rounded-2xl bg-[#005740] px-4 py-4 text-sm font-bold uppercase tracking-[0.11em] text-white shadow-[0_14px_30px_rgba(0,87,64,0.32)] transition duration-200 hover:bg-[#003f30] hover:shadow-[0_18px_38px_rgba(0,87,64,0.38)] focus:outline-none focus:ring-4 focus:ring-[#005740]/25"
            >
              <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#fff" d="M21.6 12.23c0-.71-.06-1.39-.18-2.05H12v3.87h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.98-4.33 2.98-7.35Z"/>
                <path fill="#fff" fill-opacity=".9" d="M12 22c2.7 0 4.97-.9 6.62-2.42l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.59A10 10 0 0 0 12 22Z"/>
                <path fill="#fff" fill-opacity=".8" d="M6.39 13.9A6.02 6.02 0 0 1 6.08 12c0-.66.11-1.3.31-1.9V7.51H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.49l3.35-2.59Z"/>
                <path fill="#fff" fill-opacity=".7" d="M12 5.97c1.47 0 2.79.51 3.83 1.5l2.87-2.88A9.65 9.65 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.59C7.18 7.73 9.39 5.97 12 5.97Z"/>
              </svg>
              Continue with Google
            </a>

            <p class="mt-4 text-center text-xs leading-5 text-slate-500">
              You will be redirected to Google. Your password is never entered or stored in this system.
            </p>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';

const features = [
  {
    id: 'live',
    title: 'Live',
    label: 'Schedules',
    description: 'See room schedules and availability as they change across campus.',
    icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="m9 15 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
  {
    id: 'secure',
    title: 'Secure',
    label: 'Access',
    description: 'Use your authorized UP Cebu account to enter the management portal.',
    icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 5 6v5c0 4.2 2.9 7.7 7 9 4.1-1.3 7-4.8 7-9V6l-7-3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
  {
    id: 'fast',
    title: 'Fast',
    label: 'Reports',
    description: 'Review room use and generate clear reports with fewer steps.',
    icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 19V9M12 19V5M19 19v-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="m4 6 5-3 4 3 7-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
];

const activeFeature = ref(features[0].id);

const selectAdjacentFeature = async (currentIndex, direction) => {
  const nextIndex = (currentIndex + direction + features.length) % features.length;
  activeFeature.value = features[nextIndex].id;
  await nextTick();
  document.getElementById(`feature-tab-${features[nextIndex].id}`)?.focus();
};

const props = defineProps({
  googleLoginUrl: {
    type: String,
    default: '/auth/google',
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
});

const errorMessage = computed(() => props.errors.sso || '');
</script>

<style scoped>
.login-page {
  position: relative;
  overflow: hidden;
}

.login-shell {
  min-height: min(720px, calc(100vh - 4rem));
}

.feature-card {
  transform: translateY(0);
  transition: transform 220ms ease, background-color 220ms ease, border-color 220ms ease, box-shadow 220ms ease;
}

.feature-card:hover,
.feature-card--active {
  transform: translateY(-4px);
  box-shadow: 0 18px 34px rgba(0, 20, 16, 0.24);
}

.feature-card__glow {
  position: absolute;
  right: -2.5rem;
  top: -3rem;
  width: 8rem;
  height: 8rem;
  border-radius: 9999px;
  background: rgba(244, 197, 66, 0.2);
  filter: blur(26px);
  opacity: 0;
  transition: opacity 220ms ease;
}

.feature-card__icon {
  transform: rotate(0deg) scale(1);
  transition: transform 220ms ease, background-color 220ms ease;
}

.feature-card__line {
  transform: scaleX(0);
  transition: transform 220ms ease;
}

.feature-card:hover .feature-card__glow,
.feature-card--active .feature-card__glow {
  opacity: 1;
}

.feature-card:hover .feature-card__icon,
.feature-card--active .feature-card__icon {
  transform: rotate(-5deg) scale(1.08);
  background: rgba(255, 255, 255, 0.18);
}

.feature-card:hover .feature-card__line,
.feature-card--active .feature-card__line {
  transform: scaleX(1);
}

.feature-detail {
  animation: feature-detail-in 220ms ease both;
}

@keyframes feature-detail-in {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .feature-card,
  .feature-card__glow,
  .feature-card__icon,
  .feature-card__line {
    transition: none;
  }

  .feature-detail {
    animation: none;
  }
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
