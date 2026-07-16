<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import DialogModal from '@/Components/DialogModal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, nextTick, ref } from 'vue'

const props = defineProps({
  departments: { type: Array, default: () => [] },
  shifts: { type: Array, default: () => [] },
})

const activeTab = ref('departments')
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

const stats = computed(() => {
  const departments = props.departments || []
  const shifts = props.shifts || []
  const assignedShifts = shifts.filter((shift) => shift?.department_id !== null).length

  return [
    { label: 'Odjeli', value: departments.length },
    { label: 'Smjene', value: shifts.length },
    { label: 'Dodijeljene', value: assignedShifts },
    { label: 'Slobodne', value: shifts.length - assignedShifts },
  ]
})

const fieldClass = (field) => {
  const base = 'mt-1 block w-full rounded-md border text-sm shadow-sm'
  const ok = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'
  const bad = 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500'
  return `${base} ${form.errors?.[field] ? bad : ok}`
}

const shiftFieldClass = (field) => {
  const base = 'mt-1 block w-full rounded-md border text-sm shadow-sm'
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
  activeTab.value = 'departments'
}

const startEdit = (department) => {
  editingDepartmentId.value = Number(department.id)
  form.clearErrors()
  form.name = department.name || ''
  form.description = department.description || ''
  form.shift_ids = (department.shifts || []).map((shift) => String(shift.id))
  activeTab.value = 'departments'
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
    onSuccess: () => {
      if (!isEdit.value) startCreate()
    },
  })
}

const submitShift = () => {
  shiftForm.post(route('hr.smjene.store'), {
    preserveScroll: true,
    onSuccess: () => {
      shiftForm.reset()
      shiftForm.clearErrors()
    },
  })
}

const deleteModal = ref({
  show: false,
  type: null, // 'department' | 'shift'
  id: null,
  name: '',
})
const pinInput = ref(null)
const deleteForm = useForm({
  pin: '',
})

const deleteModalTitle = computed(() => {
  if (deleteModal.value.type === 'department') return 'Brisanje odjela'
  if (deleteModal.value.type === 'shift') return 'Brisanje smjene'
  return 'Brisanje'
})

const openDeleteDepartment = (department) => {
  deleteForm.reset()
  deleteForm.clearErrors()
  deleteModal.value = {
    show: true,
    type: 'department',
    id: Number(department.id),
    name: department.name || '',
  }
  nextTick(() => pinInput.value?.focus())
}

const openDeleteShift = (shift) => {
  deleteForm.reset()
  deleteForm.clearErrors()
  deleteModal.value = {
    show: true,
    type: 'shift',
    id: Number(shift.id),
    name: shift.name || '',
  }
  nextTick(() => pinInput.value?.focus())
}

const closeDeleteModal = (force = false) => {
  if (!force && deleteForm.processing) return
  deleteModal.value = { show: false, type: null, id: null, name: '' }
  deleteForm.reset()
  deleteForm.clearErrors()
}

const submitDelete = () => {
  if (!deleteModal.value.id || !deleteModal.value.type) return

  const action =
    deleteModal.value.type === 'department'
      ? route('hr.smjene.dodjela.destroy', deleteModal.value.id)
      : route('hr.smjene.destroy', deleteModal.value.id)

  const wasDepartment = deleteModal.value.type === 'department'
  const deletedId = deleteModal.value.id

  deleteForm.delete(action, {
    preserveScroll: true,
    onSuccess: () => {
      closeDeleteModal(true)
      if (wasDepartment && editingDepartmentId.value === deletedId) {
        startCreate()
      }
    },
  })
}
</script>

