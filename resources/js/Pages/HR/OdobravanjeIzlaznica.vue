<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({
  passes: { type: Array, default: () => [] },
  workdayEndTime: { type: String, default: '15:00' },
})

const formatDateTime = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  return new Intl.DateTimeFormat('bs-BA', {
    dateStyle: 'short',
    timeStyle: 'short',
    hour12: false,
  }).format(date)
}

const updateType = (passId, type) => {
  router.patch(route('passes.updateType', passId), { type }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const confirmPass = (passId, type) => {
  router.post(route('passes.confirm', passId), { type }, {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppLayout title="Odobravanje izlaznica">
    <Head title="Odobravanje izlaznica" />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Odobravanje izlaznica</h1>
          <p class="text-sm text-gray-500">Privatne izlaznice računaju trajanje do kraja radnog vremena ({{ workdayEndTime }}).</p>
        </div>
      </div>

      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">Radnik</th>
              <th class="px-4 py-3">Razlog</th>
              <th class="px-4 py-3">Početak</th>
              <th class="px-4 py-3">Povratak</th>
              <th class="px-4 py-3">Odobrena</th>
              <th class="px-4 py-3">Tip</th>
              <th class="px-4 py-3 text-right">Akcije</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="passItem in passes" :key="passItem.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-800">{{ passItem.employee_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ passItem.reason || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDateTime(passItem.start_time) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDateTime(passItem.end_time) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <span :class="passItem.approved ? 'text-emerald-700' : 'text-amber-700'">
                  {{ passItem.approved ? 'Da' : 'Ne' }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <select
                  :value="passItem.type"
                  class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                  @change="(e) => updateType(passItem.id, e.target.value)"
                >
                  <option value="privatni">Privatna</option>
                  <option value="službeni">Službena</option>
                </select>
              </td>
              <td class="px-4 py-3 text-sm text-right space-x-2">
                <button
                  class="inline-flex items-center px-3 py-2 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:opacity-50"
                  :disabled="passItem.approved"
                  @click="confirmPass(passItem.id, passItem.type)"
                >
                  Odobri
                </button>
              </td>
            </tr>
            <tr v-if="passes.length === 0">
              <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Trenutno nema aktivnih izlaznica.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
