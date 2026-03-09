<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  departments: { type: Array, default: () => [] },
  shifts: { type: Array, default: () => [] },
})

const departmentSearch = ref('')

const formatTime = (value) => {
  if (!value) return ''
  const str = String(value)
  return str.length >= 5 ? str.slice(0, 5) : str
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
  return (props.departments || []).filter((d) => String(d?.name || '').toLowerCase().includes(query))
})

const form = useForm({
  department_id: '',
  shift_ids: [],
})

const fieldClass = (field) => {
  const base = 'mt-1 block w-full rounded-md shadow-sm text-sm'
  const ok = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'
  const bad = 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500'
  return `${base} ${form.errors?.[field] ? bad : ok}`
}

const shiftsForDepartment = (deptId) => {
  const id = deptId ? Number(deptId) : null
  if (!id) return []
  return (props.shifts || []).filter((s) => Number(s.department_id || 0) === id)
}

watch(
  () => form.department_id,
  (val) => {
    if (!val) {
      form.shift_ids = []
      return
    }
    form.shift_ids = shiftsForDepartment(val).map((s) => String(s.id))
  }
)

const submit = () => {
  form.post(route('hr.smjene.dodjela.store'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <AppLayout title="Dodjela smjena odjelu">
    <Head title="Dodjela smjena odjelu" />

    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between">
        <Link
          :href="route('sector.hr')"
          class="inline-flex items-center px-3 py-2 bg-white text-gray-700 border border-gray-200 rounded-md text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
        >
          Nazad na HR
        </Link>
      </div>

      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Dodjela smjena odjelu</h1>
        <p class="text-sm text-gray-500">Smjene se određuju po odjelu radnika (employees.dept → shifts.department_id).</p>
      </div>

      <div v-if="Object.keys(form.errors || {}).length" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        Provjerite označena polja i pokušajte ponovo.
      </div>

      <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
        <form @submit.prevent="submit">
          <div class="px-6 py-5 border-b border-gray-200 bg-white space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Pretraga odjela</label>
              <input
                v-model="departmentSearch"
                type="text"
                class="mt-1 block w-full rounded-md shadow-sm text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Pretraži odjel…"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Odjel <span class="text-red-600">*</span></label>
              <select v-model="form.department_id" :class="fieldClass('department_id')">
                <option value="">-- odaberi --</option>
                <option v-for="d in filteredDepartments" :key="d.id" :value="String(d.id)">
                  {{ d.name }}
                </option>
              </select>
              <p v-if="form.errors.department_id" class="text-sm text-red-600 mt-1">{{ form.errors.department_id }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Smjene</label>
              <div class="mt-1 rounded-md border border-gray-200 bg-white p-3 space-y-2" :class="!form.department_id ? 'opacity-50 pointer-events-none' : ''">
                <label v-for="s in shifts" :key="s.id" class="flex items-center gap-2 text-sm text-gray-700">
                  <input
                    type="checkbox"
                    :value="String(s.id)"
                    v-model="form.shift_ids"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  <span>{{ shiftLabel(s) }}</span>
                </label>

                <button
                  type="button"
                  class="mt-2 text-xs text-gray-600 underline"
                  @click="form.shift_ids = []"
                >
                  (Bez smjena)
                </button>
              </div>
              <p v-if="form.errors.shift_ids" class="text-sm text-red-600 mt-1">{{ form.errors.shift_ids }}</p>
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex justify-end">
            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition"
              :disabled="form.processing"
            >
              Sačuvaj
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
