<template>
  <div 
    class="min-h-screen flex items-center justify-center bg-cover bg-center bg-no-repeat"
    :style="{ backgroundImage: 'url(/image/upimage4.webp)' }"
  >
    <!-- Semi-transparent overlay for better readability -->
    <div class="absolute inset-0 bg-black bg-opacity-40"></div>
    
    <!-- Login Card -->
    <div class="relative z-10 bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md mx-4">
      <!-- Header -->
      <div class="text-center mb-8">
        <div class="flex justify-center mb-4">
          <img src="/image/uplogo.png" alt="UP Cebu Logo" class="h-20 w-20">
        </div>
        <h1 class="text-3xl font-bold text-[#7A0C23]">UP CEBU</h1>
        <p class="text-gray-600 mt-2">UPCEBU ROOM MANAGEMENT SYSTEM</p>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="submit" class="space-y-6">
        <div v-if="errors" class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg">
          {{ errors }}
        </div>

        <div>
          <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
            Username
          </label>
          <input
            id="username"
            v-model="form.username"
            type="text"
            required
            :class="[
              'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:border-transparent transition duration-200',
              'border-gray-300 focus:ring-[#7A0C23]'
            ]"
            placeholder="Enter your username or email"
          >
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
            Password
          </label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            required
            :class="[
              'w-full px-4 py-3 border rounded-lg focus:ring-2 focus:border-transparent transition duration-200',
              'border-gray-300 focus:ring-[#7A0C23]'
            ]"
            placeholder="Enter your password"
          >
        </div>

        <!-- Login Button -->
        <button
          type="submit"
          :disabled="processing"
          class="w-full bg-[#7A0C23] text-white py-3 px-4 rounded-lg font-semibold hover:bg-[#5a061a] transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span v-if="processing" class="flex items-center justify-center">
            <svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Logging in...
          </span>
          <span v-else>
            Sign In
          </span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const processing = ref(false);
const errors = ref('');

const form = reactive({
  username: '',
  password: '',
});

const submit = async () => {
  processing.value = true;
  errors.value = '';

  try {
    await router.post('/login', form, {
      onSuccess: () => {
        processing.value = false;
      },
      onError: (error) => {
        processing.value = false;
        if (error.username) {
          errors.value = error.username;
        } else {
          errors.value = 'Invalid username or password';
        }
      },
    });
  } catch (error) {
    processing.value = false;
    errors.value = 'Login failed. Please try again.';
  }
};
</script>

<style scoped>
/* Optional: Add a subtle overlay gradient effect */
.min-h-screen {
  position: relative;
}

/* Ensure text remains readable on any background */
.bg-white {
  backdrop-filter: blur(2px);
}
</style>