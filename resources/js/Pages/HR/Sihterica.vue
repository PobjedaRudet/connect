<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import DateInput from '@/Components/DateInput.vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  month: { type: String, required: true }, // YYYY-MM
  days: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
  attendance: { type: Object, default: () => ({}) },
  leaveOverlaps: { type: Array, default: () => [] },
  shifts: { type: Array, default: () => [] },
  canFilterByDepartment: { type: Boolean, default: false },
  departments: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({ department_id: '' }),
  },
  osmiceDepartments: { type: Array, default: () => [] },
  osmiceTemplates: { type: Object, default: () => ({}) },
})

const page = usePage()
const templateInput = ref(null)
const selectedOsmiceDepartmentId = ref('')

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

const osmiceDepartmentId = computed(() => {
  if (props.canFilterByDepartment) return selectedDepartmentId.value || ''
  if (props.osmiceDepartments.length === 1) return String(props.osmiceDepartments[0].id)
  return selectedOsmiceDepartmentId.value || ''
})

const osmiceDepartmentName = computed(() => {
  const id = osmiceDepartmentId.value
  const fromFilter = (props.departments || []).find((department) => String(department.id) === String(id))
  if (fromFilter) return fromFilter.name
  const fromOsmice = (props.osmiceDepartments || []).find((department) => String(department.id) === String(id))
  return fromOsmice?.name || ''
})

const hasOsmiceTemplate = computed(() => {
  const id = osmiceDepartmentId.value
  if (!id) return false
  return Boolean(props.osmiceTemplates?.[id] || props.osmiceTemplates?.[String(id)])
})

const exportOsmice = () => {
  if (!osmiceDepartmentId.value) {
    window.alert('Odaberite odjel. Svaki odjel ima svoj template.')
    return
  }
  if (!hasOsmiceTemplate.value) {
    window.alert(`Nema template-a za odjel ${osmiceDepartmentName.value || ''}.`.trim())
    return
  }
  const params = new URLSearchParams()
  Object.entries(sihtericaQuery()).forEach(([key, value]) => {
    if (value !== undefined && value !== null && String(value) !== '') {
      params.set(key, String(value))
    }
  })
  params.set('department_id', osmiceDepartmentId.value)
  const term = search.value.trim()
  if (term) params.set('search', term)
  window.location.href = `${route('hr.sihterica.export')}?${params.toString()}`
}

const uploadOsmiceTemplate = (event) => {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  if (!osmiceDepartmentId.value) {
    window.alert('Odaberite odjel prije uploada template-a.')
    return
  }
  const form = new FormData()
  form.append('department_id', osmiceDepartmentId.value)
  form.append('template', file)
  router.post(route('hr.sihterica.template'), form, {
    forceFormData: true,
    preserveScroll: true,
  })
}

const cell = (employeeId, dateStr) => {
  const emp = props.attendance?.[employeeId]
  return emp?.[dateStr] || null
}

const dayCount = (employeeId) => {
  const emp = props.attendance?.[employeeId]
  if (!emp) return 0
  return Object.values(emp).filter((entry) => {
    if (entry?.weekend_overtime && (entry.duration_minutes === null || entry.duration_minutes === undefined)) {
      return false
    }
    return true
  }).length
}

