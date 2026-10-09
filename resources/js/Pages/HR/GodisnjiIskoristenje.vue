<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import DialogModal from '@/Components/DialogModal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { Head, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
  employees: { type: Array, required: true },
  defaultYear: { type: Number, required: true },
  rows: { type: Array, required: true },
})

const employeeSearch = ref('')
const editingId = ref(null)
const formCard = ref(null)
const pinInput = ref(null)
let preserveDecisionSelection = false

const deleteModal = ref({
  show: false,
  id: null,
  label: '',
})

const deleteForm = useForm({
  pin: '',
})
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
  if (!preserveDecisionSelection) {
    form.annual_leave_decision_id = ''
  }
  preserveDecisionSelection = false
  loadDecisions()
})

watch(() => [form.date_from, form.date_to], () => {
  loadWorkingDays()
})

const isEditing = computed(() => editingId.value !== null)

const formatDisplayDate = (value) => {
  if (!value) return ''
  const match = String(value).trim().match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!match) return value
  return `${match[3]}-${match[2]}-${match[1]}`
}

const cancelEdit = () => {
  editingId.value = null
  form.clearErrors()
  form.reset()
  form.year = String(props.defaultYear ?? new Date().getFullYear())
  employeeSearch.value = ''
  workingDays.value = null
}

const startEdit = async (row) => {
  const nextEmployee = String(row.employee_id)
  const nextYear = String(row.year ?? props.defaultYear ?? new Date().getFullYear())
  const unchanged = String(form.employee_id) === nextEmployee && String(form.year) === nextYear

  preserveDecisionSelection = !unchanged
  editingId.value = row.id
  form.clearErrors()
  form.employee_id = nextEmployee
  form.year = nextYear
  form.annual_leave_decision_id = row.annual_leave_decision_id ? String(row.annual_leave_decision_id) : ''
  form.date_from = row.date_from || ''
  form.date_to = row.date_to || ''
  form.note = row.note || ''
  employeeSearch.value = row.employee_name || ''

  if (unchanged) {
    loadDecisions()
  }

  await nextTick()
  formCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const submit = () => {
  const options = {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      if (editingId.value) {
        cancelEdit()
      } else {
        form.reset('annual_leave_decision_id', 'date_from', 'date_to', 'note')
        workingDays.value = null
        loadDecisions()
      }
    },
  }

  if (editingId.value) {
    form.put(route('hr.godisnji.iskoristenje.update', editingId.value), options)
    return
  }

  form.post(route('hr.godisnji.iskoristenje.store'), options)
}

const openDeleteModal = (row) => {
  deleteForm.reset()
  deleteForm.clearErrors()
  deleteModal.value = {
    show: true,
    id: row.id,
    label: [
      row.employee_name || ('#' + row.employee_id),
      row.year ? (String(row.year) + '.') : null,
      row.decision_label || null,
      formatDisplayDate(row.date_from) && formatDisplayDate(row.date_to)
        ? `${formatDisplayDate(row.date_from)} – ${formatDisplayDate(row.date_to)}`
        : null,
    ].filter(Boolean).join(' · '),
  }
  nextTick(() => pinInput.value?.focus())
}

const closeDeleteModal = (force = false) => {
  if (!force && deleteForm.processing) return
  deleteModal.value = { show: false, id: null, label: '' }
  deleteForm.reset()
  deleteForm.clearErrors()
}

const submitDelete = () => {
  if (!deleteModal.value.id) return

  const deletedId = deleteModal.value.id

  deleteForm.delete(route('hr.godisnji.iskoristenje.destroy', deletedId), {
    preserveScroll: true,
    onSuccess: () => {
      if (editingId.value === deletedId) {
        cancelEdit()
      }
      closeDeleteModal(true)
    },
  })
}
</script>

<template>
  <AppLayout title="Iskorišteni godišnji">
    <Head title="Iskorišteni godišnji" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Unos iskorištenog godišnjeg odmora</h1>
        <p class="text-sm text-gray-500">Unos, izmjena i brisanje korištenja na osnovu izdatog rješenja.</p>
      </div>

      <div
        ref="formCard"
        class="bg-white rounded-lg shadow-sm p-6"
        :class="isEditing ? 'border border-indigo-200' : 'border border-gray-200'"
      >
        <div v-if="isEditing" class="mb-4 flex items-center justify-between gap-3">
          <div class="text-sm text-indigo-900">
            Uređujete iskorištenje <span class="font-semibold">#{{ editingId }}</span>.
          </div>
          <button type="button" class="text-sm font-medium text-indigo-700 hover:text-indigo-900" @click="cancelEdit">
            Otkaži
          </button>
        </div>

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
              {{ isEditing ? 'Spremi izmjene' : 'Sačuvaj' }}
            </PrimaryButton>

            <button
              type="button"
              class="text-sm text-gray-600 hover:text-gray-800"
              @click="isEditing ? cancelEdit() : form.reset()"
            >
              {{ isEditing ? 'Otkaži' : 'Reset' }}
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
        <div class="px-6 py-4 border-b">
          <h2 class="text-lg font-semibold text-gray-800">Zadnja iskorištenja</h2>
          <p class="text-sm text-gray-500">Prikaz zadnjih 50 unosa.</p>
        </div>

        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600 border-b">
              <th class="py-3 px-4">Radnik</th>
              <th class="py-3 px-4">Godina</th>
              <th class="py-3 px-4">Rješenje</th>
              <th class="py-3 px-4">Od</th>
              <th class="py-3 px-4">Do</th>
              <th class="py-3 px-4">Dani</th>
              <th class="py-3 px-4"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in props.rows"
              :key="row.id"
              class="border-b"
              :class="editingId === row.id ? 'bg-indigo-50/60' : ''"
            >
              <td class="py-2 px-4 text-gray-800">{{ row.employee_name ?? ('#' + row.employee_id) }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.year ?? '' }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.decision_label || '—' }}</td>
              <td class="py-2 px-4 text-gray-800 whitespace-nowrap">{{ formatDisplayDate(row.date_from) }}</td>
              <td class="py-2 px-4 text-gray-800 whitespace-nowrap">{{ formatDisplayDate(row.date_to) }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.days ?? '' }}</td>
              <td class="py-2 px-4 text-right whitespace-nowrap">
                <button
                  type="button"
                  class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                  @click="startEdit(row)"
                >
                  Uredi
                </button>
                <button
                  type="button"
                  class="ml-3 text-sm font-medium text-red-600 hover:text-red-800"
                  @click="openDeleteModal(row)"
                >
                  Obriši
                </button>
              </td>
            </tr>

            <tr v-if="props.rows.length === 0">
              <td colspan="7" class="py-3 px-4 text-sm text-gray-500">
                Nema podataka.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <DialogModal :show="deleteModal.show" max-width="md" @close="closeDeleteModal">
      <template #title>
        Brisanje iskorištenja
      </template>

      <template #content>
        <p>
          Trajno brišete
          <span class="font-semibold text-gray-800">{{ deleteModal.label || 'iskorištenje' }}</span>.
          Unesite šifru da potvrdite.
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
