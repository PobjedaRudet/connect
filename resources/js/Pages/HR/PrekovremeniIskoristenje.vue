<script setup>
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  employees: { type: Array, required: true },
  defaultDate: { type: String, required: true },
  usageTypes: { type: Array, required: true },
})

const employeeSearch = ref('')
const availability = ref(null)
const availabilityLoading = ref(false)
const availabilityError = ref(null)
const localDurationError = ref(null)

const form = useForm({
  employee_id: '',
  usage_date: props.defaultDate,
  duration: '',
  usage_type: props.usageTypes?.[0]?.value ?? 'slobodni_sati',
  note: '',
})

const filteredEmployees = computed(() => {
  const q = String(employeeSearch.value ?? '').trim().toLowerCase()
  if (!q) return props.employees

  const selectedId = String(form.employee_id ?? '')
  const matches = (props.employees ?? []).filter((employee) =>
    String(employee?.name ?? '').toLowerCase().includes(q)
  )

  if (!selectedId) return matches

  const selected = (props.employees ?? []).find((employee) => String(employee?.id) === selectedId)
  if (!selected) return matches

  return matches.some((employee) => String(employee?.id) === selectedId)
    ? matches
    : [selected, ...matches]
})

const requestedMinutes = computed(() => parseDuration(form.duration))

watch([() => form.employee_id, () => form.usage_date, requestedMinutes], () => {
  loadAvailability()
})

function parseDuration(value) {
  const raw = String(value ?? '').trim()
  if (!raw) return null

  const match = raw.match(/^(\d+):(\d{2})$/)
  if (!match) return null

  const hours = Number(match[1])
  const minutes = Number(match[2])

  if (!Number.isFinite(hours) || !Number.isFinite(minutes) || minutes >= 60) {
    return null
  }

  return (hours * 60) + minutes
}

async function loadAvailability() {
  availability.value = null
  availabilityError.value = null

  const employeeId = Number(form.employee_id)
  if (!employeeId || !form.usage_date) return

  availabilityLoading.value = true
  try {
    const params = {
      employee_id: employeeId,
      usage_date: form.usage_date,
    }

    if (requestedMinutes.value && requestedMinutes.value > 0) {
      params.minutes_requested = requestedMinutes.value
    }

    const { data } = await window.axios.get(route('api.prekovremeni.balance'), { params })
    availability.value = data
  } catch (error) {
    availabilityError.value = error?.response?.data?.message ?? 'Greška pri učitavanju raspoloživih sati.'
  } finally {
    availabilityLoading.value = false
  }
}

function submit() {
  localDurationError.value = null

  if (!requestedMinutes.value || requestedMinutes.value <= 0) {
    localDurationError.value = 'Unesite trajanje u formatu HH:MM.'
    return
  }

  form
    .transform((data) => ({
      employee_id: Number(data.employee_id),
      usage_date: data.usage_date,
      minutes_used: requestedMinutes.value,
      usage_type: data.usage_type,
      note: data.note?.trim() ? data.note.trim() : null,
    }))
    .post(route('hr.prekovremeni.iskoristenje.store'), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset('duration', 'note')
        form.usage_type = props.usageTypes?.[0]?.value ?? 'slobodni_sati'
        localDurationError.value = null
        loadAvailability()
      },
    })
}
</script>

