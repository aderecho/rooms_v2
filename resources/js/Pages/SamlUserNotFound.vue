<script setup>
defineProps({
  email: {
    type: String,
    default: '',
  },
  reason: {
    type: String,
    default: 'not_found',
  },
});
</script>

<template>
  <div
    class="relative min-h-screen overflow-hidden bg-cover bg-center bg-no-repeat px-4 py-8 sm:px-6 lg:px-8"
    :style="{ backgroundImage: 'url(/image/upimage4.webp)' }"
  >
    <div class="absolute inset-0 bg-slate-950/50"></div>
    <div class="absolute inset-0 bg-gradient-to-br from-[#002d24]/90 via-[#005740]/55 to-slate-950/50"></div>

    <main class="relative z-10 mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-4xl items-center justify-center">
      <section class="w-full rounded-[28px] border border-white/20 bg-white p-6 shadow-[0_28px_90px_rgba(0,31,25,0.44)] sm:p-10">
        <div class="mx-auto max-w-2xl text-center">
          <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-[1.75rem] border border-[#005740]/10 bg-[#f6faf8] shadow-[0_18px_48px_rgba(0,87,64,0.14)]">
            <img src="/image/uplogo.png" alt="UP Cebu Logo" class="h-20 w-20 object-contain">
          </div>

          <div class="mt-8 inline-flex items-center rounded-full bg-amber-100 px-4 py-2 text-sm font-bold text-amber-900">
            SAML access not enabled
          </div>

          <h1 class="mt-5 text-3xl font-bold leading-tight text-slate-950 sm:text-4xl">
            User not found in Rooms
          </h1>

          <p class="mt-4 text-base leading-7 text-slate-600">
            Your OnePortal sign-in was received, but this account is not registered for the UP Cebu Rooms system.
            Please contact your Rooms Administrator to request access.
          </p>

          <div v-if="email" class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left">
            <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Signed-in email</div>
            <div class="mt-1 break-all text-sm font-semibold text-slate-900">{{ email }}</div>
          </div>

          <div v-if="reason === 'inactive'" class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
            This Rooms account exists but is not active. Contact your Rooms Administrator to reactivate access.
          </div>

          <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <a
              href="/login"
              class="inline-flex items-center justify-center rounded-2xl bg-[#005740] px-5 py-3 text-sm font-bold uppercase tracking-[0.12em] text-white shadow-[0_14px_30px_rgba(0,87,64,0.28)] transition hover:bg-[#003f30]"
            >
              Back to Login
            </a>
            <a
              href="/"
              class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
            >
              Go to Rooms
            </a>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
