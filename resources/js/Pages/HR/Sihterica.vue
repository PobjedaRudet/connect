<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  month: { type: String, required: true }, // YYYY-MM
  days: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
  attendance: { type: Object, default: () => ({}) },
  shifts: { type: Array, default: () => [] },
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
  (val) => {
    selectedMonth.value = val
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
  return (props.employees || []).filter((e) => (e.full_name || '').toLowerCase().includes(term))
})

const sihtericaQuery = () => {
  const params = { month: selectedMonth.value }
  if (props.canFilterByDepartment) {
    // "all" = svi odjeli; inače čuvamo odabrani odjel (default: Služba informatike)
    params.department_id = selectedDepartmentId.value || 'all'
  }
  return params
}

const onMonthChange = () => {
  router.get(route('hr.sihterica'), sihtericaQuery(), { preserveScroll: true, preserveState: true })
}

const onDepartmentChange = () => {
  router.get(route('hr.sihterica'), sihtericaQuery(), { preserveScroll: true, preserveState: true })
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
  const base = ['px-2', 'py-1.5', 'text-xs', 'whitespace-nowrap', 'border', 'border-gray-100', 'cursor-pointer', 'hover:ring-2', 'hover:ring-indigo-300', 'hover:z-[1]', 'relative']
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
  if (!entry) return 'Kliknite za unos / korekciju vremena'

  const lines = ['Kliknite za uređivanje']

  if (entry.manual_status && entry.duration_display) {
    lines.push(`Ručni status: ${entry.duration_display}`)
  }

  const records = entry.records || []
  if (records.length > 1) {
    lines.push(`${records.length} prijave/odjave tog dana:`)
  }

  if (records.length) {
    records.forEach((r, idx) => {
      lines.push(`${idx + 1}. Prijava: ${r.entry_time || '—'} | Odjava: ${r.exit_time || '—'}`)
    })
  } else if (entry.entry_time || entry.exit_time) {
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

const openManualModal = (prefill = {}) => {
  manualForm.reset()
  manualForm.clearErrors()
  manualForm.employee_id = prefill.employee_id || ''
  manualForm.shift_id = prefill.shift_id || ''
  manualForm.date = prefill.date || new Date().toISOString().slice(0, 10)
  manualForm.entry_time = prefill.entry_time || ''
  manualForm.exit_time = prefill.exit_time || ''
  employeeSearch.value = ''
  showManualModal.value = true
}

const submitManual = () => {
  manualForm.post(route('hr.sihterica.manual'), {
    preserveScroll: true,
    onSuccess: () => {
      showManualModal.value = false
      closeDayModal()
    },
  })
}

const employeeSearch = ref('')
const filteredModalEmployees = computed(() => {
  const term = employeeSearch.value.trim().toLowerCase()
  if (!term) return props.employees
  return props.employees.filter(e => (e.full_name || '').toLowerCase().includes(term))
})

// Day edit modal
const showDayModal = ref(false)
const dayModal = ref({
  employeeId: null,
  employeeName: '',
  empID: null,
  date: '',
  records: [],
})
const editingId = ref(null)
const editForm = useForm({
  date: '',
  entry_time: '',
  exit_time: '',
  shift_id: '',
})
const deletingId = ref(null)

const openDayModal = (employee, dateStr) => {
  const entry = cell(employee.id, dateStr)
  dayModal.value = {
    employeeId: employee.id,
    employeeName: employee.full_name,
    empID: employee.empID,
    date: dateStr,
    records: entry?.records ? [...entry.records] : [],
  }
  editingId.value = null
  editForm.reset()
  editForm.clearErrors()
  showDayModal.value = true
}

const closeDayModal = () => {
  showDayModal.value = false
  editingId.value = null
  deletingId.value = null
  editForm.reset()
  editForm.clearErrors()
}

const startEdit = (record) => {
  editingId.value = record.record_id
  editForm.clearErrors()
  editForm.date = record.entry_date || dayModal.value.date
  editForm.entry_time = record.entry_time || ''
  editForm.exit_time = record.exit_time || ''
  editForm.shift_id = record.shift_id || ''
}

const cancelEdit = () => {
  editingId.value = null
  editForm.reset()
  editForm.clearErrors()
}

const submitEdit = () => {
  if (!editingId.value) return

  editForm.put(route('hr.sihterica.update', editingId.value), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      editingId.value = null
      const empId = dayModal.value.employeeId
      const date = dayModal.value.date
      const entry = cell(empId, date)
      dayModal.value.records = entry?.records ? [...entry.records] : []
    },
  })
}

