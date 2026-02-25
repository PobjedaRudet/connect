<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  employees: { type: Array, required: true },
  defaultYear: { type: Number, required: true },
})

const employeeSearch = ref('')
const decisions = ref([])
const decisionsLoading = ref(false)
const decisionsError = ref(null)

const workingDays = ref(null)
const workingDaysLoading = ref(false)

const form = useForm({
  employee_id: '',
  year: String(props.defaultYear ?? new Date().getFullYear()),
  annual_leave_decision_id: '',
  date_from: '',
  date_to: '',
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

async function loadDecisions() {
  decisionsError.value = null
  decisions.value = []
  workingDays.value = null

  const employeeId = Number(form.employee_id)
  const year = Number(form.year)
  if (!employeeId || !year) return

  decisionsLoading.value = true
  try {
    const { data } = await window.axios.get('/api/godisnji/decisions', {
      params: { employee_id: employeeId, year },
    })
    decisions.value = data?.decisions ?? []

    const selected = String(form.annual_leave_decision_id ?? '')
    if (selected && !decisions.value.some((d) => String(d.id) === selected)) {
      form.annual_leave_decision_id = ''
    }
  } catch (e) {
    decisionsError.value = e?.response?.data?.message ?? 'Greška pri učitavanju rješenja.'
  } finally {
    decisionsLoading.value = false
  }
}

async function loadWorkingDays() {
  workingDays.value = null
  const from = form.date_from
  const to = form.date_to
  if (!from || !to) return

  workingDaysLoading.value = true
  try {
    const { data } = await window.axios.get('/api/godisnji/working-days', {
      params: { from, to },
    })
    const n = Number(data?.working_days ?? 0)
    workingDays.value = Number.isFinite(n) ? Math.round(n) : 0
  } catch {
    workingDays.value = null
  } finally {
    workingDaysLoading.value = false
  }
}

watch(() => [form.employee_id, form.year], () => {
  form.annual_leave_decision_id = ''
  loadDecisions()
})

watch(() => [form.date_from, form.date_to], () => {
  loadWorkingDays()
})

const submit = () => {
  form.post(route('hr.godisnji.iskoristenje.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('annual_leave_decision_id', 'date_from', 'date_to', 'note')
      workingDays.value = null
      loadDecisions()
    },
  })
}
</script>

<template>
  <AppLayout title="Iskorišteni godišnji">
    <Head title="Iskorišteni godišnji" />

    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Unos iskorištenog godišnjeg odmora</h1>
          <p class="text-sm text-gray-500">Unos korištenja na osnovu izdatog rješenja.</p>
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

            <div class="sm:col-span-2">
              <InputLabel value="Rješenje" />
              <div v-if="decisionsError" class="text-sm text-red-600 mt-1">{{ decisionsError }}</div>
              <div v-else-if="decisionsLoading" class="text-sm text-gray-600 mt-1">Učitavanje rješenja...</div>

              <select
                v-model="form.annual_leave_decision_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                required
              >
                <option value="" disabled>
                  {{ (decisions?.length ?? 0) > 0 ? 'Odaberi rješenje...' : 'Nema rješenja za odabranu godinu.' }}
                </option>
                <option v-for="d in decisions" :key="d.id" :value="String(d.id)">
                  {{ d.label }}
                </option>
              </select>
              <InputError class="mt-2" :message="form.errors.annual_leave_decision_id" />
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
              <div v-if="workingDaysLoading" class="text-xs text-gray-500 mt-1">Računam dane...</div>
              <div v-else-if="workingDays !== null" class="text-xs text-gray-500 mt-1">Radni dani: {{ workingDays }}</div>
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
