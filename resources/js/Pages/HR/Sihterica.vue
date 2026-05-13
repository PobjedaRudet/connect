<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  month: { type: String, required: true }, // YYYY-MM
  days: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
  attendance: { type: Object, default: () => ({}) },
  shifts: { type: Array, default: () => [] },
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

  if (entry.manual_status) {
    base.push('text-indigo-700', 'font-semibold', 'bg-indigo-50')
    return base.join(' ')
  }

  if (entry.late_flag === 'major') base.push('text-red-700', 'font-semibold')
  else if (entry.late_flag === 'minor') base.push('text-amber-700', 'font-semibold')
  else base.push('text-gray-800')

  return base.join(' ')
}

const formatHours = (minutes) => {
  if (minutes === null || minutes === undefined) return '—'
  const hours = minutes / 60
  const rounded = Math.round(hours * 10) / 10
  if (Number.isInteger(rounded)) return String(rounded)
  return rounded.toFixed(1)
}

const cellValue = (entry) => {
  if (!entry) return '—'
  if (entry.duration_display) return String(entry.duration_display)
  return formatHours(entry.duration_minutes)
}

const cellTitle = (entry) => {
  if (!entry) return ''

  const lines = []

  if (entry.manual_status && entry.duration_display) {
    lines.push(`Rucni status: ${entry.duration_display}`)
  }

  if (entry.entry_time || entry.exit_time) {
    lines.push(`Prijava: ${entry.entry_time || '—'} | Odjava: ${entry.exit_time || '—'}`)
  }

  if (entry.terminal_in || entry.terminal_out) {
    lines.push(`Terminal IN: ${entry.terminal_in || '—'} | Terminal OUT: ${entry.terminal_out || '—'}`)
  }

  if (entry.manual_status && entry.manual_note) {
    lines.push(`Napomena: ${entry.manual_note}`)
  }

  return lines.join('\n')
}

// Manual entry modal
const showManualModal = ref(false)
const manualForm = useForm({
  employee_id: '',
  shift_id: '',
  date: '',
  entry_time: '',
  exit_time: '',
})

const openManualModal = () => {
  manualForm.reset()
  manualForm.date = new Date().toISOString().slice(0, 10)
  showManualModal.value = true
}

const submitManual = () => {
  manualForm.post(route('hr.sihterica.manual'), {
    preserveScroll: true,
    onSuccess: () => { showManualModal.value = false },
  })
}

const employeeSearch = ref('')
const filteredModalEmployees = computed(() => {
  const term = employeeSearch.value.trim().toLowerCase()
  if (!term) return props.employees
  return props.employees.filter(e => (e.full_name || '').toLowerCase().includes(term))
})
</script>

<template>
  <AppLayout title="Šihterica">
    <Head title="Šihterica" />
    <HrNav />

    <div class="max-w-[95rem] mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Šihterica (dolazak po mjesecu)</h1>
          <p class="text-sm text-gray-500">Prikaz je baziran na tabeli <span class="font-mono">attendance_records</span> (najraniji dolazak po danu).</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
          <button
            @click="openManualModal"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition shadow-sm"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Ručni unos
          </button>
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
                  :title="cellTitle(cell(e.id, d.date))"
                >
                  <template v-if="cell(e.id, d.date)">
                    <div class="leading-4">
                      <div>{{ cellValue(cell(e.id, d.date)) }}</div>
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
          Legenda: <span class="font-semibold text-yellow-600">minor</span> / <span class="font-semibold text-red-700">major</span> kasnjenje (late_flag), rucni statusi: <span class="font-semibold text-indigo-700">P</span>, <span class="font-semibold text-indigo-700">GO</span>, <span class="font-semibold text-indigo-700">BO</span>, <span class="font-semibold text-indigo-700">PO</span>, <span class="font-semibold text-indigo-700">RP</span>, <span class="font-semibold text-indigo-700">PR</span>.
        </div>
      </div>
    </div>

    <!-- Manual entry modal -->
    <Teleport to="body">
      <div v-if="showManualModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/40" @click="showManualModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 p-6">
          <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold text-gray-800">Ručni unos smjene</h3>
            <button @click="showManualModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <form @submit.prevent="submitManual" class="space-y-4">
            <!-- Employee -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Uposlenik</label>
              <input
                v-model="employeeSearch"
                type="text"
                placeholder="Pretraži..."
                class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 mb-1"
              />
              <select v-model="manualForm.employee_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                <option value="" disabled>— Odaberi uposlenika —</option>
                <option v-for="e in filteredModalEmployees" :key="e.id" :value="e.id">{{ e.full_name }} (#{{ e.empID }})</option>
              </select>
              <div v-if="manualForm.errors.employee_id" class="text-red-600 text-xs mt-1">{{ manualForm.errors.employee_id }}</div>
            </div>

            <!-- Shift -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Smjena</label>
              <select v-model="manualForm.shift_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">— Bez smjene —</option>
                <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start_time?.slice(0,5) }} – {{ s.end_time?.slice(0,5) }})</option>
              </select>
            </div>

            <!-- Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
              <input v-model="manualForm.date" type="date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required />
              <div v-if="manualForm.errors.date" class="text-red-600 text-xs mt-1">{{ manualForm.errors.date }}</div>
            </div>

            <!-- Time row -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prijava (dolazak)</label>
                <input v-model="manualForm.entry_time" type="time" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required />
                <div v-if="manualForm.errors.entry_time" class="text-red-600 text-xs mt-1">{{ manualForm.errors.entry_time }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Odjava (odlazak)</label>
                <input v-model="manualForm.exit_time" type="time" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                <div v-if="manualForm.errors.exit_time" class="text-red-600 text-xs mt-1">{{ manualForm.errors.exit_time }}</div>
                <p class="text-xs text-gray-400 mt-0.5">Ostavite prazno ako radnik još radi.</p>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-2">
              <button type="button" @click="showManualModal = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition">
                Otkaži
              </button>
              <button type="submit" :disabled="manualForm.processing" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition disabled:opacity-50">
                {{ manualForm.processing ? 'Spremam...' : 'Spremi' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
