<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
  recentRows: { type: Array, default: () => [] },
  allowedStatuses: { type: Array, default: () => [] },
})

const form = useForm({
  status_code: 'P',
  from: '',
  to: '',
  note: '',
})

const submit = () => {
  form.post(route('hr.statusi.masovno.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('from', 'to', 'note')
      form.status_code = 'P'
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
        <p class="text-sm text-gray-500">Dodijelite isti status svim aktivnim radnicima za jedan dan ili raspon dana.</p>
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

          <div class="rounded-md border border-blue-200 bg-blue-50 text-blue-900 px-4 py-3 text-sm">
            Ova akcija automatski upisuje status svim aktivnim radnicima i azurira postojeci unos za isti datum.
          </div>

          <div class="flex items-center gap-3">
            <PrimaryButton type="submit" :disabled="form.processing">
              Dodijeli status svima
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
