<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
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
  from: '',
  to: '',
  days: '',
  document_number: '',
  document_date: '',
  doctor: '',
  diagnosis_code: '',
  status: 'otvoreno',
  note: '',
})

const filteredEmployees = computed(() => {
  const q = String(employeeSearch.value ?? '').trim().toLowerCase()
  if (!q) return props.employees

  const selectedId = String(form.employee_id ?? '')

  const matches = (props.employees ?? []).filter((e) => {
    const name = String(e?.name ?? '').toLowerCase()
    return name.includes(q)
  })

  if (!selectedId) return matches

  const selected = (props.employees ?? []).find((e) => String(e?.id) === selectedId)
  if (!selected) return matches

  const alreadyIncluded = matches.some((e) => String(e?.id) === selectedId)
  return alreadyIncluded ? matches : [selected, ...matches]
})

const submit = () => {
  form.post(route('hr.bolovanja.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('from', 'to', 'days', 'document_number', 'document_date', 'doctor', 'diagnosis_code', 'note')
      form.status = 'otvoreno'
    },
  })
}
</script>

<template>
  <AppLayout title="Bolovanja">
    <Head title="Bolovanja" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Evidencija bolovanja</h1>
        <p class="text-sm text-gray-500">Unos bolovanja po radniku.</p>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <form class="space-y-5" @submit.prevent="submit">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
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
              <TextInput v-model="form.from" type="date" class="mt-1 block w-full" required />
              <InputError class="mt-2" :message="form.errors.from" />
            </div>

            <div>
              <InputLabel value="Do" />
              <TextInput v-model="form.to" type="date" class="mt-1 block w-full" required />
              <InputError class="mt-2" :message="form.errors.to" />
            </div>

            <div>
              <InputLabel value="Broj dana" />
              <TextInput v-model="form.days" type="number" step="1" min="0" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.days" />
            </div>

            <div>
              <InputLabel value="Broj doznake" />
              <TextInput v-model="form.document_number" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.document_number" />
            </div>

            <div>
              <InputLabel value="Datum doznake" />
              <TextInput v-model="form.document_date" type="date" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.document_date" />
            </div>

            <div>
              <InputLabel value="Doktor" />
              <TextInput v-model="form.doctor" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.doctor" />
            </div>

            <div>
              <InputLabel value="Šifra dijagnoze" />
              <TextInput v-model="form.diagnosis_code" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.diagnosis_code" />
            </div>

            <div>
              <InputLabel value="Status" />
              <select
                v-model="form.status"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option value="otvoreno">Otvoreno</option>
                <option value="zatvoreno">Zatvoreno</option>
              </select>
              <InputError class="mt-2" :message="form.errors.status" />
            </div>
          </div>

          <div>
            <InputLabel value="Napomena" />
            <textarea
              v-model="form.note"
              rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="form.errors.note" />
          </div>

          <div class="flex items-center gap-3">
            <PrimaryButton type="submit" :disabled="form.processing">
              Sačuvaj
            </PrimaryButton>

            <button
              type="button"
              class="text-sm text-gray-600 hover:text-gray-800"
              @click="form.reset()"
            >
              Reset
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
        <div class="px-6 py-4 border-b">
          <h2 class="text-lg font-semibold text-gray-800">Zadnja bolovanja</h2>
          <p class="text-sm text-gray-500">Prikaz zadnjih 50 unosa.</p>
        </div>

        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600 border-b">
              <th class="py-3 px-4">Radnik</th>
              <th class="py-3 px-4">Od</th>
              <th class="py-3 px-4">Do</th>
              <th class="py-3 px-4">Dani</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Doznaka</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in props.rows" :key="row.id" class="border-b">
              <td class="py-2 px-4 text-gray-800">{{ row.employee_name ?? ('#' + row.employee_id) }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.from }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.to }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.days ?? '' }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.status }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.document_number ?? '' }}</td>
            </tr>

            <tr v-if="props.rows.length === 0">
              <td colspan="6" class="py-3 px-4 text-sm text-gray-500">
                Nema podataka.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