<template>
  <AppLayout title="Iskorištenje prekovremenih">
    <Head title="Iskorištenje prekovremenih" />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Unos iskorištenih prekovremenih sati</h1>
          <p class="text-sm text-gray-500">Potrošnja se automatski raspoređuje FIFO na najstarije raspoložive prekovremene.</p>
        </div>

        <div class="flex items-center gap-3">
          <Link :href="route('hr.prekovremeni-sati')" class="text-sm text-indigo-600 hover:text-indigo-500">
            Pregled prekovremenih
          </Link>
          <Link :href="route('sector.hr')" class="text-sm text-gray-600 hover:text-gray-800">
            Nazad na HR
          </Link>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
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
                  <option v-for="employee in filteredEmployees" :key="employee.id" :value="String(employee.id)">
                    {{ employee.name }}
                  </option>
                </select>
                <InputError class="mt-2" :message="form.errors.employee_id" />
              </div>

              <div>
                <InputLabel value="Datum korištenja" />
                <TextInput v-model="form.usage_date" type="date" class="mt-1 block w-full" required />
                <InputError class="mt-2" :message="form.errors.usage_date" />
              </div>

              <div>
                <InputLabel value="Trajanje (HH:MM)" />
                <TextInput v-model="form.duration" type="text" class="mt-1 block w-full" placeholder="npr. 2:30" required />
                <div v-if="requestedMinutes !== null" class="mt-1 text-xs text-gray-500">
                  Ukupno minuta: {{ requestedMinutes }}
                </div>
                <InputError class="mt-2" :message="localDurationError || form.errors.minutes_used" />
              </div>

              <div class="sm:col-span-2">
                <InputLabel value="Način korištenja" />
                <select
                  v-model="form.usage_type"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  required
                >
                  <option v-for="type in usageTypes" :key="type.value" :value="type.value">
                    {{ type.label }}
                  </option>
                </select>
                <InputError class="mt-2" :message="form.errors.usage_type" />
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
                Sačuvaj iskorištenje
              </PrimaryButton>

              <button
                type="button"
                class="text-sm text-gray-600 hover:text-gray-800"
                @click="form.reset('duration', 'note')"
              >
                Reset trajanja
              </button>
            </div>
          </form>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 space-y-4">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Raspoloživo stanje</h2>
            <p class="text-sm text-gray-500">Pregled preostalih prekovremenih do odabranog datuma.</p>
          </div>

          <div v-if="availabilityError" class="text-sm text-red-600">
            {{ availabilityError }}
          </div>

          <div v-else-if="availabilityLoading" class="text-sm text-gray-600">
            Učitavanje raspoloživih sati...
          </div>

          <div v-else-if="availability" class="space-y-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
              <div class="text-xs uppercase tracking-wide text-gray-500">Ukupno raspoloživo</div>
              <div class="text-2xl font-semibold text-gray-800">{{ availability.available_display }}</div>
            </div>

            <div v-if="availability.preview" class="rounded-lg border px-4 py-3" :class="availability.preview.is_possible ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50'">
              <div class="text-sm font-semibold text-gray-800">FIFO raspodjela</div>
              <div class="text-xs text-gray-600 mt-1">
                Traženo: {{ availability.preview.requested_display }}
                <span v-if="!availability.preview.is_possible"> | Nedostaje: {{ availability.preview.shortage_display }}</span>
              </div>

              <div v-if="availability.preview.allocations.length > 0" class="mt-3 space-y-2 text-sm text-gray-700">
                <div v-for="allocation in availability.preview.allocations" :key="allocation.attendance_overtime_id" class="flex items-center justify-between gap-3">
                  <span>{{ allocation.work_date }}</span>
                  <span class="font-medium">{{ allocation.allocated_display }}</span>
                </div>
              </div>
            </div>

            <div>
              <div class="text-sm font-semibold text-gray-800 mb-2">Raspoloživi zapisi</div>
              <div v-if="availability.slots.length === 0" class="text-sm text-gray-500">
                Nema raspoloživih prekovremenih prije odabranog datuma.
              </div>
              <div v-else class="space-y-2 max-h-72 overflow-auto pr-1">
                <div v-for="slot in availability.slots" :key="slot.attendance_overtime_id" class="rounded-lg border border-gray-200 px-3 py-2 text-sm">
                  <div class="flex items-center justify-between gap-3 text-gray-800">
                    <span class="font-medium">{{ slot.work_date }}</span>
                    <span>{{ slot.remaining_display }}</span>
                  </div>
                  <div class="mt-1 text-xs text-gray-500">
                    Zarađeno: {{ slot.earned_display }} | Iskorišteno: {{ slot.used_display }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-sm text-gray-500">
            Odaberi radnika i datum da vidiš raspoložive sate.
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
