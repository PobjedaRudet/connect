<script setup>
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'

// Lista radnika dobijena iz Laravel kontrolera
const props = defineProps({
  radnici: Array
})
</script>

<template>
  <div class="min-h-screen bg-gray-100 p-8">
    <h1 class="text-2xl font-bold mb-6">PPZ Dashboard</h1>

    <table class="table-auto w-full bg-white shadow rounded">
      <thead class="bg-gray-200">
        <tr>
          <th class="px-4 py-2">Ime</th>
          <th class="px-4 py-2">Prezime</th>
          <th class="px-4 py-2">Akcije</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="radnik in radnici" :key="radnik.id" class="border-t">
          <td class="px-4 py-2">{{ radnik.firstName }}</td>
          <td class="px-4 py-2">{{ radnik.lastName }}</td>
          <td class="px-4 py-2">
            <Link
              :href="route('pregledi.index', { radnik: radnik.id })"
              class="text-blue-600 hover:underline"
            >
              Pregledi
            </Link>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
