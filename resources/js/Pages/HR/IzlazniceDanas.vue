<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  passes:  { type: Array,  default: () => [] },
  summary: { type: Object, default: () => ({}) },
  today:   { type: String, default: '' },
})

const search = ref('')
const filterStatus = ref('all')   // all | open | closed
const filterType   = ref('all')   // all | privatni | službeni

const formatDateTime = (value) => {
  if (!value) return '—'
  const date = new Date(value.replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('bs-BA', {
    timeStyle: 'short',
    hour12: false,
  }).format(date)
}

const formatDuration = (minutes) => {
  if (minutes === null || minutes === undefined) return '—'
  const total = Number(minutes)
  if (Number.isNaN(total)) return '—'
  const hrs  = Math.floor(total / 60)
  const mins = (total % 60).toString().padStart(2, '0')
  return `${hrs}:${mins} h`
}

const typeLabel = (type) => {
  if (type === 'privatni')  return 'Privatna'
  if (type === 'službeni' || type === 'sluzbeni') return 'Službena'
  return type || '—'
}

const typeBadgeClass = (type) => {
  if (type === 'privatni')  return 'bg-amber-100 text-amber-800'
  if (type === 'službeni' || type === 'sluzbeni') return 'bg-sky-100 text-sky-800'
  return 'bg-gray-100 text-gray-700'
}

const statusBadgeClass = (status, approved) => {
  if (status === 'open')   return 'bg-green-100 text-green-800'
  if (approved)             return 'bg-indigo-100 text-indigo-800'
  return 'bg-gray-100 text-gray-600'
}

const statusLabel = (status, approved) => {
  if (status === 'open')   return 'Aktivna'
  if (approved)             return 'Odobrena'
  return 'Zatvorena'
}

const filteredPasses = computed(() => {
  let list = props.passes || []

  const term = search.value.trim().toLowerCase()
  if (term) {
    list = list.filter((p) =>
      [p.full_name, p.empID, p.department, p.reason]
        .filter(Boolean).join(' ').toLowerCase().includes(term)
    )
  }

  if (filterStatus.value !== 'all') {
    if (filterStatus.value === 'open') {
      list = list.filter((p) => p.status === 'open')
    } else {
      list = list.filter((p) => p.status === 'closed')
    }
  }

  if (filterType.value !== 'all') {
    list = list.filter((p) => p.type === filterType.value)
  }

  return list
})

const refresh = () => {
  router.reload({ preserveScroll: true })
}

const formatTodayLabel = () => {
  if (!props.today) return ''
  return new Intl.DateTimeFormat('bs-BA', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }).format(new Date(`${props.today}T00:00:00`))
}
</script>

