<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  month: { type: String, required: true },
  days: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
  overtime: { type: Object, default: () => ({}) },
  totals: { type: Object, default: () => ({}) },
})

const selectedMonth = ref(props.month)
const search = ref('')

watch(
  () => props.month,
  (value) => {
    selectedMonth.value = value
  }
)

const filteredEmployees = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return props.employees

  return (props.employees || []).filter((employee) =>
    (employee.full_name || '').toLowerCase().includes(term)
  )
})

const onMonthChange = () => {
  router.get(route('hr.prekovremeni-sati'), { month: selectedMonth.value }, { preserveScroll: true, preserveState: true })
}

const cell = (employeeId, dateStr) => {
  const employee = props.overtime?.[employeeId]
  return employee?.[dateStr] || null
}

const total = (employeeId) => props.totals?.[employeeId] || {
  earned_minutes: 0,
  used_minutes: 0,
  remaining_minutes: 0,
  earned_display: '0:00',
  used_display: '0:00',
  remaining_display: '0:00',
}

const formatMinutes = (minutes, emptyFallback = '—') => {
  if (minutes === null || minutes === undefined) return emptyFallback

  const total = Number(minutes)
  if (!Number.isFinite(total)) return emptyFallback

  const hours = Math.floor(total / 60)
  const remainder = total % 60

  return `${hours}:${String(remainder).padStart(2, '0')}`
}

const cellClasses = (dayMeta, entry) => {
  const base = ['px-2', 'py-1.5', 'text-xs', 'whitespace-nowrap', 'border', 'border-gray-100']

  if (dayMeta?.isWeekend) base.push('bg-gray-50')
  if (entry?.status === 'partial') base.push('bg-amber-50', 'text-amber-900')
  if (entry?.status === 'used') base.push('bg-emerald-50', 'text-emerald-900')
  if (entry?.status === 'unused') base.push('text-gray-800', 'font-medium')

  return base.join(' ')
}

const formatUsageType = (value) => {
  const raw = String(value || '').replaceAll('_', ' ')
  return raw.charAt(0).toUpperCase() + raw.slice(1)
}

const usageTitle = (entry) => {
  if (!entry?.usages?.length) return ''

  return entry.usages
    .map((usage) => {
      const note = usage.note ? ` | ${usage.note}` : ''
      return `${usage.usage_date || 'datum?'} | ${formatUsageType(usage.usage_type)} | ${usage.allocated_display}${note}`
    })
    .join('\n')
}
</script>

<template>
  <AppLayout title="Prekovremeni sati">
    <Head title="Prekovremeni sati" />

    <div class="max-w-[110rem] mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="mb-4">
        <div class="flex items-center gap-3 flex-wrap">
          <Link
            :href="route('sector.hr')"
            class="inline-flex items-center px-3 py-2 bg-white text-gray-700 border border-gray-200 rounded-md text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
          >
            Nazad na HR
          </Link>

          <Link
            :href="route('hr.prekovremeni.iskoristenje')"
            class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
          >
            Unesi iskorištenje
          </Link>
        </div>
      </div>

      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Prekovremeni sati po mjesecu</h1>
          <p class="text-sm text-gray-500">
            Prikaz je baziran na tabelama <span class="font-mono">attendance_overtimes</span> i <span class="font-mono">overtime_usages</span>, sa zarađenim, iskorištenim i preostalim saldom.
          </p>
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
                <th class="px-3 py-3 bg-gray-50 z-10 border-r border-gray-200">Zarađeno</th>
                <th class="px-3 py-3 bg-gray-50 z-10 border-r border-gray-200">Iskorišteno</th>
                <th class="px-3 py-3 bg-gray-50 z-10 border-r border-gray-200">Preostalo</th>
                <th
                  v-for="day in days"
                  :key="day.date"
                  class="px-2 py-3 text-center border-l border-gray-200"
                  :class="day.isWeekend ? 'bg-gray-100' : ''"
                >
                  <div class="leading-4">
                    <div class="text-gray-700">{{ day.day }}</div>
                    <div class="text-[10px] text-gray-500 normal-case font-medium">{{ day.weekday }}</div>
                  </div>
                </th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-200">
              <tr v-for="employee in filteredEmployees" :key="employee.id" class="hover:bg-gray-50">
                <td class="px-4 py-2 text-sm text-gray-800 font-medium sticky left-0 bg-white z-10 border-r border-gray-200">
                  <div class="flex flex-col">
                    <span>{{ employee.full_name }}</span>
                    <span class="text-xs text-gray-500">#{{ employee.empID }}</span>
                  </div>
                </td>

                <td class="px-3 py-2 text-sm text-gray-700 border-r border-gray-200 font-medium">{{ total(employee.id).earned_display }}</td>
                <td class="px-3 py-2 text-sm text-rose-700 border-r border-gray-200 font-medium">{{ total(employee.id).used_display }}</td>
                <td class="px-3 py-2 text-sm text-emerald-700 border-r border-gray-200 font-semibold">{{ total(employee.id).remaining_display }}</td>

                <td
                  v-for="day in days"
                  :key="`${employee.id}-${day.date}`"
                  class="text-center"
                  :class="cellClasses(day, cell(employee.id, day.date))"
                  :title="usageTitle(cell(employee.id, day.date))"
                >
                  <template v-if="cell(employee.id, day.date)">
                    <div class="leading-4 space-y-0.5">
                      <div class="text-gray-700">Z {{ cell(employee.id, day.date).earned_display }}</div>
                      <div class="text-rose-700">I {{ cell(employee.id, day.date).used_display }}</div>
                      <div class="font-semibold text-emerald-700">P {{ cell(employee.id, day.date).remaining_display }}</div>
                    </div>
                  </template>

                  <template v-else>
                    <span class="text-gray-300">—</span>
                  </template>
                </td>
              </tr>

              <tr v-if="filteredEmployees.length === 0">
                <td :colspan="4 + days.length" class="px-4 py-6 text-center text-sm text-gray-500">
                  Nema rezultata za odabrani mjesec / filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-4 py-3 text-xs text-gray-500 border-t border-gray-200 bg-gray-50">
          Legenda: Z = zarađeno, I = iskorišteno, P = preostalo. Hover na ćeliju prikazuje detalje iskorištenja za taj dan kada postoje.
        </div>
      </div>
    </div>
  </AppLayout>
</template>
