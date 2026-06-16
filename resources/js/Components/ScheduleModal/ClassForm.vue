<script setup>
import { defineProps } from 'vue';

// The formData prop is the reactive object from AppointmentModal.vue
const props = defineProps({
    formData: Object
});
</script>

<template>
  <div>
    <h4 class="text-md font-semibold mb-2 text-gray-800">Class Details</h4>
    <label for="subject" class="block text-sm font-medium text-gray-700">Subject*</label>
    <input type="text" id="subject" v-model="formData.subject" required
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    <label for="section" class="block text-sm font-medium text-gray-700 mt-4">Section*</label>
    <input type="text" id="section" v-model="formData.section" required
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    <label for="faculty" class="block text-sm font-medium text-gray-700 mt-4">Faculty*</label>
    <input type="text" id="faculty" v-model="formData.faculty" required placeholder="Search"
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    <label for="students" class="block text-sm font-medium text-gray-700 mt-4">Number of Students*</label>
    <input type="number" id="students" v-model.number="formData.numberOfStudents" required min="1"
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">
  </div>
</template>
