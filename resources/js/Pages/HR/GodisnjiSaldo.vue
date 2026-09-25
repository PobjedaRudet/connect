<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

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

function cacheKey(employeeId) {
  return String(employeeId)
}

async function ensureDetails(employeeId) {
  const key = cacheKey(employeeId)

  if (detailsCache.value[key]) return
  if (detailsLoading.value[key]) return

  detailsLoading.value = { ...detailsLoading.value, [key]: true }
  detailsError.value = { ...detailsError.value, [key]: null }

  try {
    const { data } = await window.axios.get('/api/godisnji/balance-summary-details', {
      params: { employee_id: employeeId },
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

const selected = ref(null)

function openEmployee(row) {
  selected.value = row
  ensureDetails(row.employee_id)
}

function closeEmployee() {
  selected.value = null
}

function onEscape(event) {
  if (event.key === 'Escape') closeEmployee()
}

const activeDetails = computed(() => {
  const employeeId = hover.value.employee_id
  if (!employeeId) return null
  const key = cacheKey(employeeId)
  return detailsCache.value[key] ?? null
})

const activeLoading = computed(() => {
  const employeeId = hover.value.employee_id
  if (!employeeId) return false
  const key = cacheKey(employeeId)
  return !!detailsLoading.value[key]
})

const activeError = computed(() => {
  const employeeId = hover.value.employee_id
  if (!employeeId) return null
  const key = cacheKey(employeeId)
  return detailsError.value[key] ?? null
})

const selectedDetails = computed(() => {
  const employeeId = selected.value?.employee_id
  if (!employeeId) return null
  return detailsCache.value[cacheKey(employeeId)] ?? null
})

const selectedLoading = computed(() => {
  const employeeId = selected.value?.employee_id
  if (!employeeId) return false
  return !!detailsLoading.value[cacheKey(employeeId)]
})

const selectedError = computed(() => {
  const employeeId = selected.value?.employee_id
  if (!employeeId) return null
  return detailsError.value[cacheKey(employeeId)] ?? null
})

const usageGroups = computed(() => {
  const list = [...(selectedDetails.value?.usages ?? [])]
    .sort((a, b) => String(b?.date_from ?? '').localeCompare(String(a?.date_from ?? '')))

  const groups = new Map()
  for (const usage of list) {
    const year = Number(usage?.year) || 0
    if (!groups.has(year)) groups.set(year, [])
    groups.get(year).push(usage)
  }

  return [...groups.entries()]
    .sort((a, b) => b[0] - a[0])
    .map(([year, items]) => ({
      year,
      items,
      days: items.reduce((sum, item) => sum + Number(item?.days ?? 0), 0),
    }))
})

function usageReason(usage) {
  const note = String(usage?.note ?? '').trim()
  if (note) return note
  return fmtPart(usage?.part)
}

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
    const { data } = await window.axios.get('/api/godisnji/balance-summary')
    rows.value = data?.rows ?? []
    page.value = 1
    detailsCache.value = {}
    detailsLoading.value = {}
    detailsError.value = {}
    hidePopover()
    closeEmployee()
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

onMounted(() => {
  load()
  window.addEventListener('keydown', onEscape)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onEscape)
})
</script>

<template>
  <AppLayout title="Saldo godišnjeg">
    <Head title="Saldo godišnjeg" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Saldo godišnjeg</h1>
          <p class="text-sm text-gray-500">Sumarni pregled odobrenog, iskorištenog i preostalog po radniku za sve godine.</p>
        </div>

        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2">
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
            <tr v-for="row in pagedRows" :key="row.employee_id" class="border-b" :class="selected?.employee_id === row.employee_id ? 'bg-sky-50' : ''">
              <td class="py-2 px-4 text-gray-800">
                <button
                  type="button"
                  class="text-left font-medium text-sky-800 hover:text-sky-950 hover:underline"
                  @click="openEmployee(row)"
                >
                  {{ row.lastName }} {{ row.firstName }}
                </button>
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
                  <div v-else-if="!activeDetails || (activeDetails.year_totals?.length ?? 0) === 0" class="text-sm text-gray-600">
                    Nema rješenja.
                  </div>
                  <div v-else class="text-sm text-gray-800 max-h-60 overflow-auto">
                    <div
                      v-for="yt in activeDetails.year_totals"
                      :key="yt.year"
                      class="py-2 border-b last:border-b-0"
                    >
                      <div class="flex justify-between">
                        <span class="font-medium">{{ yt.year }}.</span>
                        <span class="text-gray-600">{{ fmtDays(yt.granted_days) }} dana</span>
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
                        <span class="text-gray-600"> | {{ u.year }}.</span>
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

    <div
      v-if="selected"
      class="fixed inset-0 z-40 flex items-start justify-center bg-slate-900/40 px-4 py-8 sm:py-12"
      @click.self="closeEmployee"
    >
      <div class="flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
          <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Iskorišteni godišnji</div>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">
              {{ selected.lastName }} {{ selected.firstName }}
            </h2>
          </div>
          <button
            type="button"
            class="rounded-lg px-2 py-1 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-800"
            @click="closeEmployee"
          >
            Zatvori
          </button>
        </div>

        <div class="grid grid-cols-3 gap-3 border-b border-slate-200 bg-slate-50 px-6 py-4">
          <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Odobreno</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">{{ fmtDays(selected.total_days) }}</div>
          </div>
          <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Iskorišteno</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">{{ fmtDays(selected.used_days) }}</div>
          </div>
          <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Preostalo</div>
            <div class="mt-1 text-lg font-semibold text-emerald-700">{{ fmtDays(selected.remaining_days) }}</div>
          </div>
        </div>

        <div class="overflow-y-auto px-6 py-5">
          <div v-if="selectedLoading" class="text-sm text-slate-500">Učitavanje iskorištenih dana...</div>
          <div v-else-if="selectedError" class="text-sm text-red-600">{{ selectedError }}</div>
          <div v-else-if="usageGroups.length === 0" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
            Nema unesenih iskorištenja.
          </div>
          <div v-else class="space-y-6">
            <section v-for="group in usageGroups" :key="group.year">
              <div class="mb-2 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-800">{{ group.year }}.</h3>
                <span class="text-xs text-slate-500">{{ fmtDays(group.days) }} dana</span>
              </div>
              <div class="overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full text-sm">
                  <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                      <th class="px-4 py-2.5 font-medium">Datum</th>
                      <th class="px-4 py-2.5 font-medium">Dani</th>
                      <th class="px-4 py-2.5 font-medium">Razlog</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="usage in group.items" :key="usage.id" class="border-t border-slate-100">
                      <td class="px-4 py-3 text-slate-800">{{ fmtRange(usage.date_from, usage.date_to) }}</td>
                      <td class="px-4 py-3 text-slate-700">{{ fmtDays(usage.days) }}</td>
                      <td class="px-4 py-3 text-slate-700">
                        <div>{{ usageReason(usage) }}</div>
                        <div v-if="String(usage.note ?? '').trim()" class="mt-0.5 text-xs text-slate-400">{{ fmtPart(usage.part) }}</div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