const confirmDelete = (recordId) => {
  deletingId.value = recordId
}

const cancelDelete = () => {
  deletingId.value = null
}

const submitDelete = (recordId) => {
  router.delete(route('hr.sihterica.destroy', recordId), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      deletingId.value = null
      const empId = dayModal.value.employeeId
      const date = dayModal.value.date
      const entry = cell(empId, date)
      dayModal.value.records = entry?.records ? [...entry.records] : []
    },
  })
}

const addRecordForDay = () => {
  showDayModal.value = false
  openManualModal({
    employee_id: dayModal.value.employeeId,
    date: dayModal.value.date,
  })
}

const formatDateLabel = (dateStr) => {
  if (!dateStr) return ''
  try {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('hr-BA', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  } catch {
    return dateStr
  }
}
</script>

<template>
  <AppLayout title="Šihterica">
    <Head title="Šihterica" />
    <HrNav />

    <div class="max-w-[95rem] mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Šihterica (dolazak po mjesecu)</h1>
          <p class="text-sm text-gray-500">
            Kliknite na ćeliju da korigujete vrijeme prijave/odjave.
            Ako ima više prijava istog dana, sve su dostupne u uređivanju.
          </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
          <button
            @click="openManualModal()"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition shadow-sm whitespace-nowrap shrink-0"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
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
                  @click="openDayModal(e, d.date)"
                >
                  <template v-if="cell(e.id, d.date)">
                    <div class="leading-4">
                      <div>{{ cellValue(cell(e.id, d.date)) }}</div>
                      <div
                        v-if="(cell(e.id, d.date)?.records_count || 0) > 1"
                        class="text-[10px] text-indigo-600 font-medium"
                      >
                        {{ cell(e.id, d.date).records_count }}×
                      </div>
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
          Legenda: <span class="font-semibold text-yellow-600">minor</span> /
          <span class="font-semibold text-red-700">major</span> kašnjenje,
          ručni statusi: <span class="font-semibold text-indigo-700">P</span>,
          <span class="font-semibold text-indigo-700">GO</span>,
          <span class="font-semibold text-indigo-700">BO</span>…
          Klik na ćeliju = korekcija vremena. oznaka <span class="font-semibold text-indigo-600">2×</span> = više prijava istog dana.
        </div>
      </div>
    </div>

    <!-- Day edit modal -->
    <Teleport to="body">
      <div v-if="showDayModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/40" @click="closeDayModal"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-start justify-between gap-3 z-10">
            <div>
              <h3 class="text-lg font-semibold text-gray-800">Korekcija prijave / odjave</h3>
              <p class="text-sm text-gray-500 mt-0.5">
                {{ dayModal.employeeName }}
                <span v-if="dayModal.empID" class="text-gray-400">(#{{ dayModal.empID }})</span>
                — {{ formatDateLabel(dayModal.date) }}
              </p>
            </div>
            <button type="button" @click="closeDayModal" class="text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <div class="px-6 py-4 space-y-4">
            <div v-if="!dayModal.records.length" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
              <p class="text-sm text-gray-600 mb-3">Nema prijave za ovaj dan.</p>
              <button
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition"
                @click="addRecordForDay"
              >
                Unesi prijavu / odjavu
              </button>
            </div>

            <div
              v-for="(record, idx) in dayModal.records"
              :key="record.record_id"
              class="rounded-lg border border-gray-200 p-4"
            >
              <div class="flex items-center justify-between gap-3 mb-3">
                <div class="text-sm font-medium text-gray-800">
                  Zapis {{ idx + 1 }}
                  <span class="ml-2 text-xs font-normal text-gray-500">
                    {{ record.terminal_in || '—' }} → {{ record.terminal_out || '—' }}
                  </span>
                </div>
                <div class="flex items-center gap-2" v-if="editingId !== record.record_id">
                  <button
                    type="button"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    @click="startEdit(record)"
                  >
                    Uredi
                  </button>
                  <button
                    type="button"
                    class="text-sm text-red-600 hover:text-red-800 font-medium"
                    @click="confirmDelete(record.record_id)"
                  >
                    Obriši
                  </button>
                </div>
              </div>

              <div v-if="editingId === record.record_id" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Datum</label>
                    <input v-model="editForm.date" type="date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required />
                    <div v-if="editForm.errors.date" class="text-red-600 text-xs mt-1">{{ editForm.errors.date }}</div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prijava</label>
                    <input v-model="editForm.entry_time" type="time" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required />
                    <div v-if="editForm.errors.entry_time" class="text-red-600 text-xs mt-1">{{ editForm.errors.entry_time }}</div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Odjava</label>
                    <input v-model="editForm.exit_time" type="time" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    <div v-if="editForm.errors.exit_time" class="text-red-600 text-xs mt-1">{{ editForm.errors.exit_time }}</div>
                    <p class="text-[11px] text-gray-400 mt-0.5">Prazno = još radi</p>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Smjena</label>
                  <select v-model="editForm.shift_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">— Bez smjene —</option>
                    <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start_time?.slice(0,5) }} – {{ s.end_time?.slice(0,5) }})</option>
                  </select>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                  <button type="button" class="px-3 py-1.5 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200" @click="cancelEdit">Otkaži</button>
                  <button
                    type="button"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="editForm.processing"
                    @click="submitEdit"
                  >
                    {{ editForm.processing ? 'Spremam...' : 'Spremi izmjene' }}
                  </button>
                </div>
              </div>

              <div v-else class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div>
                  <div class="text-xs text-gray-500">Prijava</div>
                  <div class="font-medium text-gray-800">{{ record.entry_time || '—' }}</div>
                </div>
                <div>
                  <div class="text-xs text-gray-500">Odjava</div>
                  <div class="font-medium text-gray-800">{{ record.exit_time || '—' }}</div>
                </div>
                <div>
                  <div class="text-xs text-gray-500">Trajanje</div>
                  <div class="font-medium text-gray-800">
                    {{ record.duration_display || formatHours(record.duration_minutes) }}
                  </div>
                </div>
                <div>
                  <div class="text-xs text-gray-500">Status</div>
                  <div class="font-medium text-gray-800">{{ record.status || '—' }}</div>
                </div>
              </div>

              <div
                v-if="deletingId === record.record_id"
                class="mt-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
              >
                <p class="text-sm text-red-700">Obrisati ovaj zapis prijave/odjave?</p>
                <div class="flex gap-2">
                  <button type="button" class="px-3 py-1.5 text-sm text-gray-700 bg-white border border-gray-200 rounded-md" @click="cancelDelete">Ne</button>
                  <button type="button" class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700" @click="submitDelete(record.record_id)">Da, obriši</button>
                </div>
              </div>
            </div>
          </div>

          <div class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800"
              @click="addRecordForDay"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Dodaj još jednu prijavu
            </button>
            <button type="button" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200" @click="closeDayModal">
              Zatvori
            </button>
          </div>
        </div>
      </div>
    </Teleport>

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

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Smjena</label>
              <select v-model="manualForm.shift_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">— Bez smjene —</option>
                <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start_time?.slice(0,5) }} – {{ s.end_time?.slice(0,5) }})</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
              <input v-model="manualForm.date" type="date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required />
              <div v-if="manualForm.errors.date" class="text-red-600 text-xs mt-1">{{ manualForm.errors.date }}</div>
            </div>

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
