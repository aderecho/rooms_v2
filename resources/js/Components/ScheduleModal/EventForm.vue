<script setup>
import { defineProps } from 'vue';

defineProps({
    formData: Object
});
</script>

<template>
  <div>
    <h4 class="text-md font-semibold mb-2 text-gray-800">Event Details</h4>
    <label for="title" class="block text-sm font-medium text-gray-700">Title*</label>
    <input type="text" id="title" v-model="formData.title" required
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    <label for="organizer" class="block text-sm font-medium text-gray-700 mt-4">Organizer*</label>
    <input type="text" id="organizer" v-model="formData.organizer" required placeholder="Search"
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-red-500 focus:border-red-500">

    <label for="requester" class="block text-sm font-medium text-gray-700 mt-4">Requester*</label>
    <input
      id="requester"
      v-model="formData.requester"
      type="text"
      readonly
      required
      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 bg-gray-100 text-gray-700 focus:ring-red-500 focus:border-red-500"
    >

    </div>
</template>
