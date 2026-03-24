<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  departments: { type: Array, default: () => [] },
  shifts: { type: Array, default: () => [] },
})

const departmentSearch = ref('')
const shiftSearch = ref('')
const editingDepartmentId = ref(null)

const isEdit = computed(() => editingDepartmentId.value !== null)

const formatTime = (value) => {
  if (!value) return ''
  const str = String(value).trim()

  const timeMatch = str.match(/(\d{2}:\d{2})(?::\d{2})?$/)
  if (timeMatch) return timeMatch[1]

  const isoMatch = str.match(/T(\d{2}:\d{2})(?::\d{2})?/)
  if (isoMatch) return isoMatch[1]

  return str
}

const shiftLabel = (s) => {
  const start = formatTime(s?.start_time)
  const end = formatTime(s?.end_time)
  const code = s?.attendance_credit_code ? String(s.attendance_credit_code) : ''
  const codePart = code ? ` [${code}]` : ''
  if (start && end) return `${s.name} (${start} - ${end})${codePart}`
  if (start && !end) return `${s.name} (${start})${codePart}`
  return `${s.name}${codePart}`
}

const filteredDepartments = computed(() => {
  const query = String(departmentSearch.value || '').trim().toLowerCase()
  if (!query) return props.departments || []
  return (props.departments || []).filter((d) => {
    const name = String(d?.name || '').toLowerCase()
    const description = String(d?.description || '').toLowerCase()
    return name.includes(query) || description.includes(query)
  })
})

const filteredShifts = computed(() => {
  const query = String(shiftSearch.value || '').trim().toLowerCase()
  if (!query) return props.shifts || []

  return (props.shifts || []).filter((shift) => {
    const text = [
      shift?.name,
      shift?.attendance_credit_code,
      shift?.department_name,
      formatTime(shift?.start_time),
      formatTime(shift?.end_time),
    ].filter(Boolean).join(' ').toLowerCase()

    return text.includes(query)
  })
})

const form = useForm({
  name: '',
  description: '',
  shift_ids: [],
})

const shiftForm = useForm({
  shift_name: '',
  start_time: '',
  end_time: '',
  attendance_credit_code: '',
  shift_department_id: '',
})

const selectedShiftIds = computed(() => new Set((form.shift_ids || []).map((id) => Number(id))))
const sortedShifts = computed(() => [...(props.shifts || [])].sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || ''))))
const summaryCards = computed(() => {
  const departments = props.departments || []
  const shifts = props.shifts || []
  const assignedShifts = shifts.filter((shift) => shift?.department_id !== null).length

  return [
    {
      label: 'Odjeli',
      value: departments.length,
      tone: 'bg-slate-950 text-white border-slate-950',
    },
    {
      label: 'Ukupno smjena',
      value: shifts.length,
      tone: 'bg-white text-slate-900 border-slate-200',
    },
    {
      label: 'Dodijeljene smjene',
      value: assignedShifts,
      tone: 'bg-emerald-50 text-emerald-900 border-emerald-200',
    },
    {
      label: 'Slobodne smjene',
      value: shifts.length - assignedShifts,
      tone: 'bg-amber-50 text-amber-900 border-amber-200',
    },
  ]
})
const selectedShiftCount = computed(() => form.shift_ids.length)

const fieldClass = (field) => {
  const base = 'mt-1 block w-full rounded-md shadow-sm text-sm'
  const ok = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'
  const bad = 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500'
  return `${base} ${form.errors?.[field] ? bad : ok}`
}

const shiftFieldClass = (field) => {
  const base = 'mt-1 block w-full rounded-md shadow-sm text-sm'
  const ok = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'
  const bad = 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500'
  return `${base} ${shiftForm.errors?.[field] ? bad : ok}`
}

const startCreate = () => {
  editingDepartmentId.value = null
  form.reset()
  form.clearErrors()
  form.name = ''
  form.description = ''
  form.shift_ids = []
}

const startEdit = (department) => {
  editingDepartmentId.value = Number(department.id)
  form.clearErrors()
  form.name = department.name || ''
  form.description = department.description || ''
  form.shift_ids = (department.shifts || []).map((shift) => String(shift.id))
}

const isSelectedElsewhere = (shift) => {
  if (selectedShiftIds.value.has(Number(shift.id))) return false
  return shift.department_id !== null && shift.department_id !== editingDepartmentId.value
}

