<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import DialogModal from '@/Components/DialogModal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { computed, nextTick, ref } from 'vue'

const props = defineProps({
  employees: { type: Array, required: true },
  decisions: { type: Object, required: true },
})

const employeeSearch = ref('')
const editingId = ref(null)
const formCard = ref(null)
const pinInput = ref(null)

const partLabels = {
  ljetni: 'Ljetni',
  zimski: 'Zimski',
  jednodnevni: 'Jednodnevni',
  ostalo: 'Ostalo',
}

const rows = computed(() => props.decisions?.data ?? [])
const paginationLinks = computed(() => props.decisions?.links ?? [])
const pageMeta = computed(() => ({
  from: props.decisions?.from ?? null,
  to: props.decisions?.to ?? null,
  total: props.decisions?.total ?? 0,
  current: props.decisions?.current_page ?? 1,
  last: props.decisions?.last_page ?? 1,
}))

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

const isEditing = computed(() => editingId.value !== null)

const form = useForm({
  employee_id: '',
  year: String(new Date().getFullYear()),
  part: 'ljetni',
  decision_number: '',
  decision_date: '',
  valid_from: '',
  valid_to: '',
  granted_days: '',
  note: '',
})

const deleteModal = ref({
  show: false,
  id: null,
  label: '',
  usedDays: 0,
})

const deleteForm = useForm({
  pin: '',
})

const formatPart = (part) => partLabels[part] || part || '—'

const cancelEdit = () => {
  editingId.value = null
  form.clearErrors()
  form.reset()
  form.part = 'ljetni'
  form.year = String(new Date().getFullYear())
  employeeSearch.value = ''
}

const startEdit = async (row) => {
  editingId.value = row.id
  form.clearErrors()
  form.employee_id = String(row.employee_id)
  form.year = String(row.year ?? new Date().getFullYear())
  form.part = row.part || 'ljetni'
  form.decision_number = row.decision_number || ''
  form.decision_date = row.decision_date || ''
  form.valid_from = row.valid_from || ''
  form.valid_to = row.valid_to || ''
  form.granted_days = row.granted_days !== null && row.granted_days !== undefined
    ? String(row.granted_days)
    : ''
  form.note = row.note || ''
  employeeSearch.value = row.employee_name || ''

  await nextTick()
  formCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const submitEdit = () => {
  if (!editingId.value) return

  form.put(route('hr.godisnji.rjesenja.update', editingId.value) + `?page=${pageMeta.value.current}`, {
    preserveScroll: true,
    onSuccess: () => {
      cancelEdit()
    },
  })
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
      formatPart(row.part),
      row.decision_number ? ('br. ' + row.decision_number) : null,
    ].filter(Boolean).join(' · '),
    usedDays: Number(row.used_days || 0),
  }
  nextTick(() => pinInput.value?.focus())
}

const closeDeleteModal = (force = false) => {
  if (!force && deleteForm.processing) return
  deleteModal.value = { show: false, id: null, label: '', usedDays: 0 }
  deleteForm.reset()
  deleteForm.clearErrors()
}

const submitDelete = () => {
  if (!deleteModal.value.id) return

  const deletedId = deleteModal.value.id

  deleteForm.delete(route('hr.godisnji.rjesenja.destroy', deletedId) + `?page=${pageMeta.value.current}`, {
    preserveScroll: true,
    onSuccess: () => {
      if (editingId.value === deletedId) {
        cancelEdit()
      }
      closeDeleteModal(true)
    },
  })
}

const goToPage = (url) => {
  if (!url) return
  router.get(url, {}, { preserveScroll: true, preserveState: true })
}

const linkLabel = (label) => String(label ?? '')
  .replace(/&laquo;/g, '«')
  .replace(/&raquo;/g, '»')
  .replace(/&hellip;/g, '…')
</script>

