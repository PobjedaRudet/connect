<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  date: { type: String, required: true },
  tolerance_minutes: { type: Number, default: 15 },
  rows: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, flagged: 0, ok: 0 }) },
})

const date = ref(props.date)
const tolerance = ref(props.tolerance_minutes)
const search = ref('')
const onlyFlagged = ref(false)

watch(() => props.date, (value) => { date.value = value })
watch(() => props.tolerance_minutes, (value) => { tolerance.value = value })

const formatLabel = (value) => {
  if (!value) return '—'
  return new Intl.DateTimeFormat('bs-BA', { dateStyle: 'full' }).format(new Date(`${value}T00:00:00`))
}

const reload = () => {
  router.get(route('kapija.poredjenje'), {
    date: date.value,
    tolerance: tolerance.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

const filteredRows = computed(() => {
  const term = search.value.trim().toLowerCase()
  return (props.rows || []).filter((row) => {
    if (onlyFlagged.value && !row.flagged) return false
    if (!term) return true
    const text = [row?.full_name, row?.empID].filter(Boolean).join(' ').toLowerCase()
    return text.includes(term)
  })
})
</script>

<template>
  <AppLayout title="Kapija — Poređenje">
    <Head title="Kapija — Poređenje" />

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Kapija — Poređenje ulaza/izlaza</h1>
          <p class="text-sm text-gray-500">
            Poređenje vremena zabilježenog na kapiji (okretna vrata) i na terminalu kod objekta za
            {{ formatLabel(date) }}. Samo za ručni pregled — ništa se automatski ne blokira niti šalje.
          </p>
        </div>
        <a href="/kapija" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          Kapija — pregled uživo
        </a>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Ukupno radnika</div>
          <div class="mt-2 text-2xl font-semibold text-gray-900">{{ summary.total ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-red-700">Neslaganja</div>
          <div class="mt-2 text-2xl font-semibold text-red-900">{{ summary.flagged ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Uredno</div>
          <div class="mt-2 text-2xl font-semibold text-emerald-900">{{ summary.ok ?? 0 }}</div>
        </div>
      </div>

      <div class="flex items-end justify-between gap-4 flex-wrap rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-end gap-3 flex-wrap">
          <div>
            <label for="date-input" class="block text-xs font-medium text-gray-600 mb-1">Datum</label>
            <input id="date-input" v-model="date" type="date" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" @change="reload" />
          </div>
          <div>
            <label for="tolerance-input" class="block text-xs font-medium text-gray-600 mb-1">Tolerancija (min)</label>
            <input id="tolerance-input" v-model.number="tolerance" type="number" min="0" class="w-24 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" @change="reload" />
          </div>
          <div>
            <label for="search" class="block text-xs font-medium text-gray-600 mb-1">Pretraga radnika</label>
            <input id="search" v-model="search" type="text" placeholder="Ime, prezime ili broj" class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
          </div>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700 mb-1.5">
            <input v-model="onlyFlagged" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
            Samo neslaganja
          </label>
        </div>
        <div class="text-sm text-gray-600">
          Prikazano: <span class="font-semibold text-gray-900">{{ filteredRows.length }}</span>
        </div>
      </div>

      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">Radnik</th>
              <th class="px-4 py-3">Ulaz — kapija</th>
              <th class="px-4 py-3">Ulaz — objekat</th>
              <th class="px-4 py-3">Izlaz — kapija</th>
              <th class="px-4 py-3">Izlaz — objekat</th>
              <th class="px-4 py-3">Napomena</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in filteredRows" :key="row.employee_id" :class="row.flagged ? 'bg-red-50/60' : ''">
              <td class="px-4 py-3 text-sm text-gray-800">
                <div class="font-medium text-gray-900">{{ row.full_name }}</div>
                <div class="text-xs text-gray-500">#{{ row.empID }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.gate_in || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.building_in || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.gate_out || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ row.building_out || '—' }}</td>
              <td class="px-4 py-3 text-sm">
                <span v-if="!row.issues || !row.issues.length" class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-semibold">Uredno</span>
                <div v-else class="flex flex-col gap-1">
                  <span v-for="(issue, idx) in row.issues" :key="idx" class="inline-flex items-center rounded-full bg-red-100 text-red-800 px-2 py-0.5 text-xs font-semibold w-fit">
                    {{ issue }}
                  </span>
                </div>
              </td>
            </tr>
            <tr v-if="filteredRows.length === 0">
              <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Nema podataka za odabrani datum.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
