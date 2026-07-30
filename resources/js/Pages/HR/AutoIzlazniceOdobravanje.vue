<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  pending:  { type: Array, default: () => [] },
  approved: { type: Array, default: () => [] },
})

const processingId = ref(null)
const showApproved = ref(false)

const formatDateTime = (value) => {
  if (!value) return '—'
  const date = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('bs-BA', {
    dateStyle: 'short',
    timeStyle: 'short',
    hour12: false,
  }).format(date)
}

const kindLabel = (row) => {
  if (row.kind === 'early') {
    const m = row.early_minutes ?? row.duration_minutes
    return `Prijevremeni odlazak${m != null ? ` — ${m} min` : ''}`
  }
  const m = row.late_minutes ?? row.duration_minutes
  return `Kašnjenje${m != null ? ` — ${m} min` : ''}`
}

const kindBadgeClass = (row) =>
  row.kind === 'early'
    ? 'bg-cyan-100 text-cyan-800'
    : 'bg-violet-100 text-violet-800'

const typeLabel = (type) => {
  if (type === 'privatni') return 'Privatna'
  if (type === 'službeni' || type === 'sluzbeni') return 'Službena'
  return type || '—'
}

const typeBadgeClass = (type) => {
  if (type === 'privatni') return 'bg-amber-100 text-amber-800'
  if (type === 'službeni' || type === 'sluzbeni') return 'bg-sky-100 text-sky-800'
  return 'bg-gray-100 text-gray-700'
}

const approve = (row, type) => {
  if (processingId.value) return
  processingId.value = row.id
  router.post(route('hr.izlaznice.auto.approve', { pass: row.id }), { type }, {
    preserveScroll: true,
    onFinish: () => { processingId.value = null },
  })
}
</script>

<template>
  <AppLayout title="Odobravanje auto-izlaznica">
    <Head title="Odobravanje auto-izlaznica" />
    <HrNav />

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Odobravanje auto-izlaznica</h1>
        <p class="text-sm text-gray-500">
          Automatski kreirane izlaznice za kašnjenje i prijevremeni odlazak koje čekaju vašu odluku.
          Odaberite da li je razlog privatne ili službene prirode.
        </p>
      </div>

      <!-- Neodobrene -->
      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 border-b bg-amber-50/60 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Čekaju odobrenje</h2>
            <p class="text-sm text-gray-500">Ukupno: {{ pending.length }}</p>
          </div>
        </div>

        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">Radnik</th>
              <th class="px-4 py-3">Vrsta</th>
              <th class="px-4 py-3">Period</th>
              <th class="px-4 py-3">Trajanje</th>
              <th class="px-4 py-3 text-right">Odluka</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in pending" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm">
                <div class="font-medium text-gray-800">{{ row.full_name }}</div>
                <div class="text-xs text-gray-400">#{{ row.empID }} · izlaznica #{{ row.id }}</div>
              </td>
              <td class="px-4 py-3 text-sm">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="kindBadgeClass(row)">
                  {{ kindLabel(row) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                <div>{{ formatDateTime(row.start_time) }}</div>
                <div class="text-xs text-gray-500">do {{ formatDateTime(row.end_time) }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                {{ row.duration_minutes != null ? `${row.duration_minutes} min` : '—' }}
              </td>
              <td class="px-4 py-3 text-sm">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    :disabled="processingId === row.id"
                    class="inline-flex items-center rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:opacity-50"
                    @click="approve(row, 'privatni')"
                  >
                    Privatna
                  </button>
                  <button
                    type="button"
                    :disabled="processingId === row.id"
                    class="inline-flex items-center rounded-md bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-sky-700 disabled:opacity-50"
                    @click="approve(row, 'službeni')"
                  >
                    Službena
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="pending.length === 0">
              <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
                Nema izlaznica koje čekaju odobrenje.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Odobrene (istorija) -->
      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <button
          type="button"
          class="w-full px-6 py-4 border-b flex items-center justify-between text-left hover:bg-gray-50"
          @click="showApproved = !showApproved"
        >
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Odobrene (zadnjih 30 dana)</h2>
            <p class="text-sm text-gray-500">Ukupno: {{ approved.length }}</p>
          </div>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-5 w-5 text-gray-400 transition-transform"
            :class="showApproved ? 'rotate-180' : ''"
            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <table v-if="showApproved" class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">Radnik</th>
              <th class="px-4 py-3">Vrsta</th>
              <th class="px-4 py-3">Period</th>
              <th class="px-4 py-3">Trajanje</th>
              <th class="px-4 py-3">Tip</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in approved" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm">
                <div class="font-medium text-gray-800">{{ row.full_name }}</div>
                <div class="text-xs text-gray-400">#{{ row.empID }} · izlaznica #{{ row.id }}</div>
              </td>
              <td class="px-4 py-3 text-sm">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="kindBadgeClass(row)">
                  {{ kindLabel(row) }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                <div>{{ formatDateTime(row.start_time) }}</div>
                <div class="text-xs text-gray-500">do {{ formatDateTime(row.end_time) }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                {{ row.duration_minutes != null ? `${row.duration_minutes} min` : '—' }}
              </td>
              <td class="px-4 py-3 text-sm">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="typeBadgeClass(row.type)">
                  {{ typeLabel(row.type) }}
                </span>
              </td>
            </tr>
            <tr v-if="approved.length === 0">
              <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
                Nema odobrenih auto-izlaznica u zadnjih 30 dana.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AppLayout>
</template>
