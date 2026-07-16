<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'

const props = defineProps({
  filters: {
    type: Object,
    default: () => ({
      action: '',
      user_id: '',
      employee_id: '',
      date_from: '',
      date_to: '',
      q: '',
    }),
  },
  users: { type: Array, default: () => [] },
  employees: { type: Array, default: () => [] },
  logs: {
    type: Object,
    default: () => ({
      data: [],
      links: [],
      current_page: 1,
      last_page: 1,
      prev_page_url: null,
      next_page_url: null,
      total: 0,
      from: null,
      to: null,
    }),
  },
})

const form = reactive({
  action: props.filters.action || '',
  user_id: props.filters.user_id || '',
  employee_id: props.filters.employee_id || '',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  q: props.filters.q || '',
})

const expandedId = ref(null)

const applyFilters = () => {
  const params = {}
  Object.entries(form).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params[key] = value
    }
  })
  router.get(route('admin.sihterica-audit'), params, {
    preserveState: true,
    preserveScroll: true,
  })
}

const resetFilters = () => {
  form.action = ''
  form.user_id = ''
  form.employee_id = ''
  form.date_from = ''
  form.date_to = ''
  form.q = ''
  applyFilters()
}

const goTo = (url) => {
  if (!url) return
  router.get(url, {}, { preserveState: true, preserveScroll: true })
}

const toggleExpanded = (id) => {
  expandedId.value = expandedId.value === id ? null : id
}

const actionBadge = (action) => {
  if (action === 'created') return 'bg-emerald-100 text-emerald-800'
  if (action === 'updated') return 'bg-amber-100 text-amber-800'
  if (action === 'deleted') return 'bg-red-100 text-red-800'
  return 'bg-gray-100 text-gray-700'
}

const formatTimePart = (value) => {
  if (!value || value === '—') return '—'
  // Expect "Y-m-d H:i:s" → show "d.m.Y H:i"
  const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/)
  if (!m) return value
  return `${m[3]}.${m[2]}.${m[1]} ${m[4]}:${m[5]}`
}
</script>