<template>
  <AppLayout title="Odjeli i smjene">
    <Head title="Odjeli i smjene" />
    <HrNav />

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Odjeli i smjene</h1>
          <p class="text-sm text-gray-500">
            Upravljajte odjelima, dodjeljujte smjene i kreirajte nove rasporede.
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <div
            v-for="stat in stats"
            :key="stat.label"
            class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-center shadow-sm"
          >
            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ stat.label }}</div>
            <div class="mt-0.5 text-lg font-semibold tabular-nums text-gray-800">{{ stat.value }}</div>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 shadow-sm">
          <button
            type="button"
            class="rounded-md px-4 py-2 text-sm font-medium transition"
            :class="activeTab === 'departments'
              ? 'bg-indigo-600 text-white'
              : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
            @click="activeTab = 'departments'"
          >
            Odjeli
          </button>
          <button
            type="button"
            class="rounded-md px-4 py-2 text-sm font-medium transition"
            :class="activeTab === 'shifts'
              ? 'bg-indigo-600 text-white'
              : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800'"
            @click="activeTab = 'shifts'"
          >
            Smjene
          </button>
        </div>

        <button
          v-if="activeTab === 'departments'"
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="startCreate"
        >
          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Novi odjel
        </button>
      </div>

      <div
        v-if="Object.keys(form.errors || {}).length && activeTab === 'departments'"
        class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        Provjerite označena polja i pokušajte ponovo.
      </div>
      <div
        v-if="Object.keys(shiftForm.errors || {}).length && activeTab === 'shifts'"
        class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      >
        Provjerite polja za unos smjene i pokušajte ponovo.
      </div>

      <div v-show="activeTab === 'departments'" class="grid grid-cols-1 gap-6 xl:grid-cols-[22rem,minmax(0,1fr)]">
        <aside class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-200 px-4 py-4">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-sm font-semibold text-gray-800">Postojeći odjeli</h2>
              <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium tabular-nums text-gray-600">
                {{ filteredDepartments.length }}
              </span>
            </div>
            <div class="relative mt-3">
              <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
              <input
                v-model="departmentSearch"
                type="text"
                class="block w-full rounded-md border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Pretraži odjel…"
              />
            </div>
          </div>

          <div class="max-h-[38rem] divide-y divide-gray-100 overflow-y-auto">
            <button
              v-for="department in filteredDepartments"
              :key="department.id"
              type="button"
              class="group flex w-full items-start gap-3 px-4 py-3.5 text-left transition"
              :class="editingDepartmentId === department.id
                ? 'bg-indigo-50'
                : 'bg-white hover:bg-gray-50'"
              @click="startEdit(department)"
            >
              <div
                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-xs font-semibold"
                :class="editingDepartmentId === department.id
                  ? 'bg-indigo-600 text-white'
                  : 'bg-gray-100 text-gray-600 group-hover:bg-gray-200'"
              >
                {{ (department.name || '?').charAt(0).toUpperCase() }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="truncate text-sm font-semibold text-gray-800">{{ department.name }}</span>
                  <span
                    v-if="editingDepartmentId === department.id"
                    class="shrink-0 rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white"
                  >
                    Uređivanje
                  </span>
                </div>
                <div class="mt-1 flex flex-wrap gap-1.5">
                  <span class="inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-600">
                    {{ department.employee_count }} uposlenih
                  </span>
                  <span class="inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-600">
                    {{ department.shifts.length }} smjena
                  </span>
                </div>
                <div v-if="department.shifts.length" class="mt-2 flex flex-wrap gap-1">
                  <span
                    v-for="shift in department.shifts.slice(0, 3)"
                    :key="shift.id"
                    class="truncate rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] text-gray-600"
                  >
                    {{ shift.name }}
                  </span>
                  <span
                    v-if="department.shifts.length > 3"
                    class="rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] text-gray-500"
                  >
                    +{{ department.shifts.length - 3 }}
                  </span>
                </div>
              </div>
            </button>

            <div v-if="filteredDepartments.length === 0" class="px-4 py-10 text-center text-sm text-gray-500">
              Nema odjela za prikaz.
            </div>
          </div>
        </aside>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <form @submit.prevent="submit">
              <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-5 py-4 sm:px-6">
                <div>
                  <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-gray-800">
                      {{ isEdit ? 'Uredi odjel' : 'Novi odjel' }}
                    </h2>
                    <span
                      class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                      :class="isEdit ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700'"
                    >
                      {{ isEdit ? 'Izmjena' : 'Kreiranje' }}
                    </span>
                  </div>
                  <p class="mt-1 text-sm text-gray-500">
                    Naziv, opis i dodjela smjena. Premještanje smjene automatski je uklanja s drugog odjela.
                  </p>
                </div>
                <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-600">
                  Odabrano: <span class="font-semibold tabular-nums text-gray-800">{{ form.shift_ids.length }}</span> smjena
                </div>
              </div>

              <div class="space-y-5 px-5 py-5 sm:px-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Naziv odjela <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="form.name"
                      type="text"
                      :class="fieldClass('name')"
                      placeholder="Unesite naziv odjela"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Opis</label>
                    <input
                      v-model="form.description"
                      type="text"
                      :class="fieldClass('description')"
                      placeholder="Kratak opis (opcionalno)"
                    />
                    <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                  </div>
                </div>

                <div>
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="block text-sm font-medium text-gray-700">Dodijeljene smjene</label>
                    <div class="relative w-full max-w-xs">
                      <svg class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                      </svg>
                      <input
                        v-model="shiftSearch"
                        type="text"
                        class="block w-full rounded-md border-gray-300 py-1.5 pl-8 pr-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Pretraži smjene…"
                      />
                    </div>
                  </div>

                  <div class="mt-3 max-h-[22rem] space-y-2 overflow-y-auto rounded-md border border-gray-200 bg-gray-50 p-2.5">
                    <label
                      v-for="shift in filteredShifts"
                      :key="shift.id"
                      class="flex cursor-pointer items-start gap-3 rounded-md border bg-white px-3.5 py-3 text-sm transition"
                      :class="selectedShiftIds.has(Number(shift.id))
                        ? 'border-indigo-300 ring-1 ring-indigo-200'
                        : isSelectedElsewhere(shift)
                          ? 'border-amber-200 hover:border-amber-300'
                          : 'border-gray-200 hover:border-gray-300'"
                    >
                      <input
                        type="checkbox"
                        :value="String(shift.id)"
                        v-model="form.shift_ids"
                        class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                      />

                      <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                          <span class="font-medium text-gray-800">{{ shift.name }}</span>
                          <span class="inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium tabular-nums text-gray-600">
                            {{ formatTime(shift.start_time) || '—' }} – {{ formatTime(shift.end_time) || '—' }}
                          </span>
                          <span
                            v-if="shift.attendance_credit_code"
                            class="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-[11px] font-medium text-indigo-700"
                          >
                            {{ shift.attendance_credit_code }}
                          </span>
                        </div>
                        <p
                          v-if="shift.department_name"
                          class="mt-1 text-xs"
                          :class="isSelectedElsewhere(shift) ? 'text-amber-700' : 'text-gray-500'"
                        >
                          {{ isSelectedElsewhere(shift)
                            ? `Trenutno: ${shift.department_name} — označavanjem će biti premještena.`
                            : `Odjel: ${shift.department_name}` }}
                        </p>
                        <p v-else class="mt-1 text-xs text-gray-400">Nije dodijeljena nijednom odjelu.</p>
                      </div>
                    </label>

                    <p v-if="filteredShifts.length === 0" class="px-2 py-6 text-center text-sm text-gray-500">
                      Nema smjena za prikaz.
                    </p>
                  </div>

                  <div class="mt-2 flex items-center justify-between gap-3 text-xs text-gray-500">
                    <span>{{ filteredShifts.length }} prikazanih</span>
                    <button
                      type="button"
                      class="font-medium text-gray-700 underline decoration-gray-300 underline-offset-4 hover:text-gray-900"
                      @click="form.shift_ids = []"
                    >
                      Ukloni sve
                    </button>
                  </div>

                  <p v-if="form.errors.shift_ids" class="mt-1 text-sm text-red-600">{{ form.errors.shift_ids }}</p>
                </div>
              </div>

              <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    @click="startCreate"
                  >
                    Očisti formu
                  </button>

                  <button
                    v-if="isEdit"
                    type="button"
                    class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50"
                    @click="openDeleteDepartment({ id: editingDepartmentId, name: form.name })"
                  >
                    Obriši odjel
                  </button>
                </div>

                <button
                  type="submit"
                  class="inline-flex items-center rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-40"
                  :disabled="form.processing"
                >
                  {{ isEdit ? 'Sačuvaj izmjene' : 'Sačuvaj odjel' }}
                </button>
              </div>
            </form>
          </section>
        </div>

        <div v-show="activeTab === 'shifts'" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,22rem),minmax(0,1fr)]">
          <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <form @submit.prevent="submitShift">
              <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-800">Nova smjena</h2>
                <p class="mt-1 text-sm text-gray-500">Kreiraj smjenu i po želji je odmah dodijeli odjelu.</p>
              </div>

              <div class="space-y-4 px-5 py-5">
                <div>
                  <label class="block text-sm font-medium text-gray-700">
                    Naziv smjene <span class="text-red-500">*</span>
                  </label>
                  <input
                    v-model="shiftForm.shift_name"
                    type="text"
                    :class="shiftFieldClass('shift_name')"
                    placeholder="Npr. I smjena"
                  />
                  <p v-if="shiftForm.errors.shift_name" class="mt-1 text-sm text-red-600">{{ shiftForm.errors.shift_name }}</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Odjel</label>
                  <select v-model="shiftForm.shift_department_id" :class="shiftFieldClass('shift_department_id')">
                    <option value="">Bez odjela</option>
                    <option v-for="department in departments" :key="department.id" :value="String(department.id)">
                      {{ department.name }}
                    </option>
                  </select>
                  <p v-if="shiftForm.errors.shift_department_id" class="mt-1 text-sm text-red-600">{{ shiftForm.errors.shift_department_id }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">
                      Početak <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="shiftForm.start_time"
                      type="time"
                      :class="shiftFieldClass('start_time')"
                    />
                    <p v-if="shiftForm.errors.start_time" class="mt-1 text-sm text-red-600">{{ shiftForm.errors.start_time }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Kraj</label>
                    <input
                      v-model="shiftForm.end_time"
                      type="time"
                      :class="shiftFieldClass('end_time')"
                    />
                    <p v-if="shiftForm.errors.end_time" class="mt-1 text-sm text-red-600">{{ shiftForm.errors.end_time }}</p>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700">Šifra evidencije</label>
                  <input
                    v-model="shiftForm.attendance_credit_code"
                    type="text"
                    :class="shiftFieldClass('attendance_credit_code')"
                    placeholder="Npr. I"
                  />
                  <p v-if="shiftForm.errors.attendance_credit_code" class="mt-1 text-sm text-red-600">{{ shiftForm.errors.attendance_credit_code }}</p>
                </div>
              </div>

              <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
                <button
                  type="button"
                  class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                  @click.prevent="shiftForm.reset(); shiftForm.clearErrors()"
                >
                  Očisti
                </button>
                <button
                  type="submit"
                  class="inline-flex items-center rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-40"
                  :disabled="shiftForm.processing"
                >
                  Kreiraj smjenu
                </button>
              </div>
            </form>
          </section>

          <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4">
              <div>
                <h2 class="text-base font-semibold text-gray-800">Sve smjene</h2>
                <p class="mt-0.5 text-sm text-gray-500">Vrijeme, šifra i trenutni odjel.</p>
              </div>
              <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium tabular-nums text-gray-600">
                {{ sortedShifts.length }} ukupno
              </span>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full text-sm">
                <thead>
                  <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                    <th class="px-5 py-3">Naziv</th>
                    <th class="px-4 py-3">Vrijeme</th>
                    <th class="px-4 py-3">Šifra</th>
                    <th class="px-5 py-3">Odjel</th>
                    <th class="px-4 py-3 text-right">Akcije</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr
                    v-for="shift in sortedShifts"
                    :key="shift.id"
                    class="transition hover:bg-gray-50"
                  >
                    <td class="px-5 py-3 font-medium text-gray-800">{{ shift.name }}</td>
                    <td class="px-4 py-3 tabular-nums text-gray-600">
                      {{ formatTime(shift.start_time) || '—' }} – {{ formatTime(shift.end_time) || '—' }}
                    </td>
                    <td class="px-4 py-3">
                      <span
                        v-if="shift.attendance_credit_code"
                        class="inline-flex rounded-md bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700"
                      >
                        {{ shift.attendance_credit_code }}
                      </span>
                      <span v-else class="text-gray-400">—</span>
                    </td>
                    <td class="px-5 py-3">
                      <span v-if="shift.department_name" class="text-gray-700">{{ shift.department_name }}</span>
                      <span v-else class="inline-flex rounded-md bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700">
                        Nije dodijeljena
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <button
                        type="button"
                        class="rounded-md border border-red-300 bg-white px-2.5 py-1 text-xs font-medium text-red-700 transition hover:bg-red-50"
                        @click="openDeleteShift(shift)"
                      >
                        Obriši
                      </button>
                    </td>
                  </tr>
                  <tr v-if="sortedShifts.length === 0">
                    <td colspan="5" class="px-5 py-12 text-center text-gray-500">
                      Nema definisanih smjena.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
    </div>

    <DialogModal :show="deleteModal.show" max-width="md" @close="closeDeleteModal">
      <template #title>
        {{ deleteModalTitle }}
      </template>

      <template #content>
        <p>
          Trajno brišete
          <span class="font-semibold text-gray-800">{{ deleteModal.name || 'stavku' }}</span>.
          Unesite šifru da potvrdite.
        </p>
        <p
          v-if="deleteModal.type === 'department'"
          class="mt-2 text-xs text-amber-700"
        >
          Uposlenici će biti odspojeni od odjela, a smjene ostaju bez dodjele.
        </p>

        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700">Šifra</label>
          <input
            ref="pinInput"
            v-model="deleteForm.pin"
            type="password"
            inputmode="numeric"
            autocomplete="off"
            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
            placeholder="Unesite šifru"
            @keyup.enter="submitDelete"
          />
          <p v-if="deleteForm.errors.pin" class="mt-1 text-sm text-red-600">{{ deleteForm.errors.pin }}</p>
        </div>
      </template>

      <template #footer>
        <SecondaryButton :disabled="deleteForm.processing" @click="closeDeleteModal">
          Otkaži
        </SecondaryButton>

        <DangerButton
          class="ms-3"
          :class="{ 'opacity-40': deleteForm.processing }"
          :disabled="deleteForm.processing || !deleteForm.pin"
          @click="submitDelete"
        >
          Obriši
        </DangerButton>
      </template>
    </DialogModal>
  </AppLayout>
</template>
