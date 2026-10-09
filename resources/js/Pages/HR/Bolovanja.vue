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
import { computed, nextTick, ref } from 'vue'

const props = defineProps({
  employees: { type: Array, required: true },
  rows: { type: Array, required: true },
})

const employeeSearch = ref('')
const editingId = ref(null)
const formCard = ref(null)
const pinInput = ref(null)

const deleteModal = ref({
  show: false,
  id: null,
  label: '',
})

const deleteForm = useForm({
  pin: '',
})

const form = useForm({
  employee_id: '',
  from: '',
  to: '',
  days: '',
  document_number: '',
  document_date: '',
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

const formatDisplayDate = (value) => {
  if (!value) return ''
  const match = String(value).trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/)
  if (!match) return value
  const date = `${match[3]}-${match[2]}-${match[1]}`
  return match[4] ? `${date} ${match[4]}:${match[5]}` : date
}

const isEditing = computed(() => editingId.value !== null)

const statusLabel = (status) => {
  if (status === 'otvoreno') return 'Otvoreno'
  if (status === 'zatvoreno') return 'Zatvoreno'
  return status || ''
}

const cancelEdit = () => {
  editingId.value = null
  form.clearErrors()
  form.reset()
  form.status = 'otvoreno'
  employeeSearch.value = ''
}

const startEdit = async (row) => {
  editingId.value = row.id
  form.clearErrors()
  form.employee_id = String(row.employee_id)
  form.from = row.from || ''
  form.to = row.to || ''
  form.days = row.days !== null && row.days !== undefined ? String(row.days) : ''
  form.document_number = row.document_number || ''
  form.document_date = row.document_date || ''
  form.status = row.status || 'otvoreno'
  form.note = row.note || ''
  employeeSearch.value = row.employee_name || ''

  await nextTick()
  formCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      if (editingId.value) {
        cancelEdit()
      } else {
        form.reset('from', 'to', 'days', 'document_number', 'document_date', 'note')
        form.status = 'otvoreno'
      }
    },
  }

  if (editingId.value) {
    form.put(route('hr.bolovanja.update', editingId.value), options)
    return
  }

  form.post(route('hr.bolovanja.store'), options)
}

const openDeleteModal = (row) => {
  deleteForm.reset()
  deleteForm.clearErrors()
  deleteModal.value = {
    show: true,
    id: row.id,
    label: [
      row.employee_name || ('#' + row.employee_id),
      formatDisplayDate(row.from) && formatDisplayDate(row.to)
        ? `${formatDisplayDate(row.from)} – ${formatDisplayDate(row.to)}`
        : null,
      row.document_number ? ('br. ' + row.document_number) : null,
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

  deleteForm.delete(route('hr.bolovanja.destroy', deletedId), {
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
  <AppLayout title="Bolovanja">
    <Head title="Bolovanja" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">Evidencija bolovanja</h1>
        <p class="text-sm text-gray-500">Unos, izmjena i brisanje bolovanja po radniku.</p>
      </div>

      <div
        ref="formCard"
        class="bg-white rounded-lg shadow-sm p-6"
        :class="isEditing ? 'border border-indigo-200' : 'border border-gray-200'"
      >
        <div v-if="isEditing" class="mb-4 flex items-center justify-between gap-3">
          <div class="text-sm text-indigo-900">
            Uređujete bolovanje <span class="font-semibold">#{{ editingId }}</span>.
          </div>
          <button type="button" class="text-sm font-medium text-indigo-700 hover:text-indigo-900" @click="cancelEdit">
            Otkaži
          </button>
        </div>

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
              <td class="py-2 px-4 text-gray-800">{{ formatDisplayDate(row.from) }}</td>
              <td class="py-2 px-4 text-gray-800">{{ formatDisplayDate(row.to) }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.days ?? '' }}</td>
              <td class="py-2 px-4 text-gray-800">{{ statusLabel(row.status) }}</td>
              <td class="py-2 px-4 text-gray-800">{{ row.document_number ?? '' }}</td>
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
        Brisanje bolovanja
      </template>

      <template #content>
        <p>
          Trajno brišete
          <span class="font-semibold text-gray-800">{{ deleteModal.label || 'bolovanje' }}</span>.
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
