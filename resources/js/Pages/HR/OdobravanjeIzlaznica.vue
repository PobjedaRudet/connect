<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import DialogModal from '@/Components/DialogModal.vue'
import DangerButton from '@/Components/DangerButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import DateTimeInput from '@/Components/DateTimeInput.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const MANUAL_REASON = 'Ručni unos zbog zaboravljenog skena'

const props = defineProps({
  passes: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
  workdayEndTime: { type: String, default: '15:00' },
})

const formatDateTime = (value) => {
  if (!value) return '—'
  const date = new Date(String(value).trim().replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return value
  const pad = (n) => String(n).padStart(2, '0')
  return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const formatDuration = (minutes) => {
  if (minutes === null || minutes === undefined) return '—'
  const total = Number(minutes)
  if (Number.isNaN(total)) return '—'
  const hrs = Math.floor(total / 60)
  const mins = Math.max(0, total - hrs * 60)
  const paddedMins = mins.toString().padStart(2, '0')
  return `${hrs}:${paddedMins} h`
}

const updateType = (passId, type) => {
  router.patch(route('passes.updateType', passId), { type }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const confirmPass = (passId, type) => {
  router.post(route('passes.confirm', passId), { type }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const toInputDateTime = (value) => {
  if (!value) return ''
  const match = String(value).trim().match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/)
  return match ? `${match[1]}T${match[2]}` : ''
}

const editing = ref(null)
const deleteTarget = ref(null)

const editForm = useForm({
  type: 'privatni',
  reason: '',
  start_time: '',
  end_time: '',
})

const deleteForm = useForm({})

const openEdit = (passItem) => {
  editing.value = passItem
  editForm.clearErrors()
  editForm.type = passItem.type || 'privatni'
  editForm.reason = passItem.reason || ''
  editForm.start_time = toInputDateTime(passItem.start_time)
  editForm.end_time = toInputDateTime(passItem.end_time)
}

const closeEdit = () => {
  if (editForm.processing) return
  editing.value = null
  editForm.clearErrors()
}

const submitEdit = () => {
  if (!editing.value) return

  editForm.put(route('passes.update', editing.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      editing.value = null
    },
  })
}

const openDelete = (passItem) => {
  deleteTarget.value = passItem
  deleteForm.clearErrors()
}

const closeDelete = () => {
  if (deleteForm.processing) return
  deleteTarget.value = null
}

const submitDelete = () => {
  if (!deleteTarget.value) return

  deleteForm.delete(route('passes.destroy', deleteTarget.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      deleteTarget.value = null
    },
  })
}

const employeeSearch = ref('')

const createForm = useForm({
  employee_id: '',
  type: 'privatni',
  reason: MANUAL_REASON,
  start_time: '',
  end_time: '',
})

const filteredEmployees = computed(() => {
  const query = employeeSearch.value.trim().toLowerCase()
  const list = props.employees ?? []
  if (!query) return list

  const selectedId = String(createForm.employee_id ?? '')
  const matches = list.filter((employee) => String(employee.name || '').toLowerCase().includes(query))
  const selected = list.find((employee) => String(employee.id) === selectedId)
  if (selected && !matches.some((employee) => String(employee.id) === selectedId)) {
    return [selected, ...matches]
  }

  return matches
})

const submitCreate = () => {
  createForm.post(route('passes.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset()
      employeeSearch.value = ''
    },
  })
}
</script>

<template>
  <AppLayout title="Odobravanje izlaznica">
    <Head title="Odobravanje izlaznica" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Odobravanje izlaznica</h1>
          <p class="text-sm text-gray-500">Privatne izlaznice računaju trajanje do povratka (maks. do kraja radnog vremena {{ workdayEndTime }}).</p>
        </div>
        <Link
          :href="route('passes.approved')"
          class="inline-flex items-center px-3 py-2 bg-sky-50 text-sky-700 border border-sky-200 rounded-md text-sm font-medium hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1"
        >
          Pregled odobrenih izlaznica
        </Link>
      </div>

      <form class="bg-white shadow rounded-lg border border-gray-200 p-5 mb-6 space-y-4" @submit.prevent="submitCreate">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Ručni unos izlaznice</h2>
          <p class="text-sm text-gray-500">
            Za radnika koji je bio prijavljen, a zaboravio se provući. Upišite i izlazak i povratak. Sati prijave se ne mijenjaju.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <InputLabel value="Radnik" />
            <input
              v-model="employeeSearch"
              type="text"
              placeholder="Pretraga po imenu ili prezimenu"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <select
              v-model="createForm.employee_id"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="" disabled>Odaberi radnika...</option>
              <option v-for="employee in filteredEmployees" :key="employee.id" :value="String(employee.id)">
                {{ employee.name }}
              </option>
            </select>
            <InputError class="mt-2" :message="createForm.errors.employee_id" />
          </div>

          <div>
            <InputLabel value="Tip" />
            <select
              v-model="createForm.type"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="privatni">Privatna</option>
              <option value="službeni">Službena</option>
            </select>
            <InputError class="mt-2" :message="createForm.errors.type" />
          </div>

          <div>
            <InputLabel value="Razlog" />
            <input
              v-model="createForm.reason"
              type="text"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="createForm.errors.reason" />
          </div>

          <div>
            <InputLabel value="Izlazak" />
            <DateTimeInput
              v-model="createForm.start_time"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="createForm.errors.start_time" />
          </div>

          <div>
            <InputLabel value="Povratak" />
            <DateTimeInput
              v-model="createForm.end_time"
              required
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="createForm.errors.end_time" />
          </div>
        </div>

        <div>
          <PrimaryButton type="submit" :disabled="createForm.processing || employees.length === 0">
            Unesi izlaznicu
          </PrimaryButton>
          <p v-if="employees.length === 0" class="mt-2 text-sm text-gray-500">
            Nema radnika za koje možete unijeti izlaznicu.
          </p>
        </div>
      </form>

      <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <th class="px-4 py-3">Radnik</th>
              <th class="px-4 py-3">Razlog</th>
              <th class="px-4 py-3">Izlazak</th>
              <th class="px-4 py-3">Povratak</th>
              <th class="px-4 py-3">Trajanje</th>
              <th class="px-4 py-3">Odobrena</th>
              <th class="px-4 py-3">Tip</th>
              <th class="px-4 py-3 text-right">Akcije</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="passItem in passes" :key="passItem.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-800">{{ passItem.employee_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ passItem.reason || '—' }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDateTime(passItem.start_time) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDateTime(passItem.end_time) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ formatDuration(passItem.duration_minutes) }}</td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <span :class="passItem.approved ? 'text-emerald-700' : 'text-amber-700'">
                  {{ passItem.approved ? 'Da' : 'Ne' }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <select
                  :value="passItem.type"
                  class="border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                  @change="(e) => updateType(passItem.id, e.target.value)"
                >
                  <option value="privatni">Privatna</option>
                  <option value="službeni">Službena</option>
                </select>
              </td>
              <td class="px-4 py-3 text-sm text-right whitespace-nowrap space-x-2">
                <button
                  type="button"
                  class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                  @click="openEdit(passItem)"
                >
                  Uredi
                </button>
                <button
                  type="button"
                  class="text-sm font-medium text-red-600 hover:text-red-800"
                  @click="openDelete(passItem)"
                >
                  Obriši
                </button>
                <button
                  class="inline-flex items-center px-3 py-2 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:opacity-50"
                  :disabled="passItem.approved"
                  @click="confirmPass(passItem.id, passItem.type)"
                >
                  Odobri
                </button>
              </td>
            </tr>
            <tr v-if="passes.length === 0">
              <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">Trenutno nema izlaznica za odobravanje.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <DialogModal :show="editing !== null" max-width="lg" @close="closeEdit">
      <template #title>
        Izmjena izlaznice
      </template>

      <template #content>
        <p v-if="editing" class="mb-4">
          {{ editing.employee_name }}
        </p>

        <form id="pass-edit-form" class="space-y-4" @submit.prevent="submitEdit">
          <div>
            <InputLabel value="Tip" />
            <select
              v-model="editForm.type"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="privatni">Privatna</option>
              <option value="službeni">Službena</option>
            </select>
            <InputError class="mt-2" :message="editForm.errors.type" />
          </div>

          <div>
            <InputLabel value="Razlog" />
            <textarea
              v-model="editForm.reason"
              rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-2" :message="editForm.errors.reason" />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <InputLabel value="Izlazak" />
              <DateTimeInput
                v-model="editForm.start_time"
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
              <InputError class="mt-2" :message="editForm.errors.start_time" />
            </div>

            <div>
              <InputLabel value="Povratak" />
              <DateTimeInput
                v-model="editForm.end_time"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
              <InputError class="mt-2" :message="editForm.errors.end_time" />
            </div>
          </div>
        </form>
      </template>

      <template #footer>
        <SecondaryButton :disabled="editForm.processing" @click="closeEdit">
          Otkaži
        </SecondaryButton>

        <PrimaryButton
          type="button"
          class="ms-3"
          :disabled="editForm.processing"
          @click="submitEdit"
        >
          Spremi
        </PrimaryButton>
      </template>
    </DialogModal>

    <DialogModal :show="deleteTarget !== null" max-width="md" @close="closeDelete">
      <template #title>
        Brisanje izlaznice
      </template>

      <template #content>
        <p>
          Trajno brišete izlaznicu za
          <span class="font-semibold text-gray-800">{{ deleteTarget?.employee_name || 'radnika' }}</span>.
        </p>
      </template>

      <template #footer>
        <SecondaryButton :disabled="deleteForm.processing" @click="closeDelete">
          Otkaži
        </SecondaryButton>

        <DangerButton
          class="ms-3"
          :class="{ 'opacity-40': deleteForm.processing }"
          :disabled="deleteForm.processing"
          @click="submitDelete"
        >
          Obriši
        </DangerButton>
      </template>
    </DialogModal>
  </AppLayout>
</template>
