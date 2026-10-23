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

const yearStats = computed(() => {
  const decisions = selectedDetails.value?.decisions ?? []
  const usages = [...(selectedDetails.value?.usages ?? [])]
    .sort((a, b) => String(b?.date_from ?? '').localeCompare(String(a?.date_from ?? '')))
  const years = new Set([
    ...decisions.map((item) => Number(item?.year) || 0),
    ...usages.map((item) => Number(item?.year) || 0),
  ])

  return [...years]
    .sort((a, b) => b - a)
    .map((year) => {
      const yearDecisions = decisions
        .filter((item) => Number(item?.year) === year)
        .sort((a, b) => String(b?.decision_date ?? '').localeCompare(String(a?.decision_date ?? '')))
      const yearUsages = usages.filter((item) => Number(item?.year) === year)
      const granted = yearDecisions.reduce((sum, item) => sum + wholeDays(item?.granted_days), 0)
      const used = yearUsages.reduce((sum, item) => sum + wholeDays(item?.days), 0)

      return {
        year,
        decisions: yearDecisions,
        usages: yearUsages,
        granted,
        used,
        remaining: granted - used,
      }
    })
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

const stats = computed(() => {
  const list = filteredRows.value ?? []
  return list.reduce((sum, row) => {
    sum.employees += 1
    sum.decisions += wholeDays(row?.decision_count)
    sum.granted += wholeDays(row?.total_days)
    sum.used += wholeDays(row?.used_days)
    sum.remaining += wholeDays(row?.remaining_days)
    return sum
  }, { employees: 0, decisions: 0, granted: 0, used: 0, remaining: 0 })
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

function wholeDays(value) {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? Math.round(n) : 0
}

function fmtDays(value) {
  return String(wholeDays(value))
}

function fmtPlus(value) {
  const n = wholeDays(value)
  return n > 0 ? `+${n}` : '0'
}

function fmtMinus(value) {
  const n = wholeDays(value)
  return n > 0 ? `-${n}` : '0'
}

function decisionLabel(count) {
  const n = wholeDays(count)
  return n === 1 ? '1 rješenje' : `${n} rješenja`
}

function fmtPart(part) {
  const p = String(part ?? 'ostalo')
  if (p === 'ljetni') return 'I dio'
  if (p === 'zimski') return 'II dio'
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
          <p class="text-sm text-gray-500">Rješenja su odobreni dani. Iskorišteni dani su u minusu. Preostalo je razlika.</p>
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

      <div v-if="!isLoading" class="grid gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Radnika</div>
          <div class="mt-2 text-2xl font-semibold text-gray-900">{{ stats.employees }}</div>
        </div>
        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-sky-700">Rješenja</div>
          <div class="mt-2 text-2xl font-semibold text-sky-900">{{ fmtPlus(stats.granted) }} <span class="text-base font-medium">dana</span></div>
          <div class="mt-1 text-xs text-sky-800">{{ decisionLabel(stats.decisions) }}</div>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-red-700">Iskorišteno</div>
          <div class="mt-2 text-2xl font-semibold text-red-900">{{ fmtMinus(stats.used) }} <span class="text-base font-medium">dana</span></div>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Preostalo</div>
          <div class="mt-2 text-2xl font-semibold text-emerald-900">{{ fmtDays(stats.remaining) }} <span class="text-base font-medium">dana</span></div>
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
              <th class="py-3 px-4">Rješenja</th>
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
                <span class="underline decoration-dotted cursor-help text-sky-800">
                  {{ fmtPlus(row.total_days) }}
                </span>
                <div class="text-xs text-gray-500">{{ decisionLabel(row.decision_count) }}</div>

                <div
                  v-if="hover.employee_id === row.employee_id && hover.field === 'approved'"
                  class="absolute z-20 mt-2 left-4 top-full w-96 bg-white border border-gray-200 rounded-lg shadow-sm p-3"
                >
                  <div class="text-sm font-semibold text-gray-800 mb-2">Rješenja</div>

                  <div v-if="activeLoading" class="text-sm text-gray-600">Učitavanje...</div>
                  <div v-else-if="activeError" class="text-sm text-red-600">{{ activeError }}</div>
                  <div v-else-if="!activeDetails || (activeDetails.decisions?.length ?? 0) === 0" class="text-sm text-gray-600">
                    Nema rješenja.
                  </div>
                  <div v-else class="text-sm text-gray-800 max-h-60 overflow-auto">
                    <div
                      v-for="decision in activeDetails.decisions"
                      :key="decision.id"
                      class="py-2 border-b last:border-b-0"
                    >
                      <div class="flex justify-between gap-3">
                        <span class="font-medium">
                          {{ decision.year }}. · {{ fmtPart(decision.part) }}
                          <span v-if="decision.decision_number">#{{ decision.decision_number }}</span>
                        </span>
                        <span class="text-sky-800">{{ fmtPlus(decision.granted_days) }} dana</span>
                      </div>
                      <div class="text-gray-500">{{ fmtDate(decision.decision_date) || 'Bez datuma' }}</div>
                    </div>
                  </div>
                </div>
              </td>

              <td
                class="py-2 px-4 text-gray-800 relative"
                @mouseenter="showPopover(row.employee_id, 'used')"
                @mouseleave="hidePopover"
              >
                <span class="underline decoration-dotted cursor-help" :class="wholeDays(row.used_days) > 0 ? 'text-red-700' : ''">
                  {{ fmtMinus(row.used_days) }}
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
                        Iskorišteno {{ fmtMinus(u.days) }} dana
                        <span class="text-gray-600"> | {{ u.year }}.</span>
                        <span class="text-gray-600"> | {{ fmtPart(u.part) }}</span>
                        <span v-if="u.decision_number" class="text-gray-600"> #{{ u.decision_number }}</span>
                      </div>
                      <div v-if="u.note" class="text-gray-500">{{ u.note }}</div>
                    </div>
                  </div>
                </div>
              </td>

              <td class="py-2 px-4 font-medium" :class="wholeDays(row.remaining_days) < 0 ? 'text-red-700' : 'text-emerald-800'">
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
            <div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Saldo godišnjeg</div>
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
            <div class="text-xs text-slate-500">Rješenja</div>
            <div class="mt-1 text-lg font-semibold text-sky-800">{{ fmtPlus(selected.total_days) }} dana</div>
            <div class="text-xs text-slate-500">{{ decisionLabel(selected.decision_count) }}</div>
          </div>
          <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Iskorišteno</div>
            <div class="mt-1 text-lg font-semibold text-red-700">{{ fmtMinus(selected.used_days) }} dana</div>
          </div>
          <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Preostalo</div>
            <div class="mt-1 text-lg font-semibold" :class="wholeDays(selected.remaining_days) < 0 ? 'text-red-700' : 'text-emerald-700'">{{ fmtDays(selected.remaining_days) }} dana</div>
          </div>
        </div>

        <div class="overflow-y-auto px-6 py-5">
          <div v-if="selectedLoading" class="text-sm text-slate-500">Učitavanje rješenja i iskorištenih dana...</div>
          <div v-else-if="selectedError" class="text-sm text-red-600">{{ selectedError }}</div>
          <div v-else-if="yearStats.length === 0" class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
            Nema rješenja ni iskorištenja.
          </div>
          <div v-else class="space-y-6">
            <section v-for="group in yearStats" :key="group.year">
              <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-800">{{ group.year }}.</h3>
                <div class="flex flex-wrap gap-2 text-xs">
                  <span class="rounded-full bg-sky-50 px-2 py-1 text-sky-800">Rješenja {{ fmtPlus(group.granted) }}</span>
                  <span class="rounded-full bg-red-50 px-2 py-1 text-red-700">Iskorišteno {{ fmtMinus(group.used) }}</span>
                  <span class="rounded-full bg-emerald-50 px-2 py-1" :class="group.remaining < 0 ? 'text-red-700' : 'text-emerald-800'">Preostalo {{ fmtDays(group.remaining) }}</span>
                </div>
              </div>

              <div class="overflow-hidden rounded-xl border border-slate-200">
                <div class="bg-sky-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-sky-800">Rješenja</div>
                <div v-if="group.decisions.length === 0" class="px-4 py-3 text-sm text-slate-500">Nema rješenja za ovu godinu.</div>
                <table v-else class="min-w-full text-sm">
                  <tbody>
                    <tr v-for="decision in group.decisions" :key="decision.id" class="border-t border-slate-100">
                      <td class="px-4 py-3 text-slate-800">
                        <div>{{ fmtPart(decision.part) }}<span v-if="decision.decision_number"> · #{{ decision.decision_number }}</span></div>
                        <div class="text-xs text-slate-500">{{ fmtDate(decision.decision_date) || 'Bez datuma' }}<span v-if="decision.valid_from || decision.valid_to"> · važi {{ fmtRange(decision.valid_from, decision.valid_to) }}</span></div>
                      </td>
                      <td class="px-4 py-3 text-right font-medium text-sky-800">{{ fmtPlus(decision.granted_days) }} dana</td>
                    </tr>
                  </tbody>
                </table>

                <div class="border-t border-slate-200 bg-red-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-red-800">Iskorišteno</div>
                <div v-if="group.usages.length === 0" class="px-4 py-3 text-sm text-slate-500">Nema iskorištenja za ovu godinu.</div>
                <table v-else class="min-w-full text-sm">
                  <tbody>
                    <tr v-for="usage in group.usages" :key="usage.id" class="border-t border-slate-100">
                      <td class="px-4 py-3 text-slate-800">
                        <div>{{ fmtRange(usage.date_from, usage.date_to) }}</div>
                        <div class="text-xs text-slate-500">{{ usageReason(usage) }}<span v-if="usage.decision_number"> · #{{ usage.decision_number }}</span></div>
                      </td>
                      <td class="px-4 py-3 text-right font-medium text-red-700">Iskorišteno {{ fmtMinus(usage.days) }} dana</td>
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