<template>
  <AppLayout title="Šihterica audit log">
    <Head title="Šihterica audit log" />

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
        <div>
          <div class="text-sm text-gray-500 mb-1">
            <Link :href="route('admin.dashboard')" class="text-indigo-600 hover:text-indigo-800">Admin</Link>
            <span class="mx-1">/</span>
            <span>Šihterica audit</span>
          </div>
          <h1 class="text-2xl font-semibold text-gray-800">Šihterica – audit log</h1>
          <p class="text-sm text-gray-500 mt-1">
            Praćenje ručnih dodavanja, izmjena i brisanja prijave/odjave uposlenika.
          </p>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 mb-4">
        <form class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3" @submit.prevent="applyFilters">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Akcija</label>
            <select v-model="form.action" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
              <option value="">Sve</option>
              <option value="created">Dodano</option>
              <option value="updated">Izmijenjeno</option>
              <option value="deleted">Obrisano</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Korisnik</label>
            <select v-model="form.user_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
              <option value="">Svi</option>
              <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Uposlenik</label>
            <select v-model="form.employee_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
              <option value="">Svi</option>
              <option v-for="e in employees" :key="e.id" :value="String(e.id)">{{ e.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Od datuma</label>
            <input v-model="form.date_from" type="date" class="w-full border-gray-300 rounded-md shadow-sm text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Do datuma</label>
            <input v-model="form.date_to" type="date" class="w-full border-gray-300 rounded-md shadow-sm text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Pretraga</label>
            <input
              v-model="form.q"
              type="text"
              placeholder="Ime / email / empID"
              class="w-full border-gray-300 rounded-md shadow-sm text-sm"
            />
          </div>
          <div class="md:col-span-3 lg:col-span-6 flex items-center gap-2">
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
              Filtriraj
            </button>
            <button type="button" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200" @click="resetFilters">
              Reset
            </button>
            <div class="text-xs text-gray-500 ml-auto">
              Ukupno: {{ logs.total ?? 0 }}
            </div>
          </div>
        </form>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
              <tr>
                <th class="px-4 py-3">Vrijeme</th>
                <th class="px-4 py-3">Akcija</th>
                <th class="px-4 py-3">Korisnik</th>
                <th class="px-4 py-3">Uposlenik</th>
                <th class="px-4 py-3">Radni dan</th>
                <th class="px-4 py-3">Promjene</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <template v-for="log in logs.data" :key="log.id">
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ log.created_at }}</td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="actionBadge(log.action)">
                      {{ log.action_label }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-800">{{ log.user?.name || '—' }}</div>
                    <div class="text-xs text-gray-500">{{ log.user?.email || '' }}</div>
                  </td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-gray-800">{{ log.employee?.full_name || '—' }}</div>
                    <div class="text-xs text-gray-500" v-if="log.employee?.empID">#{{ log.employee.empID }}</div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ log.work_date || '—' }}</td>
                  <td class="px-4 py-3 text-gray-600">
                    <template v-if="log.action === 'created'">
                      Prijava {{ formatTimePart(log.after?.entry_time) }}
                      → Odjava {{ formatTimePart(log.after?.exit_time) }}
                    </template>
                    <template v-else-if="log.action === 'deleted'">
                      Prijava {{ formatTimePart(log.before?.entry_time) }}
                      → Odjava {{ formatTimePart(log.before?.exit_time) }}
                    </template>
                    <template v-else>
                      <span v-if="!log.changes?.length">Bez značajnih polja</span>
                      <span v-else>{{ log.changes.length }} polja</span>
                    </template>
                  </td>
                  <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button
                      type="button"
                      class="text-indigo-600 hover:text-indigo-800 text-sm font-medium"
                      @click="toggleExpanded(log.id)"
                    >
                      {{ expandedId === log.id ? 'Sakrij' : 'Detalji' }}
                    </button>
                  </td>
                </tr>
                <tr v-if="expandedId === log.id" class="bg-slate-50">
                  <td colspan="7" class="px-4 py-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                      <div class="rounded-md border border-gray-200 bg-white p-3">
                        <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Prije</div>
                        <pre class="text-xs text-gray-700 whitespace-pre-wrap break-words">{{ log.before ? JSON.stringify(log.before, null, 2) : '—' }}</pre>
                      </div>
                      <div class="rounded-md border border-gray-200 bg-white p-3">
                        <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Poslije</div>
                        <pre class="text-xs text-gray-700 whitespace-pre-wrap break-words">{{ log.after ? JSON.stringify(log.after, null, 2) : '—' }}</pre>
                      </div>
                    </div>

                    <div v-if="log.changes?.length" class="mt-3 overflow-x-auto">
                      <table class="min-w-full text-xs">
                        <thead>
                          <tr class="text-left text-gray-500">
                            <th class="py-1 pr-3">Polje</th>
                            <th class="py-1 pr-3">Od</th>
                            <th class="py-1">Na</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="c in log.changes" :key="c.field" class="border-t border-gray-200">
                            <td class="py-1.5 pr-3 font-medium text-gray-700">{{ c.label }}</td>
                            <td class="py-1.5 pr-3 text-gray-600">{{ c.field.includes('time') || c.field.includes('start') ? formatTimePart(c.from) : c.from }}</td>
                            <td class="py-1.5 text-gray-800">{{ c.field.includes('time') || c.field.includes('start') ? formatTimePart(c.to) : c.to }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <div class="mt-3 text-xs text-gray-500 flex flex-wrap gap-4">
                      <span>Record ID: {{ log.attendance_record_id || '—' }}</span>
                      <span>IP: {{ log.ip_address || '—' }}</span>
                    </div>
                  </td>
                </tr>
              </template>

              <tr v-if="!(logs.data || []).length">
                <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                  Nema zabilježenih izmjena za odabrane filtere.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-between text-sm text-gray-600">
          <div>
            Stranica {{ logs.current_page ?? 1 }} / {{ logs.last_page ?? 1 }}
            <span v-if="logs.from" class="text-gray-400"> ({{ logs.from }}–{{ logs.to }})</span>
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              class="px-3 py-1.5 rounded-md border border-gray-300 bg-white disabled:opacity-40"
              :disabled="!logs.prev_page_url"
              @click="goTo(logs.prev_page_url)"
            >
              Prethodna
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-md border border-gray-300 bg-white disabled:opacity-40"
              :disabled="!logs.next_page_url"
              @click="goTo(logs.next_page_url)"
            >
              Sljedeća
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
