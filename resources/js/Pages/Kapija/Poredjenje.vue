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
const editTime = ref('')
const saving = ref(false)

const fieldMeta = {
  gate_in: { kind: 'gate', event: 'in', idKey: 'gate_in_id', label: 'Ulaz — kapija' },
  gate_out: { kind: 'gate', event: 'out', idKey: 'gate_out_id', label: 'Izlaz — kapija' },
  building_in: { kind: 'building', event: 'in', idKey: 'building_in_id', label: 'Ulaz — objekat' },
  building_out: { kind: 'building', event: 'out', idKey: 'building_out_id', label: 'Izlaz — objekat' },
}

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
  editTime.value = row[field] || ''
}

const closeEditor = () => {
  if (saving.value) return
  editor.value = null
}

const saveEdit = () => {
  if (!editor.value || !editTime.value) return
  saving.value = true
  if (editor.value.creating) {
    router.post(route('hr.poredjenje.store'), {
      kind: editor.value.kind,
      event: editor.value.event,
      employee_id: editor.value.employeeId,
      attendance_id: editor.value.attendanceId,
      date: date.value,
      time: editTime.value,
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
    date: date.value,
    time: editTime.value,
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
        <p class="mt-1 text-sm text-slate-500">{{ editor.creating ? 'Upišite vrijeme za ovaj zapis.' : 'Ispravite vrijeme ili obrišite ovaj zapis.' }}</p>
        <label class="mt-4 block text-xs font-medium text-slate-600" for="edit-time">Vrijeme</label>
        <input id="edit-time" v-model="editTime" type="time" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        <div class="mt-5 flex items-center justify-between gap-3">
          <button v-if="!editor.creating" type="button" class="text-sm font-medium text-red-700 hover:text-red-900 disabled:opacity-50" :disabled="saving" @click="deleteEdit">Obriši</button>
          <span v-else></span>
          <div class="flex gap-2">
            <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700" :disabled="saving" @click="closeEditor">Otkaži</button>
            <button type="button" class="rounded-md bg-sky-700 px-3 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="saving || !editTime" @click="saveEdit">Sačuvaj</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