const submit = () => {
  const action = isEdit.value
    ? route('hr.smjene.dodjela.update', editingDepartmentId.value)
    : route('hr.smjene.dodjela.store')

  const method = isEdit.value ? 'put' : 'post'

  form[method](action, {
    preserveScroll: true,
  })
}

const submitShift = () => {
  shiftForm.post(route('hr.smjene.store'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <AppLayout title="Odjeli i smjene">
    <Head title="Odjeli i smjene" />
    <HrNav />

    <div class="min-h-screen bg-[linear-gradient(180deg,#f8fafc_0%,#eef2f7_100%)]">
      <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_20px_60px_-30px_rgba(15,23,42,0.35)]">
          <div class="grid gap-6 lg:grid-cols-[1.4fr,0.9fr]">
            <div class="px-6 py-8 sm:px-8 lg:px-10 lg:py-10 bg-[radial-gradient(circle_at_top_left,#e0f2fe_0%,#ffffff_48%,#ffffff_100%)]">
              <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="flex gap-2 flex-wrap">
                  <button
                    type="button"
                    class="inline-flex items-center rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-1"
                    @click="startCreate"
                  >
                    Novi odjel
                  </button>
                </div>

                <span
                  class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                  :class="isEdit ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
                >
                  {{ isEdit ? 'Uređivanje odjela' : 'Novi unos odjela' }}
                </span>
              </div>

              <div class="mt-8 max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700">HR administracija</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Odjeli i smjene</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base">
                  Centralizovan pregled za unos i uređivanje odjela, dodjelu jedne ili više smjena i brzu kontrolu rasporeda po odjelima.
                </p>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 bg-slate-50/80 p-6 sm:p-8">
              <div
                v-for="card in summaryCards"
                :key="card.label"
                class="rounded-2xl border p-4 shadow-sm"
                :class="card.tone"
              >
                <div class="text-xs font-semibold uppercase tracking-[0.18em] opacity-70">{{ card.label }}</div>
                <div class="mt-3 text-3xl font-semibold tracking-tight">{{ card.value }}</div>
              </div>
            </div>
          </div>
        </section>

        <div v-if="Object.keys(form.errors || {}).length" class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 shadow-sm">
        Provjerite označena polja i pokušajte ponovo.
        </div>

        <div v-if="Object.keys(shiftForm.errors || {}).length" class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700 shadow-sm">
        Provjerite polja za unos smjene i pokušajte ponovo.
        </div>

        <section class="space-y-4">
          <div class="flex items-end justify-between gap-4 flex-wrap">
            <div>
              <h2 class="text-xl font-semibold text-slate-900">Odjeli</h2>
              <p class="mt-1 text-sm text-slate-500">Prvo uredi odjel i njegove povezane smjene, pa zatim po potrebi kreiraj novu smjenu ispod.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm">
              Aktivno odabrano smjena za formu odjela: <span class="font-semibold text-slate-900">{{ selectedShiftCount }}</span>
            </div>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-[1.2fr,0.8fr] gap-6 items-start">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
          <form @submit.prevent="submit">
            <div class="border-b border-slate-200 bg-slate-50/70 px-6 py-5 sm:px-8">
              <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                  <h3 class="text-lg font-semibold text-slate-900">Detalji odjela</h3>
                  <p class="mt-1 text-sm text-slate-500">Naziv, opis i izbor smjena koje pripadaju odjelu.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs leading-5 text-slate-600">
                  Dodavanje smjene odjelu po potrebi je premješta sa drugog odjela.
                </div>
              </div>
            </div>

            <div class="px-6 py-6 sm:px-8 bg-white space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700">Naziv odjela <span class="text-red-600">*</span></label>
                  <input
                    v-model="form.name"
                    type="text"
                    :class="fieldClass('name')"
                    placeholder="Unesite naziv odjela"
                  />
                  <p v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</p>
                </div>

                <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                  <p class="font-medium">Veza sa smjenama</p>
                  <p class="mt-1 text-sky-800/80">Odjel može imati jednu ili više smjena, a vrijeme svake smjene je vidljivo u listi ispod.</p>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-700">Opis</label>
                <textarea
                  v-model="form.description"
                  :class="fieldClass('description')"
                  rows="3"
                  placeholder="Kratak opis odjela"
                />
                <p v-if="form.errors.description" class="text-sm text-red-600 mt-1">{{ form.errors.description }}</p>
              </div>

              <div>
                <div class="flex items-center justify-between gap-3 flex-wrap">
                  <label class="block text-sm font-medium text-slate-700">Dodijeljene smjene</label>
                  <input
                    v-model="shiftSearch"
                    type="text"
                    class="block w-full max-w-xs rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Pretraži smjene..."
                  />
                </div>

                <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-3 space-y-2 max-h-[26rem] overflow-y-auto">
                  <label v-for="shift in filteredShifts" :key="shift.id" class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50">
                    <input
                      type="checkbox"
                      :value="String(shift.id)"
                      v-model="form.shift_ids"
                      class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />

                    <div class="min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <div class="font-medium text-slate-900">{{ shift.name }}</div>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                          {{ formatTime(shift.start_time) || '—' }} - {{ formatTime(shift.end_time) || '—' }}
                        </span>
                        <span v-if="shift.attendance_credit_code" class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700">
                          {{ shift.attendance_credit_code }}
                        </span>
                      </div>
                      <p v-if="shift.department_name" class="text-xs mt-1" :class="isSelectedElsewhere(shift) ? 'text-amber-700' : 'text-slate-500'">
                        {{ isSelectedElsewhere(shift) ? `Trenutno dodijeljena odjelu: ${shift.department_name}. Označavanjem će biti premještena.` : `Trenutno dodijeljena odjelu: ${shift.department_name}` }}
                      </p>
                      <p v-else class="text-xs text-slate-500 mt-1">Smjena trenutno nije dodijeljena nijednom odjelu.</p>
                    </div>
                  </label>

                  <p v-if="filteredShifts.length === 0" class="px-2 py-4 text-sm text-slate-500">Nema smjena za prikaz.</p>
                </div>

                <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500">
                  <span>Odabrano smjena: {{ form.shift_ids.length }}</span>
                  <button type="button" class="font-medium text-slate-700 underline decoration-slate-300 underline-offset-4" @click="form.shift_ids = []">Ukloni sve smjene</button>
                </div>

                <p v-if="form.errors.shift_ids" class="text-sm text-red-600 mt-1">{{ form.errors.shift_ids }}</p>
              </div>
            </div>

            <div class="px-6 py-4 sm:px-8 bg-slate-50 flex items-center justify-between gap-3 border-t border-slate-200">
              <button
                type="button"
                class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="startCreate"
              >
                Očisti formu
              </button>

              <button
                type="submit"
                class="inline-flex items-center rounded-full bg-indigo-600 px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="form.processing"
              >
                {{ isEdit ? 'Sačuvaj izmjene' : 'Sačuvaj odjel' }}
              </button>
            </div>
          </form>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 bg-slate-50/70 px-6 py-5 sm:px-8 space-y-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">Postojeći odjeli</h3>
              <p class="text-sm text-slate-500 mt-1">Brzi pregled odjela, broja uposlenih i povezanih smjena.</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700">Pretraga odjela</label>
              <input
                v-model="departmentSearch"
                type="text"
                class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Pretraži odjel..."
              />
            </div>
          </div>

          <div class="divide-y divide-slate-200 max-h-[42rem] overflow-y-auto bg-white">
            <div v-for="department in filteredDepartments" :key="department.id" class="px-6 py-5 sm:px-8" :class="editingDepartmentId === department.id ? 'bg-amber-50/70' : 'bg-white'">
              <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <h4 class="text-base font-semibold text-slate-900">{{ department.name }}</h4>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                      {{ department.employee_count }} uposlenih
                    </span>
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                      {{ department.shifts.length }} smjena
                    </span>
                  </div>
                  <p v-if="department.description" class="mt-2 text-sm leading-6 text-slate-600">{{ department.description }}</p>
                  <p v-else class="mt-2 text-sm text-slate-400">Opis nije unesen.</p>

                  <div class="mt-3 flex flex-wrap gap-2">
                    <span v-for="shift in department.shifts" :key="shift.id" class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">
                      {{ shiftLabel(shift) }}
                    </span>
                    <span v-if="department.shifts.length === 0" class="text-xs text-slate-400">Nema dodijeljenih smjena.</span>
                  </div>
                </div>

                <button
                  type="button"
                  class="inline-flex shrink-0 items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
                  @click="startEdit(department)"
                >
                  Uredi
                </button>
              </div>
            </div>

            <div v-if="filteredDepartments.length === 0" class="px-6 py-10 text-sm text-slate-500 sm:px-8">
              Nema odjela za prikaz.
            </div>
          </div>
        </div>

          </div>
        </section>

        <section class="space-y-4">
          <div>
            <h2 class="text-xl font-semibold text-slate-900">Smjene</h2>
            <p class="mt-1 text-sm text-slate-500">Unesi novu smjenu i prati sve postojeće smjene sa vremenom i pripadnim odjelom.</p>
          </div>

          <div class="grid grid-cols-1 xl:grid-cols-[0.9fr,1.1fr] gap-6 items-start">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
          <form @submit.prevent="submitShift">
            <div class="border-b border-slate-200 bg-slate-50/70 px-6 py-5 sm:px-8">
              <div>
                <h3 class="text-lg font-semibold text-slate-900">Nova smjena</h3>
                <p class="text-sm text-slate-500 mt-1">Kreiraj smjenu i po želji je odmah dodijeli odjelu.</p>
              </div>
            </div>

            <div class="px-6 py-6 sm:px-8 bg-white">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-slate-700">Naziv smjene <span class="text-red-600">*</span></label>
                  <input
                    v-model="shiftForm.shift_name"
                    type="text"
                    :class="shiftFieldClass('shift_name')"
                    placeholder="Npr. I smjena"
                  />
                  <p v-if="shiftForm.errors.shift_name" class="text-sm text-red-600 mt-1">{{ shiftForm.errors.shift_name }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700">Odjel</label>
                  <select v-model="shiftForm.shift_department_id" :class="shiftFieldClass('shift_department_id')">
                    <option value="">Bez odjela</option>
                    <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                      {{ department.name }}
                    </option>
                  </select>
                  <p v-if="shiftForm.errors.shift_department_id" class="text-sm text-red-600 mt-1">{{ shiftForm.errors.shift_department_id }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700">Početak <span class="text-red-600">*</span></label>
                  <input
                    v-model="shiftForm.start_time"
                    type="time"
                    :class="shiftFieldClass('start_time')"
                  />
                  <p v-if="shiftForm.errors.start_time" class="text-sm text-red-600 mt-1">{{ shiftForm.errors.start_time }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-slate-700">Kraj</label>
                  <input
                    v-model="shiftForm.end_time"
                    type="time"
                    :class="shiftFieldClass('end_time')"
                  />
                  <p v-if="shiftForm.errors.end_time" class="text-sm text-red-600 mt-1">{{ shiftForm.errors.end_time }}</p>
                </div>

                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-slate-700">Šifra evidencije</label>
                  <input
                    v-model="shiftForm.attendance_credit_code"
                    type="text"
                    :class="shiftFieldClass('attendance_credit_code')"
                    placeholder="Npr. I"
                  />
                  <p v-if="shiftForm.errors.attendance_credit_code" class="text-sm text-red-600 mt-1">{{ shiftForm.errors.attendance_credit_code }}</p>
                </div>
              </div>
            </div>

            <div class="px-6 py-4 sm:px-8 bg-slate-50 flex items-center justify-between gap-3 border-t border-slate-200">
              <button
                type="button"
                class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click.prevent="shiftForm.reset(); shiftForm.clearErrors()"
              >
                Očisti smjenu
              </button>

              <button
                type="submit"
                class="inline-flex items-center rounded-full bg-emerald-600 px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-25"
                :disabled="shiftForm.processing"
              >
                Kreiraj smjenu
              </button>
            </div>
          </form>
        </div>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 bg-slate-50/70 px-6 py-5 sm:px-8 space-y-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">Sve smjene</h3>
              <p class="text-sm text-slate-500 mt-1">Vrijeme smjene, evidencijska šifra i trenutni odjel.</p>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-3 text-left font-medium text-slate-600 sm:px-6">Naziv</th>
                  <th class="px-4 py-3 text-left font-medium text-slate-600">Vrijeme</th>
                  <th class="px-4 py-3 text-left font-medium text-slate-600">Šifra</th>
                  <th class="px-4 py-3 text-left font-medium text-slate-600 sm:px-6">Odjel</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="shift in sortedShifts" :key="shift.id">
                  <td class="px-4 py-3 font-medium text-slate-900 sm:px-6">{{ shift.name }}</td>
                  <td class="px-4 py-3 text-slate-600">
                    {{ formatTime(shift.start_time) || '—' }} - {{ formatTime(shift.end_time) || '—' }}
                  </td>
                  <td class="px-4 py-3 text-slate-600">{{ shift.attendance_credit_code || '—' }}</td>
                  <td class="px-4 py-3 text-slate-600 sm:px-6">{{ shift.department_name || 'Nije dodijeljena' }}</td>
                </tr>
                <tr v-if="sortedShifts.length === 0">
                  <td colspan="4" class="px-4 py-8 text-center text-slate-500 sm:px-6">Nema definisanih smjena.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
