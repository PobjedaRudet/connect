<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps({
  workers: Array
})

const confirmDelete = (id) => {
  if (confirm('Da li ste sigurni da želite obrisati ovog radnika?')) {
    router.delete(`/employees/${id}`)
  }
}
</script>

<template>
  <Head title="Evidencija radnika" />

  <div class="max-w-6xl mx-auto py-6">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">Radnici</h1>
      <Link href="/employees/create" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        + Novi radnik
      </Link>
    </div>

    <table class="min-w-full bg-white shadow-md rounded border border-gray-200">
      <thead>
        <tr class="bg-gray-100 text-left text-sm text-gray-700">
          <th class="px-4 py-2">#</th>
          <th class="px-4 py-2">Ime</th>
          <th class="px-4 py-2">Prezime</th>
          <th class="px-4 py-2">Pozicija</th>
          <th class="px-4 py-2 text-right">Akcije</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(employee, index) in employees" :key="employee.id" class="border-t hover:bg-gray-50">
          <td class="px-4 py-2">{{ index + 1 }}</td>
          <td class="px-4 py-2">{{ worker.first_name }}</td>
          <td class="px-4 py-2">{{ worker.last_name }}</td>
          <td class="px-4 py-2">{{ worker.position }}</td>
          <td class="px-4 py-2 text-right space-x-2">
            <Link :href="`/workers/${employee.id}/edit`" class="text-blue-600 hover:underline">Uredi</Link>
            <button @click="confirmDelete(employee.id)" class="text-red-600 hover:underline">Obriši</button>
          </td>
        </tr>
        <tr v-if="employees.length === 0">
          <td colspan="5" class="text-center py-4 text-gray-500">Nema radnika u evidenciji.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
table {
  border-collapse: collapse;
}
</style>
