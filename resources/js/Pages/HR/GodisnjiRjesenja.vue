<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed, ref } from 'vue'

const props = defineProps({
  employees: { type: Array, required: true },
  defaultYear: { type: Number, required: true },
})

const employeeSearch = ref('')

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

const form = useForm({
  employee_id: '',
  year: String(props.defaultYear ?? new Date().getFullYear()),
  part: 'ljetni',
  decision_number: '',
  decision_date: '',
  valid_from: '',
  valid_to: '',
  granted_days: '',
  note: '',
})

const submit = () => {
  form.post(route('hr.godisnji.rjesenja.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('decision_number', 'decision_date', 'valid_from', 'valid_to', 'granted_days', 'note')
      form.part = 'ljetni'
    },
  })
}
</script>

<template>
  <AppLayout title="Rješenja godišnjeg">
    <Head title="Rješenja godišnjeg" />

    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Unos rješenja za godišnji odmor</h1>
          <p class="text-sm text-gray-500">Kreiranje rješenja (ljetni / zimski / jednodnevni) po radniku.</p>
        </div>

        <Link :href="route('sector.hr')" class="text-sm text-indigo-600 hover:text-indigo-500">Nazad na HR sektor</Link>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
        <form class="space-y-5" @submit.prevent="submit">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
              <InputLabel value="Godina" />
              <TextInput v-model="form.year" type="number" class="mt-1 block w-full" min="2000" max="2100" required />
              <InputError class="mt-2" :message="form.errors.year" />
            </div>

            <div>
              <InputLabel value="Dio" />
              <select
                v-model="form.part"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
              >
                <option value="ljetni">Ljetni</option>
                <option value="zimski">Zimski</option>
                <option value="jednodnevni">Jednodnevni</option>
                <option value="ostalo">Ostalo</option>
              </select>
              <InputError class="mt-2" :message="form.errors.part" />
            </div>

            <div>
              <InputLabel value="Broj rješenja" />
              <TextInput v-model="form.decision_number" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.decision_number" />
            </div>

            <div>
              <InputLabel value="Datum rješenja" />
              <TextInput v-model="form.decision_date" type="date" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.decision_date" />
            </div>

            <div>
              <InputLabel value="Važi od" />
              <TextInput v-model="form.valid_from" type="date" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.valid_from" />
            </div>

            <div>
              <InputLabel value="Važi do" />
              <TextInput v-model="form.valid_to" type="date" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="form.errors.valid_to" />
            </div>

            <div>
              <InputLabel value="Dani (odobreno)" />
              <TextInput v-model="form.granted_days" type="number" step="1" min="0" class="mt-1 block w-full" required />
              <InputError class="mt-2" :message="form.errors.granted_days" />
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
    </div>
  </AppLayout>
</template>