<template>
  <AppLayout title="Današnje izlaznice">
    <Head title="Današnje izlaznice" />
    <HrNav />

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Naslov -->
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Današnje izlaznice</h1>
          <p class="text-sm text-gray-500 capitalize">{{ formatTodayLabel() }}</p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition"
          @click="refresh"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Osvježi
        </button>
      </div>

      <!-- Sumarni kartice -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm text-center">
          <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Ukupno</div>
          <div class="mt-1 text-2xl font-bold text-gray-900">{{ summary.total ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-green-200 bg-green-50 p-3 shadow-sm text-center">
          <div class="text-xs font-semibold uppercase tracking-wide text-green-600">Aktivne</div>
          <div class="mt-1 text-2xl font-bold text-green-800">{{ summary.open ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 shadow-sm text-center">
          <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Zatvorene</div>
          <div class="mt-1 text-2xl font-bold text-gray-700">{{ summary.closed ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 shadow-sm text-center">
          <div class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Odobrene</div>
          <div class="mt-1 text-2xl font-bold text-indigo-800">{{ summary.approved ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 shadow-sm text-center">
          <div class="text-xs font-semibold uppercase tracking-wide text-amber-600">Privatne</div>
          <div class="mt-1 text-2xl font-bold text-amber-800">{{ summary.private ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 shadow-sm text-center">
          <div class="text-xs font-semibold uppercase tracking-wide text-sky-600">Službene</div>
          <div class="mt-1 text-2xl font-bold text-sky-800">{{ summary.business ?? 0 }}</div>
        </div>
      </div>

      <!-- Filteri -->
      <div class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Pretraga</label>
          <input
            v-model="search"
            type="text"
            placeholder="Ime, odjel, razlog…"
            class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
          <select
            v-model="filterStatus"
            class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
          >
            <option value="all">Sve</option>
            <option value="open">Aktivne</option>
            <option value="closed">Zatvorene</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Tip</label>
          <select
            v-model="filterType"
            class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
          >
            <option value="all">Sve</option>
            <option value="privatni">Privatna</option>
            <option value="službeni">Službena</option>
          </select>
        </div>
        <div class="ml-auto text-sm text-gray-500 self-end pb-0.5">
          Prikazano: <span class="font-semibold text-gray-800">{{ filteredPasses.length }}</span>
        </div>
      </div>

      <!-- Tabela -->
      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">Radnik</th>
              <th class="px-4 py-3">Odjel</th>
              <th class="px-4 py-3">Tip</th>
              <th class="px-4 py-3">Izlazak</th>
              <th class="px-4 py-3">Povratak</th>
              <th class="px-4 py-3">Trajanje</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Napomena</th>
              <th class="px-4 py-3">Razlog</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr
              v-for="pass in filteredPasses"
              :key="pass.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <!-- Radnik -->
              <td class="px-4 py-3 text-sm">
                <div class="font-medium text-gray-800">{{ pass.full_name }}</div>
                <div class="text-xs text-gray-400">#{{ pass.empID }}</div>
              </td>

              <!-- Odjel -->
              <td class="px-4 py-3 text-sm text-gray-600">{{ pass.department }}</td>

              <!-- Tip -->
              <td class="px-4 py-3 text-sm">
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="typeBadgeClass(pass.type)"
                >
                  {{ typeLabel(pass.type) }}
                </span>
              </td>

              <!-- Izlazak -->
              <td class="px-4 py-3 text-sm text-gray-700 font-mono whitespace-nowrap">
                {{ formatDateTime(pass.start_time) }}
              </td>

              <!-- Povratak -->
              <td class="px-4 py-3 text-sm text-gray-700 font-mono whitespace-nowrap">
                <span v-if="pass.end_time">{{ formatDateTime(pass.end_time) }}</span>
                <span v-else class="inline-flex items-center gap-1 text-green-600 font-semibold text-xs">
                  <span class="inline-block h-2 w-2 rounded-full bg-green-500 animate-pulse" />
                  Vani
                </span>
              </td>

              <!-- Trajanje -->
              <td class="px-4 py-3 text-sm text-gray-800 whitespace-nowrap">
                {{ pass.duration_display ?? '—' }}
              </td>

              <!-- Status -->
              <td class="px-4 py-3 text-sm">
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="statusBadgeClass(pass.status, pass.approved)"
                >
                  {{ statusLabel(pass.status, pass.approved) }}
                </span>
              </td>

              <!-- Napomena (kašnjenje / prijevremeni) -->
              <td class="px-4 py-3 text-xs">
                <span v-if="pass.late_pass" class="inline-flex rounded bg-violet-100 text-violet-800 px-1.5 py-0.5 font-medium">
                  Kašnjenje{{ pass.late_minutes != null ? ` ${pass.late_minutes} min` : '' }}
                </span>
                <span v-else-if="pass.early_departure" class="inline-flex rounded bg-cyan-100 text-cyan-800 px-1.5 py-0.5 font-medium">
                  Raniji odlazak{{ pass.early_minutes != null ? ` ${pass.early_minutes} min` : '' }}
                </span>
                <span v-else class="text-gray-400">—</span>
              </td>

              <!-- Razlog -->
              <td class="px-4 py-3 text-sm text-gray-600 max-w-xs">
                {{ pass.reason || '—' }}
              </td>
            </tr>

            <tr v-if="filteredPasses.length === 0">
              <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-500">
                <template v-if="(passes || []).length === 0">
                  Nema izlaznica za danas.
                </template>
                <template v-else>
                  Nijedna izlaznica ne odgovara zadanim filterima.
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AppLayout>
</template>