const cellClasses = (dayMeta, entry) => {
  const base = ['px-2', 'py-1.5', 'text-xs', 'whitespace-nowrap', 'border', 'border-gray-100', 'cursor-pointer', 'hover:ring-2', 'hover:ring-indigo-300', 'hover:z-[1]', 'relative']
  if (dayMeta?.isWeekend) base.push('bg-gray-50')
  if (!entry) return base.join(' ')

  if (entry.manual_status) {
    if (entry.leave_overlap) {
      base.push('text-amber-900', 'font-semibold', 'bg-amber-100', 'ring-1', 'ring-amber-400')
    } else if (entry.from_sick_leave) {
      base.push('text-rose-800', 'font-semibold', 'bg-rose-50')
    } else if (entry.from_annual_leave_decision) {
      base.push('text-teal-800', 'font-semibold', 'bg-teal-50')
    } else if (entry.from_annual_leave_usage) {
      base.push('text-blue-700', 'font-semibold', 'bg-blue-50')
    } else if (entry.from_travel_order) {
      base.push('text-sky-800', 'font-semibold', 'bg-sky-50')
    } else {
      base.push('text-indigo-700', 'font-semibold', 'bg-indigo-50')
    }
    return base.join(' ')
  }

  if (entry.leave_overlap) {
    base.push('text-amber-900', 'font-semibold', 'bg-amber-100', 'ring-1', 'ring-amber-400')
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

  if (entry.weekend_overtime) {
    lines.push('Subota ili nedjelja se vodi kao prekovremeni, ne kao radni dan.')
  }

  if (entry.leave_overlap) {
    lines.push('Upozorenje: preklapaju se dani godišnjeg odmora i bolovanja')
  }

  if (entry.manual_status && entry.duration_display) {
    if (entry.leave_overlap) {
      lines.push(`Konflikt statusa: ${entry.duration_display}`)
    } else if (entry.from_sick_leave) {
      lines.push(`Bolovanje: ${entry.duration_display}`)
    } else if (entry.from_annual_leave_decision) {
      lines.push(`Godišnji odmor (rješenje): ${entry.duration_display}`)
    } else if (entry.from_annual_leave_usage) {
      lines.push(`Iskorištenje GO (kao radni dan): ${entry.duration_display}`)
    } else if (entry.from_travel_order) {
      lines.push(`Putni nalog: ${entry.duration_display}`)
    } else {
      lines.push(`Ručni status: ${entry.duration_display}`)
    }
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
    if (entry.from_annual_leave_decision || entry.from_annual_leave_usage || entry.from_sick_leave || entry.leave_overlap || entry.from_travel_order) {
      lines.push(entry.manual_note)
    } else {
      lines.push(`Napomena: ${entry.manual_note}`)
    }
  }

  if (entry.overlap_note) {
    lines.push(entry.overlap_note)
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

const toIsoDate = (value) => {
  const raw = String(value || '').trim()
  const iso = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw)
  const slash = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(raw)
  const year = Number(iso?.[1] ?? slash?.[3])
  const month = Number(iso?.[2] ?? slash?.[2])
  const day = Number(iso?.[3] ?? slash?.[1])
  if (!iso && !slash) return ''
  const parsed = new Date(year, month - 1, day)
  if (parsed.getFullYear() !== year || parsed.getMonth() !== month - 1 || parsed.getDate() !== day) return ''
  return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

const maskTime = (raw) => {
  const cleaned = String(raw || '').replace(/[^\d:]/g, '')
  if (cleaned.includes(':')) {
    const [hour, minute = ''] = cleaned.split(':')
    return `${hour.replace(/\D/g, '').slice(0, 2)}:${minute.replace(/\D/g, '').slice(0, 2)}`
  }
  const digits = cleaned.slice(0, 4)
  if (digits.length <= 2) return digits
  return `${digits.slice(0, 2)}:${digits.slice(2)}`
}

const normalizeTime = (value) => {
  const match = /^(\d{1,2}):(\d{1,2})$/.exec(maskTime(value))
  if (!match) return ''
  const hour = match[1].padStart(2, '0')
  const minute = match[2].padStart(2, '0')
  if (!/^([01]\d|2[0-3])$/.test(hour) || !/^[0-5]\d$/.test(minute)) return ''
  return `${hour}:${minute}`
}

const attendancePayload = (data) => ({
  ...data,
  date: toIsoDate(data.date),
  entry_time: normalizeTime(data.entry_time),
  exit_time: data.exit_time ? normalizeTime(data.exit_time) : '',
})

const attendanceReady = (data) => {
  if (!toIsoDate(data.date) || !normalizeTime(data.entry_time)) return false
  if (data.exit_time && !normalizeTime(data.exit_time)) return false
  return true
}

const departmentShiftsFor = (employeeId) => {
  const employee = (props.employees || []).find((item) => String(item.id) === String(employeeId))
  const departmentId = employee?.department_id
  if (departmentId === null || departmentId === undefined || departmentId === '') return []
  return (props.shifts || []).filter((shift) => String(shift.department_id) === String(departmentId))
}

const resolveShiftId = (employeeId, currentShiftId = '') => {
  const options = departmentShiftsFor(employeeId)
  const current = options.find((shift) => String(shift.id) === String(currentShiftId ?? ''))
  if (current) return current.id
  if (options.length === 1) return options[0].id
  return ''
}

const shiftReady = (employeeId, shiftId) => departmentShiftsFor(employeeId)
  .some((shift) => String(shift.id) === String(shiftId ?? ''))

const openManualModal = (prefill = {}) => {
  manualForm.reset()
  manualForm.clearErrors()
  manualForm.employee_id = prefill.employee_id || ''
  manualForm.shift_id = resolveShiftId(manualForm.employee_id, prefill.shift_id || '')
  manualForm.date = (prefill.date || new Date().toISOString().slice(0, 10)).slice(0, 10)
  manualForm.entry_time = (prefill.entry_time || '').slice(0, 5)
  manualForm.exit_time = (prefill.exit_time || '').slice(0, 5)
  employeeSearch.value = ''
  showManualModal.value = true
}

const onManualEmployeeChange = () => {
  manualForm.shift_id = resolveShiftId(manualForm.employee_id, '')
}

const manualDepartmentShifts = computed(() => departmentShiftsFor(manualForm.employee_id))
const manualShiftMissing = computed(() => (
  manualForm.employee_id !== '' && manualForm.employee_id !== null && manualDepartmentShifts.value.length === 0
))

const submitManual = () => {
  if (!attendanceReady(manualForm) || !shiftReady(manualForm.employee_id, manualForm.shift_id)) return
  manualForm.transform((data) => attendancePayload(data))
  manualForm.post(route('hr.sihterica.manual'), {
    preserveScroll: true,
    onSuccess: () => {
      showManualModal.value = false
      closeDayModal()
    },
  })
}

const employeeSearch = ref('')
const editableEmployees = computed(() => (props.employees || []).filter((e) => e.can_edit !== false))

const filteredModalEmployees = computed(() => {
  const term = employeeSearch.value.trim().toLowerCase()
  if (!term) return editableEmployees.value
  return editableEmployees.value.filter(e => (e.full_name || '').toLowerCase().includes(term))
})

// Day edit modal
const showDayModal = ref(false)
const dayModal = ref({
  employeeId: null,
  employeeName: '',
  empID: null,
  date: '',
  records: [],
  fromAnnualLeaveDecision: false,
  fromAnnualLeaveUsage: false,
  fromSickLeave: false,
  leaveOverlap: false,
  leaveNote: '',
  overlapNote: '',
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
    fromAnnualLeaveDecision: !!entry?.from_annual_leave_decision,
    fromAnnualLeaveUsage: !!entry?.from_annual_leave_usage,
    fromSickLeave: !!entry?.from_sick_leave,
    leaveOverlap: !!entry?.leave_overlap,
    leaveNote: (entry?.from_annual_leave_decision || entry?.from_annual_leave_usage || entry?.from_sick_leave || entry?.leave_overlap)
      ? (entry.manual_note || '')
      : '',
    overlapNote: entry?.overlap_note || '',
    canEdit: employee.can_edit !== false,
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
  editForm.date = String(record.entry_date || dayModal.value.date || '').slice(0, 10)
  editForm.entry_time = (record.entry_time || '').slice(0, 5)
  editForm.exit_time = (record.exit_time || '').slice(0, 5)
  editForm.shift_id = resolveShiftId(dayModal.value.employeeId, record.shift_id || '')
}

const cancelEdit = () => {
  editingId.value = null
  editForm.reset()
  editForm.clearErrors()
}

const editDepartmentShifts = computed(() => departmentShiftsFor(dayModal.value.employeeId))
const editShiftMissing = computed(() => editDepartmentShifts.value.length === 0)

const submitEdit = () => {
  if (!editingId.value || !attendanceReady(editForm) || !shiftReady(dayModal.value.employeeId, editForm.shift_id)) return

  editForm.transform((data) => attendancePayload(data))
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

const formatDateShort = (dateStr) => {
  if (!dateStr) return ''
  try {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('hr-BA', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return dateStr
  }
}

const visibleLeaveOverlaps = computed(() => {
  const overlaps = props.leaveOverlaps || []
  if (!overlaps.length) return []

  const visibleIds = new Set((filteredEmployees.value || []).map((e) => Number(e.id)))
  return overlaps.filter((row) => visibleIds.has(Number(row.employee_id)))
})
</script>

<template>
  <AppLayout title="Šihterica">
    <Head title="Šihterica" />
    <HrNav />

    <div class="max-w-[95rem] mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="mb-6 text-center">
        <h1 class="text-2xl font-semibold text-gray-800">Šihterica (dolazak po mjesecu)</h1>
        <p class="mt-2 text-sm text-gray-500 leading-relaxed max-w-3xl mx-auto">
          Kliknite na ćeliju da korigujete vrijeme prijave/odjave.
          Ako ima više prijava istog dana, sve su dostupne u uređivanju.
        </p>
      </div>

      <div class="flex flex-col items-stretch sm:items-end gap-1 mb-6">
          <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
            <div v-if="!canFilterByDepartment && osmiceDepartments.length > 1">
              <label class="block text-xs font-medium text-gray-600 mb-1">Odjel za Osmice</label>
              <select
                v-model="selectedOsmiceDepartmentId"
                class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 min-w-[12rem]"
              >
                <option value="">Odaberite odjel</option>
                <option v-for="d in osmiceDepartments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
              </select>
            </div>
            <button
              v-if="editableEmployees.length"
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
            <button
              type="button"
              @click="exportOsmice"
              class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-md border border-gray-300 hover:bg-gray-50 transition shadow-sm whitespace-nowrap shrink-0"
            >
              <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/></svg>
              Izvoz Osmice
            </button>
            <button
              type="button"
              @click="templateInput?.click()"
              class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-md border border-gray-300 hover:bg-gray-50 transition shadow-sm whitespace-nowrap shrink-0"
            >
              <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5-5m0 0l5 5m-5-5v12"/></svg>
              Upload template-a
            </button>
            <input
              ref="templateInput"
              type="file"
              accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              class="hidden"
              @change="uploadOsmiceTemplate"
            />
          </div>
          <p class="text-xs text-gray-500 sm:text-right">
            <template v-if="!osmiceDepartmentId">Odaberite odjel. Svaki odjel ima svoj template.</template>
            <template v-else-if="hasOsmiceTemplate">Template za {{ osmiceDepartmentName }} je uploadovan.</template>
            <template v-else>Nema template-a za {{ osmiceDepartmentName || 'odabrani odjel' }}.</template>
          </p>
          <p v-if="page.props.errors?.template" class="text-xs text-red-600 sm:text-right">{{ page.props.errors.template }}</p>
      </div>

      <div
        v-if="page.props.flash?.success"
        class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
      >
        {{ page.props.flash.success }}
      </div>

      <div
        v-if="visibleLeaveOverlaps.length"
        class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950"
      >
        <div class="font-semibold">
          Upozorenje: preklapaju se dani godišnjeg odmora i bolovanja
          ({{ visibleLeaveOverlaps.length }})
        </div>
        <ul class="mt-2 space-y-1 text-amber-900/90">
          <li v-for="(row, idx) in visibleLeaveOverlaps" :key="`${row.employee_id}-${row.date}-${idx}`">
            <span class="font-medium">{{ row.employee_name }}</span>
            — {{ formatDateShort(row.date) }}
          </li>
        </ul>
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
          <span class="font-semibold text-teal-800">GO</span> iz rješenja godišnjeg,
          <span class="font-semibold text-blue-700">8</span> iz iskorištenja GO (kao radni dan),
          <span class="font-semibold text-rose-800">BO</span> iz evidencije bolovanja,
          <span class="font-semibold text-amber-900">GO/BO</span> = preklapanje.
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
            <div
              v-if="dayModal.leaveOverlap"
              class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950"
            >
              <div class="font-semibold">Upozorenje: preklapanje GO i BO</div>
              <p class="mt-1">
                {{ dayModal.overlapNote || 'Za ovaj dan postoji i godišnji odmor i bolovanje.' }}
              </p>
              <p v-if="dayModal.leaveNote" class="mt-1 text-amber-900/90">{{ dayModal.leaveNote }}</p>
            </div>

            <div
              v-else-if="dayModal.fromAnnualLeaveUsage"
              class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
            >
              <div class="font-semibold">Iskorištenje GO — prikazano kao radni dan (8)</div>
              <p v-if="dayModal.leaveNote" class="mt-1 text-blue-800/90">{{ dayModal.leaveNote }}</p>
            </div>

            <div
              v-else-if="dayModal.fromAnnualLeaveDecision"
              class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900"
            >
              <div class="font-semibold">Godišnji odmor (GO) — iz rješenja</div>
              <p v-if="dayModal.leaveNote" class="mt-1 text-teal-800/90">{{ dayModal.leaveNote }}</p>
              <p v-if="dayModal.records.length" class="mt-1 text-teal-800/80 text-xs">
                Prijava/odjava postoji u bazi, ali je na šihterici sakrivena ispod GO.
              </p>
            </div>

            <div
              v-else-if="dayModal.fromSickLeave"
              class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
            >
              <div class="font-semibold">Bolovanje (BO)</div>
              <p v-if="dayModal.leaveNote" class="mt-1 text-rose-800/90">{{ dayModal.leaveNote }}</p>
            </div>

            <div v-if="!dayModal.records.length" class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
              <p class="text-sm text-gray-600 mb-3">
                <template v-if="dayModal.leaveOverlap">Nema prijave za ovaj dan (preklapanje godišnjeg i bolovanja).</template>
                <template v-else-if="dayModal.fromAnnualLeaveUsage">Nema stvarne prijave — dan je označen iskorištenjem GO kao radni (8).</template>
                <template v-else-if="dayModal.fromAnnualLeaveDecision">Nema prijave za ovaj dan (označeno rješenjem godišnjeg).</template>
                <template v-else-if="dayModal.fromSickLeave">Nema prijave za ovaj dan (označeno bolovanjem).</template>
                <template v-else>Nema prijave za ovaj dan.</template>
              </p>
              <button
                v-if="dayModal.canEdit"
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
                <div class="flex items-center gap-2" v-if="dayModal.canEdit && editingId !== record.record_id">
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
                    <DateInput v-model="editForm.date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                    <div v-if="editForm.errors.date" class="text-red-600 text-xs mt-1">{{ editForm.errors.date }}</div>
                    <p v-else class="text-[11px] text-gray-400 mt-0.5">dd/mm/yyyy</p>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prijava</label>
                    <input :value="editForm.entry_time" type="text" inputmode="numeric" maxlength="5" placeholder="HH:mm" autocomplete="off" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" @input="editForm.entry_time = maskTime($event.target.value)" @blur="editForm.entry_time = normalizeTime(editForm.entry_time) || editForm.entry_time" />
                    <div v-if="editForm.errors.entry_time" class="text-red-600 text-xs mt-1">{{ editForm.errors.entry_time }}</div>
                    <p v-else class="text-[11px] text-gray-400 mt-0.5">24h, npr. 07:00</p>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Odjava</label>
                    <input :value="editForm.exit_time" type="text" inputmode="numeric" maxlength="5" placeholder="HH:mm" autocomplete="off" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" @input="editForm.exit_time = maskTime($event.target.value)" @blur="editForm.exit_time = editForm.exit_time ? (normalizeTime(editForm.exit_time) || editForm.exit_time) : ''" />
                    <div v-if="editForm.errors.exit_time" class="text-red-600 text-xs mt-1">{{ editForm.errors.exit_time }}</div>
                    <p class="text-[11px] text-gray-400 mt-0.5">Prazno = još radi. 24h, npr. 16:20</p>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Smjena</label>
                  <select v-model="editForm.shift_id" :disabled="editShiftMissing" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100">
                    <option v-if="editDepartmentShifts.length !== 1" value="" disabled>— Odaberi smjenu —</option>
                    <option v-for="s in editDepartmentShifts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start_time?.slice(0,5) }} – {{ s.end_time?.slice(0,5) }})</option>
                  </select>
                  <div v-if="editForm.errors.shift_id" class="text-red-600 text-xs mt-1">{{ editForm.errors.shift_id }}</div>
                  <p v-else-if="editShiftMissing" class="text-red-600 text-xs mt-1">Odjel nema dodijeljenu smjenu.</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                  <button type="button" class="px-3 py-1.5 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200" @click="cancelEdit">Otkaži</button>
                  <button
                    type="button"
                    class="px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="editForm.processing || !attendanceReady(editForm) || !shiftReady(dayModal.employeeId, editForm.shift_id)"
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
              <select v-model="manualForm.employee_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required @change="onManualEmployeeChange">
                <option value="" disabled>— Odaberi uposlenika —</option>
                <option v-for="e in filteredModalEmployees" :key="e.id" :value="e.id">{{ e.full_name }} (#{{ e.empID }})</option>
              </select>
              <div v-if="manualForm.errors.employee_id" class="text-red-600 text-xs mt-1">{{ manualForm.errors.employee_id }}</div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Smjena</label>
              <select v-model="manualForm.shift_id" :disabled="manualShiftMissing" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100">
                <option v-if="manualDepartmentShifts.length !== 1" value="" disabled>— Odaberi smjenu —</option>
                <option v-for="s in manualDepartmentShifts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start_time?.slice(0,5) }} – {{ s.end_time?.slice(0,5) }})</option>
              </select>
              <div v-if="manualForm.errors.shift_id" class="text-red-600 text-xs mt-1">{{ manualForm.errors.shift_id }}</div>
              <p v-else-if="manualShiftMissing" class="text-red-600 text-xs mt-1">Odjel nema dodijeljenu smjenu.</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Datum</label>
              <DateInput v-model="manualForm.date" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" />
              <div v-if="manualForm.errors.date" class="text-red-600 text-xs mt-1">{{ manualForm.errors.date }}</div>
              <p v-else class="text-xs text-gray-400 mt-0.5">dd/mm/yyyy</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prijava (dolazak)</label>
                <input :value="manualForm.entry_time" type="text" inputmode="numeric" maxlength="5" placeholder="HH:mm" autocomplete="off" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" @input="manualForm.entry_time = maskTime($event.target.value)" @blur="manualForm.entry_time = normalizeTime(manualForm.entry_time) || manualForm.entry_time" />
                <div v-if="manualForm.errors.entry_time" class="text-red-600 text-xs mt-1">{{ manualForm.errors.entry_time }}</div>
                <p v-else class="text-xs text-gray-400 mt-0.5">24h, npr. 07:00</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Odjava (odlazak)</label>
                <input :value="manualForm.exit_time" type="text" inputmode="numeric" maxlength="5" placeholder="HH:mm" autocomplete="off" class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" @input="manualForm.exit_time = maskTime($event.target.value)" @blur="manualForm.exit_time = manualForm.exit_time ? (normalizeTime(manualForm.exit_time) || manualForm.exit_time) : ''" />
                <div v-if="manualForm.errors.exit_time" class="text-red-600 text-xs mt-1">{{ manualForm.errors.exit_time }}</div>
                <p class="text-xs text-gray-400 mt-0.5">Ostavite prazno ako radnik još radi. 24h, npr. 16:20</p>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
              <button type="button" @click="showManualModal = false" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition">
                Otkaži
              </button>
              <button type="submit" :disabled="manualForm.processing || !attendanceReady(manualForm) || !shiftReady(manualForm.employee_id, manualForm.shift_id)" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition disabled:opacity-50">
                {{ manualForm.processing ? 'Spremam...' : 'Spremi' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
