<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed } from 'vue'

const props = defineProps({
  recentRows: { type: Array, default: () => [] },
  allowedStatuses: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
})

const form = useForm({
  status_code: 'P',
  from: '',
  to: '',
  note: '',
  scope: 'all',
  department_ids: [],
})

const selectedDepartmentCount = computed(() => form.department_ids.length)

const sortedDepartments = computed(() =>
  [...(props.departments || [])].sort((a, b) =>
    (a.name || '').localeCompare(b.name || '', 'bs', { sensitivity: 'base' })
  )
)

const allDepartmentsSelected = computed(() => {
  const total = sortedDepartments.value.length
  return total > 0 && form.department_ids.length === total
})

const submitLabel = computed(() => {
  if (form.scope === 'departments') {
    if (selectedDepartmentCount.value === 0) return 'Dodijeli status odjelima'
    if (selectedDepartmentCount.value === 1) return 'Dodijeli status odabranom odjelu'
    return `Dodijeli status (${selectedDepartmentCount.value} odjela)`
  }
  return 'Dodijeli status svima'
})

const infoText = computed(() => {
  if (form.scope === 'departments') {
    return 'Status se upisuje aktivnim radnicima u označenim odjelima i ažurira postojeći unos za isti datum.'
  }
  return 'Ova akcija automatski upisuje status svim aktivnim radnicima i ažurira postojeći unos za isti datum.'
})

const toggleAllDepartments = () => {
  if (allDepartmentsSelected.value) {
    form.department_ids = []
    return
  }

  form.department_ids = (sortedDepartments.value || []).map((d) => Number(d.id))
}

const onScopeChange = () => {
  form.clearErrors('department_ids')
  if (form.scope === 'all') {
    form.department_ids = []
  }
}

const submit = () => {
  if (form.scope === 'all') {
    form.department_ids = []
  }

  form.post(route('hr.statusi.masovno.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('from', 'to', 'note', 'department_ids')
      form.status_code = 'P'
      form.scope = 'all'
    },
  })
}
</script>

<template>
  <AppLayout title="Masovna dodjela statusa">
    <Head title="Masovna dodjela statusa" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Masovna dodjela statusa</h1>
        <p class="text-sm text-gray-500">
          Dodijelite isti status svim aktivnim radnicima ili samo odabranim odjelima, za jedan dan ili raspon dana.
        </p>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <form class="space-y-5" @submit.prevent="submit">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <InputLabel value="Status" />
              <select
                v-model="form.status_code"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
              >
                <option v-for="item in props.allowedStatuses" :key="item.code" :value="item.code">
                  {{ item.label }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.status_code" />
            </div>

            <div>
              <InputLabel value="Od datuma" />
              <TextInput v-model="form.from" type="date" class="mt-1 block w-full" required />
              <InputError class="mt-2" :message="form.errors.from" />
            </div>

            <div>
              <InputLabel value="Do datuma" />
              <TextInput v-model="form.to" type="date" class="mt-1 block w-full" required />
              <InputError class="mt-2" :message="form.errors.to" />
            </div>

            <div>
              <InputLabel value="Napomena (opcionalno)" />
              <TextInput v-model="form.note" type="text" class="mt-1 block w-full" placeholder="Npr. neradni dan" />
              <InputError class="mt-2" :message="form.errors.note" />
            </div>
          </div>

          <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
            <div>
              <InputLabel value="Obuhvat" />
              <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:gap-6">
                <label class="inline-flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                  <input
                    v-model="form.scope"
                    type="radio"
                    value="all"
                    class="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    @change="onScopeChange"
                  />
                  Svi aktivni radnici
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                  <input
                    v-model="form.scope"
                    type="radio"
                    value="departments"
                    class="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    @change="onScopeChange"
                  />
                  Po odjelima
                </label>
              </div>
              <InputError class="mt-2" :message="form.errors.scope" />
            </div>

            <div v-if="form.scope === 'departments'" class="space-y-3">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-gray-600">
                  Odaberite jedan ili više odjela.
                  <span class="font-medium text-gray-800">Označeno: {{ selectedDepartmentCount }}</span>
                </p>
                <button
                  type="button"
                  class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                  @click="toggleAllDepartments"
                >
                  {{ allDepartmentsSelected ? 'Poništi sve' : 'Označi sve' }}
                </button>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-2 gap-y-0 max-h-64 overflow-y-auto rounded-md border border-gray-200 bg-white p-2">
                <label
                  v-for="department in sortedDepartments"
                  :key="department.id"
                  class="inline-flex items-center gap-2 rounded px-1.5 py-0.5 text-sm text-gray-800 hover:bg-gray-50 cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :value="Number(department.id)"
                    v-model="form.department_ids"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  <span>{{ department.name }}</span>
                </label>

                <p v-if="sortedDepartments.length === 0" class="col-span-full text-sm text-gray-500 px-2 py-2">
                  Nema definisanih odjela.
                </p>
              </div>

              <InputError :message="form.errors.department_ids" />
            </div>
          </div>

          <div class="rounded-md border border-blue-200 bg-blue-50 text-blue-900 px-4 py-3 text-sm">
            {{ infoText }}
          </div>

          <div class="flex items-center gap-3">
            <PrimaryButton type="submit" :disabled="form.processing || (form.scope === 'departments' && selectedDepartmentCount === 0)">
              {{ submitLabel }}
            </PrimaryButton>

            <button
              type="button"
              class="text-sm text-gray-600 hover:text-gray-800"
              @click="form.reset(); form.status_code = 'P'; form.scope = 'all'; form.department_ids = []"
            >
              Reset
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
        <div class="px-6 py-4 border-b">
          <h2 class="text-lg font-semibold text-gray-800">Zadnji unosi</h2>
          <p class="text-sm text-gray-500">Prikaz zadnjih 120 stavki masovne dodjele.</p>
        </div>

        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600 border-b">
              <th class="py-3 px-4">Datum</th>
              <th class="py-3 px-4">Radnik</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Napomena</th>
              <th class="py-3 px-4">Kreirano</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in props.recentRows" :key="row.id" class="border-b">
              <td class="py-2 px-4 text-gray-800">{{ row.work_date }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.employee_name ?? ('#' + row.employee_id) }}</td>
              <td class="py-2 px-4 text-gray-800 font-semibold">{{ row.status_code }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.note ?? '' }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.created_at ?? '' }}</td>
            </tr>

            <tr v-if="props.recentRows.length === 0">
              <td colspan="5" class="py-3 px-4 text-sm text-gray-500">
                Nema podataka.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
