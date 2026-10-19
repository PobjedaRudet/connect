<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed, ref } from 'vue'

const props = defineProps({
  employees: { type: Array, required: true },
  rows: { type: Array, required: true },
})

const employeeSearch = ref('')

const form = useForm({
  employee_id: '',
  date_from: '',
  date_to: '',
  reason: '',
})

const filteredEmployees = computed(() => {
  const q = employeeSearch.value.trim().toLowerCase()
  if (!q) return props.employees
  return props.employees.filter((e) => String(e.name || '').toLowerCase().includes(q))
})

const formatDisplayDate = (value) => {
  if (!value) return ''
  const [y, m, d] = String(value).split('-')
  if (!y || !m || !d) return value
  return `${d}.${m}.${y}`
}

const submit = () => {
  form.post(route('hr.putni-nalozi.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}

const remove = (row) => {
  const name = row.employee_name || ('#' + row.employee_id)
  if (!window.confirm(`Obrisati putni nalog za ${name} (${formatDisplayDate(row.date_from)} – ${formatDisplayDate(row.date_to)})?`)) {
    return
  }

  router.delete(route('hr.putni-nalozi.destroy', row.id), { preserveScroll: true })
}
</script>

<template>
  <AppLayout title="Putni nalozi">
    <Head title="Putni nalozi" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Putni nalog</h1>
        <p class="text-sm text-gray-500">
          Jedan nalog pokriva period od polaska do povratka. Za svaki radni dan šihterica prikazuje 8.
        </p>
      </div>

      <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <form class="space-y-5" @submit.prevent="submit">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <InputLabel value="Radnik" />
              <TextInput
                v-model="employeeSearch"
                type="text"
                class="mt-1 block w-full"
                placeholder="Pretraga po imenu ili prezimenu"
              />
              <select
                v-model="form.employee_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
              >
                <option value="" disabled>Odaberi radnika...</option>
                <option v-for="e in filteredEmployees" :key="e.id" :value="String(e.id)">
                  {{ e.name }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.employee_id" />
            </div>

            <div>
              <InputLabel value="Od" />
              <TextInput v-model="form.date_from" type="date" class="mt-1 block w-full" required />
              <InputError class="mt-2" :message="form.errors.date_from" />
            </div>

            <div>
              <InputLabel value="Do" />
              <TextInput v-model="form.date_to" type="date" class="mt-1 block w-full" required />
              <InputError class="mt-2" :message="form.errors.date_to" />
            </div>
          </div>

          <div>
            <InputLabel value="Razlog" />
            <TextInput
              v-model="form.reason"
              type="text"
              class="mt-1 block w-full"
              placeholder="Broj ili opis naloga"
              required
            />
            <InputError class="mt-2" :message="form.errors.reason" />
          </div>

          <div class="flex items-center gap-3">
            <PrimaryButton type="submit" :disabled="form.processing">Sačuvaj</PrimaryButton>
            <button type="button" class="text-sm text-gray-600 hover:text-gray-800" @click="form.reset()">
              Reset
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
        <div class="px-6 py-4 border-b">
          <h2 class="text-lg font-semibold text-gray-800">Putni nalozi</h2>
        </div>

        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600 border-b">
              <th class="py-3 px-4">Radnik</th>
              <th class="py-3 px-4">Od</th>
              <th class="py-3 px-4">Do</th>
              <th class="py-3 px-4">Razlog</th>
              <th class="py-3 px-4"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="5" class="py-6 px-4 text-gray-500">Nema unesenih putnih naloga.</td>
            </tr>
            <tr v-for="row in rows" :key="row.id" class="border-b">
              <td class="py-2 px-4 text-gray-800">{{ row.employee_name ?? ('#' + row.employee_id) }}</td>
              <td class="py-2 px-4 text-gray-800 whitespace-nowrap">{{ formatDisplayDate(row.date_from) }}</td>
              <td class="py-2 px-4 text-gray-800 whitespace-nowrap">{{ formatDisplayDate(row.date_to) }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.reason }}</td>
              <td class="py-2 px-4 text-right">
                <button type="button" class="text-sm font-medium text-red-600 hover:text-red-800" @click="remove(row)">
                  Obriši
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
