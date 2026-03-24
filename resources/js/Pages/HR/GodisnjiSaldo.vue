<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'

const selectedYear = ref(new Date().getFullYear())
const isLoading = ref(false)
const error = ref(null)
const rows = ref([])
const search = ref('')

const pageSize = 20
const page = ref(1)

const detailsCache = ref({})
const detailsLoading = ref({})
const detailsError = ref({})

const hover = ref({
  employee_id: null,
  field: null, // 'approved' | 'used'
})

function cacheKey(employeeId, year) {
  return `${employeeId}:${year}`
}

async function ensureDetails(employeeId) {
  const key = cacheKey(employeeId, selectedYear.value)

  if (detailsCache.value[key]) return
  if (detailsLoading.value[key]) return

  detailsLoading.value = { ...detailsLoading.value, [key]: true }
  detailsError.value = { ...detailsError.value, [key]: null }

  try {
    const { data } = await window.axios.get('/api/godisnji/balance-details', {
      params: { employee_id: employeeId, year: selectedYear.value },
    })
    detailsCache.value = { ...detailsCache.value, [key]: data }
  } catch (e) {
    const msg = e?.response?.data?.message ?? 'Greška pri učitavanju detalja.'
    detailsError.value = { ...detailsError.value, [key]: msg }
  } finally {
    detailsLoading.value = { ...detailsLoading.value, [key]: false }
  }
}

function showPopover(employeeId, field) {
  hover.value = { employee_id: employeeId, field }
  ensureDetails(employeeId)
}

function hidePopover() {
  hover.value = { employee_id: null, field: null }
}

const activeDetails = computed(() => {
  const employeeId = hover.value.employee_id
  if (!employeeId) return null
  const key = cacheKey(employeeId, selectedYear.value)
  return detailsCache.value[key] ?? null
})

const activeLoading = computed(() => {
  const employeeId = hover.value.employee_id
  if (!employeeId) return false
  const key = cacheKey(employeeId, selectedYear.value)
  return !!detailsLoading.value[key]
})

const activeError = computed(() => {
  const employeeId = hover.value.employee_id
  if (!employeeId) return null
  const key = cacheKey(employeeId, selectedYear.value)
  return detailsError.value[key] ?? null
})

const filteredRows = computed(() => {
  const q = String(search.value ?? '').trim().toLowerCase()
  if (!q) return rows.value

  return (rows.value ?? []).filter((row) => {
    const first = String(row?.firstName ?? '')
    const last = String(row?.lastName ?? '')
    const full1 = `${first} ${last}`.trim().toLowerCase()
    const full2 = `${last} ${first}`.trim().toLowerCase()
    return full1.includes(q) || full2.includes(q)
  })
})

const totalPages = computed(() => {
  const total = (filteredRows.value ?? []).length
  return Math.max(1, Math.ceil(total / pageSize))
})

const pagedRows = computed(() => {
  const p = Math.min(Math.max(1, Number(page.value) || 1), totalPages.value)
  const start = (p - 1) * pageSize
  return (filteredRows.value ?? []).slice(start, start + pageSize)
})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const { data } = await window.axios.get('/api/godisnji/balance-all', {
      params: { year: selectedYear.value },
    })
    rows.value = data?.rows ?? []
    page.value = 1
    detailsCache.value = {}
    detailsLoading.value = {}
    detailsError.value = {}
    hidePopover()
  } catch (e) {
    error.value = e?.response?.data?.message ?? 'Greška pri učitavanju salda.'
  } finally {
    isLoading.value = false
  }
}

watch(search, () => {
  page.value = 1
})

function fmtDays(value) {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? String(Math.round(n)) : '0'
}

function fmtPart(part) {
  const p = String(part ?? 'ostalo')
  if (p === 'ljetni') return 'Ljetni'
  if (p === 'zimski') return 'Zimski'
  if (p === 'jednodnevni') return 'Jednodnevni'
  return 'Ostalo'
}

function fmtDate(value) {
  if (!value) return null
  const raw = String(value)
  const ymd = raw.length >= 10 ? raw.slice(0, 10) : raw
  const m = ymd.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (m) return `${m[3]}.${m[2]}.${m[1]}`

  const d = new Date(raw)
  if (!Number.isNaN(d.getTime())) {
    const dd = String(d.getDate()).padStart(2, '0')
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const yyyy = String(d.getFullYear())
    return `${dd}.${mm}.${yyyy}`
  }

  return raw
}

function fmtRange(from, to) {
  if (!from && !to) return ''
  return `${fmtDate(from) ?? '?'} – ${fmtDate(to) ?? '?'}`
}

onMounted(load)
</script>

