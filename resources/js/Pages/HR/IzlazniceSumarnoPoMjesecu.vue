<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import DialogModal from '@/Components/DialogModal.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  selectedMonth: { type: String, required: true },
  availableMonths: { type: Array, default: () => [] },
  summary: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({}) },
  selectedEmployee: { type: Object, default: null },
  employeePasses: { type: Array, default: () => [] },
})

const month = ref(props.selectedMonth)
const search = ref('')

watch(
  () => props.selectedMonth,
  (value) => {
    month.value = value
  }
)

const showEmployeeModal = computed(() => !!props.selectedEmployee)

const formatMonthLabel = (value) => {
  if (!value) return '—'
  return new Intl.DateTimeFormat('bs-BA', {
    month: 'long',
    year: 'numeric',
  }).format(new Date(`${value}-01T00:00:00`))
}

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

const filteredSummary = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return props.summary || []

  return (props.summary || []).filter((row) => {
    const text = [row?.full_name, row?.empID].filter(Boolean).join(' ').toLowerCase()
    return text.includes(term)
  })
})

const onMonthChange = (event) => {
  router.get(route('hr.izlaznice.sumarno'), { month: event.target.value }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

const openEmployeePasses = (row) => {
  router.get(route('hr.izlaznice.sumarno'), {
    month: month.value || props.selectedMonth,
    employee_id: row.employee_id,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

const closeEmployeeModal = () => {
  router.get(route('hr.izlaznice.sumarno'), {
    month: month.value || props.selectedMonth,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}
</script>

<template>
  <AppLayout title="Izlaznice sumarno po mjesecu">
    <Head title="Izlaznice sumarno po mjesecu" />
    <HrNav />

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Izlaznice sumarno po mjesecu</h1>
        <p class="text-sm text-gray-500">
          Zbir trajanja privatnih i službenih izlaznica po radniku za {{ formatMonthLabel(selectedMonth) }}.
          Kliknite na radnika da vidite pojedinačne izlaznice.
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Radnici</div>
          <div class="mt-2 text-2xl font-semibold text-gray-900">{{ totals.employees_count ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-amber-700">Privatne</div>
          <div class="mt-2 text-2xl font-semibold text-amber-900">{{ totals.private_display ?? '0:00' }}</div>
          <div class="mt-1 text-xs text-amber-700">Broj izlaznica: {{ totals.private_count ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-sky-700">Službene</div>
          <div class="mt-2 text-2xl font-semibold text-sky-900">{{ totals.business_display ?? '0:00' }}</div>
          <div class="mt-1 text-xs text-sky-700">Broj izlaznica: {{ totals.business_count ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Ukupno</div>
          <div class="mt-2 text-2xl font-semibold text-emerald-900">{{ totals.total_display ?? '0:00' }}</div>
        </div>
      </div>

      <div class="flex items-end justify-between gap-4 flex-wrap rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-end gap-3 flex-wrap">
          <div>
            <label for="month-select" class="block text-xs font-medium text-gray-600 mb-1">Mjesec</label>
            <select
              id="month-select"
              :value="month"
              class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
              @change="onMonthChange"
            >
              <option v-for="item in availableMonths" :key="item" :value="item">{{ formatMonthLabel(item) }}</option>
            </select>
          </div>

          <div>
            <label for="search" class="block text-xs font-medium text-gray-600 mb-1">Pretraga radnika</label>
            <input
              id="search"
              v-model="search"
              type="text"
              placeholder="Ime, prezime ili broj"
              class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            />
          </div>
        </div>

        <div class="text-sm text-gray-600">
          Prikazani radnici: <span class="font-semibold text-gray-900">{{ filteredSummary.length }}</span>
        </div>
      </div>

      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">Radnik</th>
              <th class="px-4 py-3">Privatne izlaznice</th>
              <th class="px-4 py-3">Službene izlaznice</th>
              <th class="px-4 py-3">Ukupno</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr
              v-for="row in filteredSummary"
              :key="row.employee_id"
              class="hover:bg-indigo-50/60 cursor-pointer transition-colors"
              title="Kliknite za detalje izlaznica"
              @click="openEmployeePasses(row)"
            >
              <td class="px-4 py-3 text-sm text-gray-800">
                <div class="font-medium text-indigo-700 hover:underline">{{ row.full_name }}</div>
                <div class="text-xs text-gray-500">#{{ row.empID }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-amber-900">
                <div class="font-medium">{{ row.private_display }}</div>
                <div class="text-xs text-amber-700">{{ row.private_count }} izlaznica</div>
              </td>
              <td class="px-4 py-3 text-sm text-sky-900">
                <div class="font-medium">{{ row.business_display }}</div>
                <div class="text-xs text-sky-700">{{ row.business_count }} izlaznica</div>
              </td>
              <td class="px-4 py-3 text-sm font-semibold text-emerald-800">{{ row.total_display }}</td>
            </tr>
            <tr v-if="filteredSummary.length === 0">
              <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">Nema zatvorenih i odobrenih izlaznica za odabrani mjesec.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <DialogModal :show="showEmployeeModal" max-width="4xl" @close="closeEmployeeModal">
      <template #title>
        Izlaznice — {{ selectedEmployee?.full_name || 'Radnik' }}
        <span class="ml-2 text-sm font-normal text-gray-500">
          (#{{ selectedEmployee?.empID }}) · {{ formatMonthLabel(selectedMonth) }}
        </span>
      </template>

      <template #content>
        <div class="overflow-x-auto -mx-1">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                <th class="px-3 py-2">Datum / vrijeme</th>
                <th class="px-3 py-2">Tip</th>
                <th class="px-3 py-2">Trajanje</th>
                <th class="px-3 py-2">Napomena</th>
                <th class="px-3 py-2">Razlog</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="pass in employeePasses" :key="pass.id" class="hover:bg-gray-50">
                <td class="px-3 py-2 text-gray-800 whitespace-nowrap">
                  <div>{{ formatDateTime(pass.start_time) }}</div>
                  <div class="text-xs text-gray-500">do {{ formatDateTime(pass.end_time) }}</div>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="typeBadgeClass(pass.type)">
                    {{ typeLabel(pass.type) }}
                  </span>
                </td>
                <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">
                  {{ pass.duration_display }}
                </td>
                <td class="px-3 py-2 text-xs text-gray-600">
                  <span v-if="pass.late_pass" class="inline-flex rounded bg-violet-100 text-violet-800 px-1.5 py-0.5 font-medium">
                    Kašnjenje{{ pass.late_minutes != null ? ` ${pass.late_minutes} min` : '' }}
                  </span>
                  <span v-else-if="pass.early_departure" class="inline-flex rounded bg-cyan-100 text-cyan-800 px-1.5 py-0.5 font-medium">
                    Prijevremeni odlazak{{ pass.early_minutes != null ? ` ${pass.early_minutes} min` : '' }}
                  </span>
                  <span v-else class="text-gray-400">—</span>
                </td>
                <td class="px-3 py-2 text-gray-600 max-w-xs">
                  {{ pass.reason || '—' }}
                </td>
              </tr>
              <tr v-if="!employeePasses.length">
                <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                  Nema izlaznica za ovog radnika u odabranom mjesecu.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="mt-3 text-xs text-gray-500">
          Prikazano: {{ employeePasses.length }} izlaznica (odobrenih i zatvorenih)
        </div>
      </template>

      <template #footer>
        <button
          type="button"
          class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
          @click="closeEmployeeModal"
        >
          Zatvori
        </button>
      </template>
    </DialogModal>
  </AppLayout>
</template>