<template>
  <AppLayout title="Lista rješenja godišnjeg">
    <Head title="Lista rješenja godišnjeg" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Lista rješenja godišnjeg odmora</h1>
          <p class="text-sm text-gray-500">
            Pregled, uređivanje i brisanje. Prikaz po {{ decisions.per_page || 20 }} rješenja.
          </p>
        </div>
        <Link
          :href="route('hr.godisnji.rjesenja')"
          class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
        >
          Novo rješenje
        </Link>
      </div>

      <div
        v-if="isEditing"
        ref="formCard"
        class="bg-white border border-indigo-200 rounded-lg shadow-sm p-6"
      >
        <div class="mb-4 flex items-center justify-between gap-3">
          <div class="text-sm text-indigo-900">
            Uređujete rješenje <span class="font-semibold">#{{ editingId }}</span>.
          </div>
          <button type="button" class="text-sm font-medium text-indigo-700 hover:text-indigo-900" @click="cancelEdit">
            Otkaži
          </button>
        </div>

        <form class="space-y-5" @submit.prevent="submitEdit">
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
              {{ form.processing ? 'Spremam...' : 'Spremi izmjene' }}
            </PrimaryButton>
            <button type="button" class="text-sm text-gray-600 hover:text-gray-800" @click="cancelEdit">
              Otkaži
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Rješenja</h2>
            <p class="text-sm text-gray-500">
              <template v-if="pageMeta.total">
                Prikaz {{ pageMeta.from }}–{{ pageMeta.to }} od {{ pageMeta.total }}
              </template>
              <template v-else>
                Nema rješenja.
              </template>
            </p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-gray-600 border-b bg-gray-50">
                <th class="py-3 px-4">Radnik</th>
                <th class="py-3 px-4">Godina</th>
                <th class="py-3 px-4">Dio</th>
                <th class="py-3 px-4">Broj</th>
                <th class="py-3 px-4">Važi</th>
                <th class="py-3 px-4">Odobreno</th>
                <th class="py-3 px-4">Iskorišteno</th>
                <th class="py-3 px-4"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                class="border-b"
                :class="editingId === row.id ? 'bg-indigo-50/60' : ''"
              >
                <td class="py-2 px-4 text-gray-800">{{ row.employee_name ?? ('#' + row.employee_id) }}</td>
                <td class="py-2 px-4 text-gray-800">{{ row.year }}</td>
                <td class="py-2 px-4 text-gray-800">{{ formatPart(row.part) }}</td>
                <td class="py-2 px-4 text-gray-800">{{ row.decision_number || '—' }}</td>
                <td class="py-2 px-4 text-gray-800 whitespace-nowrap">
                  <template v-if="row.valid_from || row.valid_to">
                    {{ row.valid_from || '?' }} – {{ row.valid_to || '?' }}
                  </template>
                  <template v-else>—</template>
                </td>
                <td class="py-2 px-4 text-gray-800">{{ row.granted_days }}</td>
                <td class="py-2 px-4 text-gray-800">{{ row.used_days }}</td>
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

              <tr v-if="!rows.length">
                <td colspan="8" class="py-6 px-4 text-center text-sm text-gray-500">
                  Nema podataka.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="pageMeta.last > 1"
          class="px-4 py-3 border-t border-gray-200 bg-gray-50 flex flex-wrap items-center justify-center gap-1"
        >
          <button
            v-for="(link, idx) in paginationLinks"
            :key="idx"
            type="button"
            class="min-w-[2.25rem] rounded-md px-2.5 py-1.5 text-sm border"
            :class="link.active
              ? 'border-indigo-600 bg-indigo-600 text-white'
              : (link.url
                ? 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100'
                : 'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed')"
            :disabled="!link.url || link.active"
            @click="goToPage(link.url)"
            v-html="linkLabel(link.label)"
          />
        </div>
      </div>
    </div>

    <DialogModal :show="deleteModal.show" max-width="md" @close="closeDeleteModal">
      <template #title>
        Brisanje rješenja
      </template>

      <template #content>
        <p>
          Trajno brišete
          <span class="font-semibold text-gray-800">{{ deleteModal.label || 'rješenje' }}</span>.
          Unesite šifru da potvrdite.
        </p>
        <p
          v-if="deleteModal.usedDays > 0"
          class="mt-2 text-xs text-amber-700"
        >
          Ovo rješenje ima {{ deleteModal.usedDays }} iskorištenih dana — i ta iskorištenja će biti obrisana.
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
