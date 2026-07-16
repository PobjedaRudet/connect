<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HrNav from '@/Components/HrNav.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  employees: {
    type: Object,
    default: () => ({
      data: [],
      links: [],
      current_page: 1,
      last_page: 1,
      next_page_url: null,
      prev_page_url: null,
      total: 0,
    }),
  },
  search: { type: String, default: '' },
  radnaMjesta: { type: Array, default: () => [] },
})

const search = ref(props.search || '')
const updatingId = ref(null)
const updateError = ref(null)

const items = ref([...(props.employees?.data ?? [])])
const currentPage = ref(props.employees?.current_page ?? 1)
const lastPage = ref(props.employees?.last_page ?? 1)
const total = ref(props.employees?.total ?? 0)
const loadingMore = ref(false)
const resetting = ref(false)

const scrollRoot = ref(null)
const sentinel = ref(null)
let observer = null
let searchTimer = null

const hasMore = computed(() => currentPage.value < lastPage.value)

const formatStatus = (value) => {
  if (value === 1 || value === '1') return 'Neodređeno'
  if (value === 2 || value === '2') return 'Određeno'
  return value || '—'
}

const syncFromPaginator = (paginator, { append = false } = {}) => {
  const rows = paginator?.data ?? []
  if (append) {
    const existingIds = new Set(items.value.map((e) => e.id))
    items.value.push(...rows.filter((e) => !existingIds.has(e.id)))
  } else {
    items.value = [...rows]
  }
  currentPage.value = paginator?.current_page ?? 1
  lastPage.value = paginator?.last_page ?? 1
  total.value = paginator?.total ?? items.value.length
}

const loadMore = () => {
  if (loadingMore.value || resetting.value || !hasMore.value) return

  loadingMore.value = true
  router.get(
    route('hr.uposlenici.pregled'),
    {
      search: search.value || undefined,
      page: currentPage.value + 1,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['employees'],
      onSuccess: (page) => {
        syncFromPaginator(page.props.employees, { append: true })
      },
      onFinish: () => {
        loadingMore.value = false
      },
    }
  )
}

const resetAndSearch = (term) => {
  resetting.value = true
  router.get(
    route('hr.uposlenici.pregled'),
    { search: term || undefined },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['employees'],
      onSuccess: (page) => {
        syncFromPaginator(page.props.employees, { append: false })
        if (scrollRoot.value) scrollRoot.value.scrollTop = 0
      },
      onFinish: () => {
        resetting.value = false
      },
    }
  )
}

watch(search, (val) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    resetAndSearch(val)
  }, 300)
})

const setupObserver = async () => {
  await nextTick()
  if (observer) observer.disconnect()
  if (!sentinel.value || !scrollRoot.value) return

  observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) {
        loadMore()
      }
    },
    {
      root: scrollRoot.value,
      rootMargin: '120px',
      threshold: 0,
    }
  )
  observer.observe(sentinel.value)
}

onMounted(() => {
  setupObserver()
})

onBeforeUnmount(() => {
  if (observer) observer.disconnect()
  if (searchTimer) clearTimeout(searchTimer)
})

watch(sentinel, () => {
  setupObserver()
})

const updateRadnoMjesto = async (employee, newValue) => {
  const val = newValue || null
  if (employee.radno_mjesto === val) return
  updatingId.value = employee.id
  updateError.value = null
  try {
    const res = await axios.put(
      route('hr.uposlenici.update-radno-mjesto', employee.id),
      { radno_mjesto: val }
    )
    employee.radno_mjesto = res.data.radno_mjesto
  } catch {
    updateError.value = 'Greška pri ažuriranju radnog mjesta.'
  } finally {
    updatingId.value = null
  }
}
</script>

<template>
  <AppLayout title="Uposlenici">
    <Head title="Uposlenici" />
    <HrNav />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Pregled uposlenika</h1>
          <p class="text-sm text-gray-500">
            Skrolaj listu — novi uposlenici se učitavaju automatski.
          </p>
        </div>
        <div class="flex gap-2">
          <input
            v-model="search"
            type="text"
            placeholder="Pretraga (ime, prezime, odjel, radno mjesto)"
            class="w-full sm:w-72 border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
          />
          <Link
            :href="route('hr.uposlenici.forma')"
            class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
          >
            Novi
          </Link>
        </div>
      </div>

      <div v-if="updateError" class="bg-red-50 border border-red-200 rounded-md p-3 text-sm text-red-700">
        {{ updateError }}
      </div>

      <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
        <div ref="scrollRoot" class="max-h-[65vh] overflow-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 sticky top-0 z-10">
              <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                <th class="px-4 py-3">SAP ID</th>
                <th class="px-4 py-3">Ime i prezime</th>
                <th class="px-4 py-3">Odjel</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Aktivan</th>
                <th class="px-4 py-3 text-right sticky right-0 bg-gray-50">Akcije</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="e in items" :key="e.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ e.empID || '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-800 font-medium whitespace-nowrap">{{ e.full_name }}</td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ e.department_name || '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ formatStatus(e.status) }}</td>
                <td class="px-4 py-3 text-sm whitespace-nowrap">
                  <span
                    :class="e.active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                  >
                    {{ e.active ? 'Da' : 'Ne' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-right whitespace-nowrap sticky right-0 bg-white">
                  <Link
                    :href="route('hr.uposlenici.forma', e.id)"
                    class="inline-flex items-center px-3 py-2 bg-white text-gray-700 border border-gray-200 rounded-md text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
                  >
                    Uredi
                  </Link>
                </td>
              </tr>
              <tr v-if="items.length === 0 && !resetting">
                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">Nema uposlenika za prikaz.</td>
              </tr>
            </tbody>
          </table>

          <div ref="sentinel" class="px-4 py-4 text-center text-sm text-gray-500">
            <span v-if="loadingMore || resetting">Učitavanje…</span>
            <span v-else-if="hasMore">Skrolaj za još uposlenika…</span>
            <span v-else-if="items.length > 0">Prikazano {{ items.length }} od {{ total }}</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
