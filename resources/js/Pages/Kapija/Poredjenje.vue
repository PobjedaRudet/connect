<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  date: { type: String, required: true },
  tolerance_minutes: { type: Number, default: 15 },
  rows: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({ total: 0, flagged: 0, ok: 0 }) },
  from_hr: { type: Boolean, default: false },
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
  router.get(route(props.from_hr ? 'hr.poredjenje' : 'kapija.poredjenje'), {
    date: date.value,
    tolerance: tolerance.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

const editor = ref(null)
const editDate = ref('')
const editTime = ref('')
const saving = ref(false)

const fieldMeta = {
  gate_in: { kind: 'gate', event: 'in', idKey: 'gate_in_id', atKey: 'gate_in_at', label: 'Ulaz — kapija' },
  gate_out: { kind: 'gate', event: 'out', idKey: 'gate_out_id', atKey: 'gate_out_at', label: 'Izlaz — kapija' },
  building_in: { kind: 'building', event: 'in', idKey: 'building_in_id', atKey: 'building_in_at', label: 'Ulaz — objekat' },
  building_out: { kind: 'building', event: 'out', idKey: 'building_out_id', atKey: 'building_out_at', label: 'Izlaz — objekat' },
}

const displayFromIso = (value) => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || '').trim())
  if (!match) return ''
  return `${match[3]}/${match[2]}/${match[1]}`
}

const isoFromDisplay = (value) => {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(value || '').trim())
  if (!match) return ''
  const day = Number(match[1])
  const month = Number(match[2])
  const year = Number(match[3])
  const parsed = new Date(year, month - 1, day)
  if (parsed.getFullYear() !== year || parsed.getMonth() !== month - 1 || parsed.getDate() !== day) return ''
  return `${match[3]}-${match[2]}-${match[1]}`
}

