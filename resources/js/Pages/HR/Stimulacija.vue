<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  month: { type: String, required: true },
  days: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
  stimulations: { type: Object, default: () => ({}) },
  totals: { type: Object, default: () => ({}) },
  canFilterByDepartment: { type: Boolean, default: false },
  departments: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({ department_id: '' }),
  },
})

const selectedMonth = ref(props.month)
const selectedDepartmentId = ref(props.filters?.department_id || '')
const search = ref('')

watch(
  () => props.month,
  (value) => {
    selectedMonth.value = value
  }
)

watch(
  () => props.filters?.department_id,
  (val) => {
    selectedDepartmentId.value = val || ''
  }
)

const filteredEmployees = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (!term) return props.employees

  return (props.employees || []).filter((employee) =>
    (employee.full_name || '').toLowerCase().includes(term)
  )
})

const stimulationQuery = () => {
  const params = { month: selectedMonth.value }
  if (props.canFilterByDepartment) {
    params.department_id = selectedDepartmentId.value || 'all'
  }
  return params
}

const onMonthChange = () => {
  router.get(route('hr.stimulacija'), stimulationQuery(), { preserveScroll: true, preserveState: true })
}

const onDepartmentChange = () => {
  router.get(route('hr.stimulacija'), stimulationQuery(), { preserveScroll: true, preserveState: true })
}

const cell = (employeeId, dateStr) => {
  const employee = props.stimulations?.[employeeId]
  return employee?.[dateStr] || null
}

const total = (employeeId) => props.totals?.[employeeId] || {
  minutes: 0,
  display: '0:00',
}

const cellClasses = (dayMeta, entry) => {
  const base = ['px-2', 'py-1.5', 'text-xs', 'whitespace-nowrap', 'border', 'border-gray-100', 'text-center']

  if (dayMeta?.isWeekend) base.push('bg-gray-50')
  if (entry) base.push('text-gray-800', 'font-medium')

  return base.join(' ')
}
</script>

<template>
  <AppLayout title="Stimulacija">
    <Head title="Stimulacija" />
    <HrNav />

    <div class="max-w-[110rem] mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Stimulacija po mjesecu</h1>
          <p class="text-sm text-gray-500">
            Pregled minuta subote i nedjelje koje se vode kao stimulacija. Iskorištenje se ovdje ne vodi.
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

          <div v-if="canFilterByDepartment">
            <label class="block text-xs font-medium text-gray-600 mb-1">Odjel</label>
            <select
              v-model="selectedDepartmentId"
              class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 min-w-[12rem]"
              @change="onDepartmentChange"
            >
              <option value="">Svi odjeli</option>
              <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
            </select>
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
                <th class="px-3 py-3 bg-gray-50 z-10 border-r border-gray-200">Zbir</th>
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

                <td class="px-3 py-2 text-sm text-gray-700 border-r border-gray-200 font-medium">{{ total(employee.id).display }}</td>

                <td
                  v-for="day in days"
                  :key="`${employee.id}-${day.date}`"
                  :class="cellClasses(day, cell(employee.id, day.date))"
                >
                  <template v-if="cell(employee.id, day.date)">
                    {{ cell(employee.id, day.date).display }}
                  </template>
                  <template v-else>
                    <span class="text-gray-300 font-normal">—</span>
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
      </div>
    </div>
  </AppLayout>
</template>
