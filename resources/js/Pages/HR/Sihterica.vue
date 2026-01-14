<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  month: { type: String, required: true }, // YYYY-MM
  days: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
  attendance: { type: Object, default: () => ({}) },
})

const selectedMonth = ref(props.month)
const search = ref('')

watch(
  () => props.month,
  (val) => {
    selectedMonth.value = val
  }
)

const filteredEmployees = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return props.employees
  return (props.employees || []).filter((e) => (e.full_name || '').toLowerCase().includes(term))
})

const onMonthChange = () => {
  const month = selectedMonth.value
  router.get(route('hr.sihterica'), { month }, { preserveScroll: true, preserveState: true })
}

const cell = (employeeId, dateStr) => {
  const emp = props.attendance?.[employeeId]
  return emp?.[dateStr] || null
}

const dayCount = (employeeId) => {
  const emp = props.attendance?.[employeeId]
  if (!emp) return 0
  return Object.keys(emp).length
}

const cellClasses = (dayMeta, entry) => {
  const base = ['px-2', 'py-1.5', 'text-xs', 'whitespace-nowrap', 'border', 'border-gray-100']
  if (dayMeta?.isWeekend) base.push('bg-gray-50')
  if (!entry) return base.join(' ')

  if (entry.late_flag === 'major') base.push('text-red-700', 'font-semibold')
  else if (entry.late_flag === 'minor') base.push('text-amber-700', 'font-semibold')
  else base.push('text-gray-800')

  return base.join(' ')
}
</script>

<template>
  <AppLayout title="Šihterica">
    <Head title="Šihterica" />

    <div class="max-w-[95rem] mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Šihterica (dolazak po mjesecu)</h1>
          <p class="text-sm text-gray-500">Prikaz je baziran na tabeli <span class="font-mono">attendance_records</span> (najraniji dolazak po danu).</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Mjesec</label>
            <input
              v-model="selectedMonth"
              type="month"
              class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              @change="onMonthChange"
            />
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Pretraga radnika</label>
            <input
              v-model="search"
              type="text"
              placeholder="Ime i prezime"
              class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
        </div>
      </div>

      <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-gray-50">
              <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                <th class="px-4 py-3 sticky left-0 bg-gray-50 z-10 border-r border-gray-200">Radnik</th>
                <th class="px-3 py-3 bg-gray-50 z-10 border-r border-gray-200">Ukupno</th>
                <th
                  v-for="d in days"
                  :key="d.date"
                  class="px-2 py-3 text-center border-l border-gray-200"
                  :class="d.isWeekend ? 'bg-gray-100' : ''"
                >
                  <div class="leading-4">
                    <div class="text-gray-700">{{ d.day }}</div>
                    <div class="text-[10px] text-gray-500 normal-case font-medium">{{ d.weekday }}</div>
                  </div>
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
              <tr v-for="e in filteredEmployees" :key="e.id" class="hover:bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-800 font-medium sticky left-0 bg-white z-10 border-r border-gray-200">
                  <div class="flex flex-col">
                    <span>{{ e.full_name }}</span>
                    <span class="text-xs text-gray-500">#{{ e.empID }}</span>
                  </div>
                </td>
                <td class="px-3 py-2 text-sm text-gray-700 border-r border-gray-200">
                  {{ dayCount(e.id) }}
                </td>
                <td
                  v-for="d in days"
                  :key="`${e.id}-${d.date}`"
                  class="text-center"
                  :class="cellClasses(d, cell(e.id, d.date))"
                >
                  <template v-if="cell(e.id, d.date)">
                    <div class="leading-4">
                      <div>{{ cell(e.id, d.date).entry_time || '—' }}</div>
                      <div v-if="cell(e.id, d.date).exit_time" class="text-[10px] text-gray-500">{{ cell(e.id, d.date).exit_time }}</div>
                    </div>
                  </template>
                  <template v-else>
                    <span class="text-gray-300">—</span>
                  </template>
                </td>
              </tr>

              <tr v-if="filteredEmployees.length === 0">
                <td :colspan="2 + days.length" class="px-4 py-6 text-center text-sm text-gray-500">
                  Nema rezultata za odabrani mjesec / filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-4 py-3 text-xs text-gray-500 border-t border-gray-200 bg-gray-50">
          Legenda: <span class="font-semibold text-amber-700">minor</span> / <span class="font-semibold text-red-700">major</span> kašnjenje (late_flag).
        </div>
      </div>
    </div>
  </AppLayout>
</template>