const maskDate = (raw) => {
  const digits = String(raw || '').replace(/\D/g, '').slice(0, 8)
  if (digits.length <= 2) return digits
  if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`
  return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
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

const editDateIso = computed(() => isoFromDisplay(editDate.value))
const editTimeValue = computed(() => (/^([01]\d|2[0-3]):[0-5]\d$/.test(editTime.value) ? editTime.value : ''))
const dateInvalid = computed(() => editDate.value.length === 10 && !editDateIso.value)
const timeInvalid = computed(() => editTime.value.length === 5 && !editTimeValue.value)

const openEditor = (row, field) => {
  const meta = fieldMeta[field]
  if (!props.from_hr || row?.can_edit === false) return
  const id = row?.[meta.idKey] || null
  editor.value = {
    ...meta,
    id,
    creating: !id,
    employeeId: row.employee_id,
    attendanceId: field === 'building_out' ? row.building_in_id : (field === 'building_in' ? row.building_out_id : null),
    employee: row.full_name,
    sameRecord: field.startsWith('building') && row.building_in_id && row.building_in_id === row.building_out_id,
  }
  const stamp = String(row?.[meta.atKey] || '')
  const [stampDate, stampTime] = stamp.includes(' ') ? stamp.split(' ') : ['', '']
  editDate.value = displayFromIso(stampDate || date.value)
  editTime.value = (stampTime || row[field] || '').slice(0, 5)
}

const closeEditor = () => {
  if (saving.value) return
  editor.value = null
}

const onDateInput = (event) => { editDate.value = maskDate(event.target.value) }
const onTimeInput = (event) => { editTime.value = maskTime(event.target.value) }
const onTimeBlur = () => {
  const match = /^(\d{1,2}):(\d{1,2})$/.exec(editTime.value)
  if (!match) return
  editTime.value = `${match[1].padStart(2, '0')}:${match[2].padStart(2, '0')}`
}

const saveEdit = () => {
  if (!editor.value || !editDateIso.value || !editTimeValue.value) return
  saving.value = true
  if (editor.value.creating) {
    router.post(route('hr.poredjenje.store'), {
      kind: editor.value.kind,
      event: editor.value.event,
      employee_id: editor.value.employeeId,
      attendance_id: editor.value.attendanceId,
      date: editDateIso.value,
      report_date: date.value,
      time: editTimeValue.value,
      tolerance: tolerance.value,
    }, {
      preserveScroll: true,
      onFinish: () => {
        saving.value = false
        editor.value = null
      },
    })
    return
  }
  router.patch(route('hr.poredjenje.update'), {
    kind: editor.value.kind,
    event: editor.value.event,
    id: editor.value.id,
    date: editDateIso.value,
    report_date: date.value,
    time: editTimeValue.value,
    tolerance: tolerance.value,
  }, {
    preserveScroll: true,
    onFinish: () => {
      saving.value = false
      editor.value = null
    },
  })
}

const deleteEdit = () => {
  if (!editor.value || editor.value.creating) return
  const label = editor.value.label
  const extra = editor.value.event === 'in' && editor.value.sameRecord
    ? ' Brisanjem ulaza na objekt briše se i odjava ako je na istom zapisu.'
    : ''
  if (!window.confirm(`Obrisati ${label} za ${editor.value.employee}?${extra}`)) return
  saving.value = true
  router.delete(route('hr.poredjenje.delete'), {
    data: {
      kind: editor.value.kind,
      event: editor.value.event,
      id: editor.value.id,
      date: date.value,
      tolerance: tolerance.value,
    },
    preserveScroll: true,
    onFinish: () => {
      saving.value = false
      editor.value = null
    },
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
        <a
          :href="from_hr ? '/sector/hr' : '/kapija'"
          class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          {{ from_hr ? 'HR sektor' : 'Kapija — pregled uživo' }}
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
              <th class="px-4 py-3">Izlaz — objekat</th>
              <th class="px-4 py-3">Izlaz — kapija</th>
              <th class="px-4 py-3">Napomena</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in filteredRows" :key="row.employee_id" :class="row.flagged ? 'bg-red-50/60' : ''">
              <td class="px-4 py-3 text-sm text-gray-800">
                <div class="font-medium text-gray-900">{{ row.full_name }}</div>
                <div class="text-xs text-gray-500">#{{ row.empID }}</div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <button v-if="from_hr && row.can_edit" type="button" class="font-medium text-sky-800 hover:underline" @click="openEditor(row, 'gate_in')">{{ row.gate_in || 'Dodaj' }}</button>
                <span v-else>{{ row.gate_in || '—' }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <button v-if="from_hr && row.can_edit" type="button" class="font-medium text-sky-800 hover:underline" @click="openEditor(row, 'building_in')">{{ row.building_in || 'Dodaj' }}</button>
                <span v-else>{{ row.building_in || '—' }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <button v-if="from_hr && row.can_edit" type="button" class="font-medium text-sky-800 hover:underline" @click="openEditor(row, 'building_out')">{{ row.building_out || 'Dodaj' }}</button>
                <span v-else>{{ row.building_out || '—' }}</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <button v-if="from_hr && row.can_edit" type="button" class="font-medium text-sky-800 hover:underline" @click="openEditor(row, 'gate_out')">{{ row.gate_out || 'Dodaj' }}</button>
                <span v-else>{{ row.gate_out || '—' }}</span>
              </td>
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

    <div
      v-if="editor"
      class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 px-4"
      @click.self="closeEditor"
    >
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">{{ editor.label }}</div>
        <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ editor.employee }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ editor.creating ? 'Upišite datum i vrijeme za ovaj zapis.' : 'Ispravite datum i vrijeme ili obrišite ovaj zapis.' }}</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label class="block text-xs font-medium text-slate-600" for="edit-date">Datum</label>
            <input
              id="edit-date"
              :value="editDate"
              type="text"
              inputmode="numeric"
              maxlength="10"
              placeholder="dd/mm/yyyy"
              autocomplete="off"
              class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              @input="onDateInput"
            />
            <p class="mt-1 text-xs" :class="dateInvalid ? 'text-red-600' : 'text-slate-500'">Format: dd/mm/yyyy</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600" for="edit-time">Vrijeme (24h)</label>
            <input
              id="edit-time"
              :value="editTime"
              type="text"
              inputmode="numeric"
              maxlength="5"
              placeholder="HH:mm"
              autocomplete="off"
              class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              @input="onTimeInput"
              @blur="onTimeBlur"
            />
            <p class="mt-1 text-xs" :class="timeInvalid ? 'text-red-600' : 'text-slate-500'">24 sata, npr. 07:30 ili 18:05.</p>
          </div>
        </div>
        <div class="mt-5 flex items-center justify-between gap-3">
          <button v-if="!editor.creating" type="button" class="text-sm font-medium text-red-700 hover:text-red-900 disabled:opacity-50" :disabled="saving" @click="deleteEdit">Obriši</button>
          <span v-else></span>
          <div class="flex gap-2">
            <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700" :disabled="saving" @click="closeEditor">Otkaži</button>
            <button type="button" class="rounded-md bg-sky-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="saving || !editDateIso || !editTimeValue" @click="saveEdit">Sačuvaj</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
