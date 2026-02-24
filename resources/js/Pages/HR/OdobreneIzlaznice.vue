<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'

const props = defineProps({
  passes: { type: Array, default: () => [] },
  selectedMonth: { type: String, default: '' },
  availableMonths: { type: Array, default: () => [] },
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

const formatDuration = (minutes) => {
  if (minutes === null || minutes === undefined) return '—'
  const total = Number(minutes)
  if (Number.isNaN(total)) return '—'
  const hrs = Math.floor(total / 60)
  const mins = Math.max(0, total - hrs * 60)
  const paddedMins = mins.toString().padStart(2, '0')
  return `${hrs}:${paddedMins} h`
}

const formatMonthLabel = (month) => {
  if (!month) return '—'
  return new Intl.DateTimeFormat('bs-BA', {
    month: 'long',
    year: 'numeric',
  }).format(new Date(`${month}-01T00:00:00`))
}

const onMonthChange = (event) => {
  const month = event.target.value
  router.get(route('passes.approved'), { month }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}
</script>

<template>
  <AppLayout title="Odobrene izlaznice">
    <Head title="Odobrene izlaznice" />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Odobrene izlaznice</h1>
          <p class="text-sm text-gray-500">Prikaz odobrenih izlaznica po mjesecima. Trenutno prikazano: {{ formatMonthLabel(selectedMonth) }}.</p>
        </div>
        <Link
          :href="route('passes.active')"
          class="inline-flex items-center px-3 py-2 bg-white text-gray-700 border border-gray-200 rounded-md text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
        >
          Nazad na odobravanje
        </Link>
      </div>

      <div class="flex items-center justify-between bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
        <div class="text-sm text-gray-700">
          Ukupno odobreno za {{ formatMonthLabel(selectedMonth) }}: <span class="font-semibold">{{ passes.length }}</span>
        </div>
        <div class="flex items-center space-x-2">
          <label for="month-select" class="text-sm text-gray-600">Mjesec</label>
          <select
            id="month-select"
            :value="selectedMonth"
            class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            @change="onMonthChange"
          >
            <option v-for="month in availableMonths" :key="month" :value="month">{{ formatMonthLabel(month) }}</option>
          </select>
        </div>
      </div>

      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">Radnik</th>
              <th class="px-4 py-3">Razlog</th>
              <th class="px-4 py-3">Tip</th>
              <th class="px-4 py-3">Izlazak</th>
              <th class="px-4 py-3">Povratak</th>
              <th class="px-4 py-3">Trajanje</th>
              <th class="px-4 py-3">Odobreno</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="passItem in passes" :key="passItem.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-800">{{ passItem.employee_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ passItem.reason || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700 capitalize">{{ passItem.type }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDateTime(passItem.start_time) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDateTime(passItem.end_time) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDuration(passItem.duration_minutes) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDateTime(passItem.approved_at) }}</td>
            </tr>
            <tr v-if="passes.length === 0">
              <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">Nema odobrenih izlaznica za odabrani mjesec.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
