<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  employees: {
    type: Object,
    default: () => ({ data: [], links: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null }),
  },
  search: { type: String, default: '' },
})

const search = ref(props.search || '')

const employeesData = computed(() => props.employees?.data ?? [])

const goTo = (url) => {
  if (!url) return
  router.get(url, { preserveState: true, preserveScroll: true })
}

let searchTimer = null
watch(search, (val) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get(route('hr.uposlenici.pregled'), { search: val || undefined }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
  }, 300)
})
</script>

<template>
  <AppLayout title="Uposlenici">
    <Head title="Uposlenici" />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <Link
          :href="route('sector.hr')"
          class="inline-flex items-center px-3 py-2 bg-white text-gray-700 border border-gray-200 rounded-md text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
        >
          Nazad na HR
        </Link>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-800">Pregled uposlenika</h1>
          <p class="text-sm text-gray-500">Pretraga, pregled i brzi odlazak na izmjenu podataka.</p>
        </div>
        <div class="flex gap-2">
          <input
            v-model="search"
            type="text"
            placeholder="Pretraga (ime, prezime, odjel, funkcija)"
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

      <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                <th class="px-4 py-3">Šifra</th>
                <th class="px-4 py-3">Ime i prezime</th>
                <th class="px-4 py-3">Odjel</th>
                <th class="px-4 py-3">Funkcija</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Telefon</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Aktivan</th>
                <th class="px-4 py-3 text-right sticky right-0 bg-gray-50">Akcije</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="e in employeesData" :key="e.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ e.empID || '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-800 font-medium whitespace-nowrap">{{ e.full_name }}</td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ e.department_name || '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ e.funkcija_name || '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ e.email || '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ e.phone || '—' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">{{ e.status || '—' }}</td>
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
              <tr v-if="employeesData.length === 0">
                <td colspan="9" class="px-4 py-6 text-center text-sm text-gray-500">Nema uposlenika za prikaz.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-between text-sm text-gray-600">
          <div>Stranica {{ props.employees.current_page ?? 1 }} / {{ props.employees.last_page ?? 1 }}</div>
          <div class="space-x-2">
            <button
              class="px-3 py-1 border border-gray-300 rounded-md bg-white hover:bg-gray-100 disabled:opacity-50"
              :disabled="!props.employees.prev_page_url"
              @click="goTo(props.employees.prev_page_url)"
            >
              Prethodna
            </button>
            <button
              class="px-3 py-1 border border-gray-300 rounded-md bg-white hover:bg-gray-100 disabled:opacity-50"
              :disabled="!props.employees.next_page_url"
              @click="goTo(props.employees.next_page_url)"
            >
              Sljedeća
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