<template>
  <AppLayout title="Saldo godišnjeg">
    <Head title="Saldo godišnjeg" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Saldo godišnjeg</h1>
          <p class="text-sm text-gray-500">Pregled odobrenog, iskorištenog i preostalog po radniku.</p>
        </div>

        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Godina</label>
            <input
              v-model.number="selectedYear"
              type="number"
              min="2000"
              max="2100"
              class="border rounded px-3 py-2 text-sm bg-white"
            />

            <label class="text-sm text-gray-600">Pretraga</label>
            <input
              v-model="search"
              type="text"
              placeholder="Ime ili prezime"
              class="border rounded px-3 py-2 text-sm bg-white"
            />

            <button
              type="button"
              @click="load"
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm"
            >
              Učitaj
            </button>
          </div>
        </div>
      </div>

      <div v-if="error" class="text-sm text-red-600">
        {{ error }}
      </div>

      <div v-if="isLoading" class="text-sm text-gray-600">
        Učitavanje...
      </div>

      <div v-else class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-600 border-b">
              <th class="py-3 px-4">Radnik</th>
              <th class="py-3 px-4">Odobreno</th>
              <th class="py-3 px-4">Iskorišteno</th>
              <th class="py-3 px-4">Preostalo</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in pagedRows" :key="row.employee_id" class="border-b">
              <td class="py-2 px-4 text-gray-800">
                {{ row.lastName }} {{ row.firstName }}
              </td>
              <td
                class="py-2 px-4 text-gray-800 relative"
                @mouseenter="showPopover(row.employee_id, 'approved')"
                @mouseleave="hidePopover"
              >
                <span class="underline decoration-dotted cursor-help">
                  {{ fmtDays(row.total_days) }}
                </span>

                <div
                  v-if="hover.employee_id === row.employee_id && hover.field === 'approved'"
                  class="absolute z-20 mt-2 left-4 top-full w-96 bg-white border border-gray-200 rounded-lg shadow-sm p-3"
                >
                  <div class="text-sm font-semibold text-gray-800 mb-2">Odobreno – detalji</div>

                  <div v-if="activeLoading" class="text-sm text-gray-600">Učitavanje...</div>
                  <div v-else-if="activeError" class="text-sm text-red-600">{{ activeError }}</div>
                  <div v-else-if="!activeDetails || (activeDetails.decisions?.length ?? 0) === 0" class="text-sm text-gray-600">
                    <div v-if="(activeDetails?.carryover_days ?? 0) > 0" class="text-gray-700">
                      Preneseno iz {{ activeDetails.carryover_from_year ?? (selectedYear - 1) }}: {{ fmtDays(activeDetails.carryover_days) }}
                    </div>
                    <div class="text-gray-600">Nema rješenja.</div>
                  </div>
                  <div v-else class="text-sm text-gray-800 max-h-60 overflow-auto">
                    <div v-if="(activeDetails?.carryover_days ?? 0) > 0" class="py-2 border-b">
                      <div class="font-medium">Preneseno iz {{ activeDetails.carryover_from_year ?? (selectedYear - 1) }}</div>
                      <div class="text-gray-600">Dani: {{ fmtDays(activeDetails.carryover_days) }}</div>
                    </div>
                    <div
                      v-for="d in activeDetails.decisions"
                      :key="d.id"
                      class="py-2 border-b last:border-b-0"
                    >
                      <div class="font-medium">
                        {{ fmtPart(d.part) }}
                        <span v-if="d.decision_number" class="text-gray-600">#{{ d.decision_number }}</span>
                      </div>
                      <div class="text-gray-600">
                        Dani: {{ fmtDays(d.total_days) }}
                        <span v-if="d.valid_from || d.valid_to"> | Važi: {{ fmtRange(d.valid_from, d.valid_to) }}</span>
                        <span v-if="d.decision_date" class="text-gray-600"> | Datum: {{ fmtDate(d.decision_date) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </td>

              <td
                class="py-2 px-4 text-gray-800 relative"
                @mouseenter="showPopover(row.employee_id, 'used')"
                @mouseleave="hidePopover"
              >
                <span class="underline decoration-dotted cursor-help">
                  {{ fmtDays(row.used_days) }}
                </span>

                <div
                  v-if="hover.employee_id === row.employee_id && hover.field === 'used'"
                  class="absolute z-20 mt-2 left-4 top-full w-96 bg-white border border-gray-200 rounded-lg shadow-sm p-3"
                >
                  <div class="text-sm font-semibold text-gray-800 mb-2">Iskorišteno – datumi</div>

                  <div v-if="activeLoading" class="text-sm text-gray-600">Učitavanje...</div>
                  <div v-else-if="activeError" class="text-sm text-red-600">{{ activeError }}</div>
                  <div v-else-if="!activeDetails || (activeDetails.usages?.length ?? 0) === 0" class="text-sm text-gray-600">
                    Nema iskorištenja.
                  </div>
                  <div v-else class="text-sm text-gray-800 max-h-60 overflow-auto">
                    <div
                      v-for="u in activeDetails.usages"
                      :key="u.id"
                      class="py-2 border-b last:border-b-0"
                    >
                      <div class="font-medium">{{ fmtRange(u.date_from, u.date_to) }}</div>
                      <div class="text-gray-600">
                        Dani: {{ fmtDays(u.days) }}
                        <span class="text-gray-600"> | {{ fmtPart(u.part) }}</span>
                        <span v-if="u.decision_number" class="text-gray-600"> #{{ u.decision_number }}</span>
                      </div>
                      <div v-if="u.note" class="text-gray-500">{{ u.note }}</div>
                    </div>
                  </div>
                </div>
              </td>

              <td class="py-2 px-4 text-gray-800">
                {{ fmtDays(row.remaining_days) }}
              </td>
            </tr>

            <tr v-if="pagedRows.length === 0">
              <td colspan="4" class="py-3 px-4 text-sm text-gray-500">
                Nema podataka.
              </td>
            </tr>
          </tbody>
        </table>

        <div class="flex items-center justify-between gap-3 px-4 py-3 border-t">
          <div class="text-sm text-gray-600">
            Stranica {{ page }} / {{ totalPages }}
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="border rounded px-3 py-1 text-sm bg-white disabled:opacity-50"
              :disabled="page <= 1"
              @click="page = Math.max(1, page - 1)"
            >
              Prethodna
            </button>

            <button
              type="button"
              class="border rounded px-3 py-1 text-sm bg-white disabled:opacity-50"
              :disabled="page >= totalPages"
              @click="page = Math.min(totalPages, page + 1)"
            >
              Sljedeća
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
