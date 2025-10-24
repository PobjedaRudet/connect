<template>
  <ProductionAppLayout title="Status naloga">
    <template #header>
  <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Status naloga</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Pretraga</label>
              <input v-model="q" type="text" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200" placeholder="Broj ili opis..." />
            </div>
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Status</label>
              <select v-model="status" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200">
                <option value="">(sve)</option>
                <option value="na odobrenju">na odobrenju</option>
                <option value="odobreno">odobreno</option>
                <option value="odbijeno">odbijeno</option>
              </select>
            </div>
            <div class="ml-auto">
              <button @click="load(1)" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Osvježi</button>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gray-50 dark:bg-gray-700/70 text-gray-700 dark:text-gray-200">
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('OrderNumber')">
                    Broj <span class="text-[10px] opacity-70" v-if="sortKey==='OrderNumber'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('OrderDate')">
                    Datum <span class="text-[10px] opacity-70" v-if="sortKey==='OrderDate'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('partner')">
                    Partner <span class="text-[10px] opacity-70" v-if="sortKey==='partner'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('creator')">
                    Kreirao <span class="text-[10px] opacity-70" v-if="sortKey==='creator'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('Status')">
                    Status <span class="text-[10px] opacity-70" v-if="sortKey==='Status'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('total_quantity')">
                    Uk. količina <span class="text-[10px] opacity-70" v-if="sortKey==='total_quantity'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center">Akcija</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="o in displayRows" :key="o.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td class="px-3 py-2 text-center">
                    <div class="font-medium flex flex-wrap items-center justify-center gap-2">
                      <a :href="`/productionorders/${o.id}`" class="text-blue-600 hover:underline">{{ o.OrderNumber }}</a>
                      <span class="text-gray-700 dark:text-gray-200">—</span>
                      <span class="text-gray-800 dark:text-gray-100 truncate max-w-[280px]">{{ o.Description || '' }}</span>
                    </div>
                  </td>
                  <td class="px-3 py-2 whitespace-nowrap text-center">{{ formatDateOnly(o.OrderDate) }}</td>
                  <td class="px-3 py-2 text-center">
                    <span v-if="o.partner?.name" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                      {{ o.partner.name }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">{{ o.creator?.name ?? '' }}</td>
                  <td class="px-3 py-2 text-center">
                    <span :class="statusClass(o.Status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px]">
                      {{ o.Status || 'Na čekanju' }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">
                      {{ formatQty(totalQuantity(o)) }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <div class="flex items-center justify-center">
                      <div v-if="showOneUp(o)">
                        <button @click="approveOneUp(o)" :title="tooltipText" class="px-3 py-1 bg-red-700 text-white rounded hover:bg-red-800">Odobri (1 nivo iznad)</button>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4 flex items-center justify-between text-xs text-gray-600 dark:text-gray-300">
            <div>
              Stranica {{ page }} / {{ totalPages }} ({{ total }} ukupno)
            </div>
            <div class="flex gap-2">
              <button :disabled="page<=1" @click="load(page-1)" class="px-3 py-1 border rounded disabled:opacity-50">«</button>
              <button :disabled="page>=totalPages" @click="load(page+1)" class="px-3 py-1 border rounded disabled:opacity-50">»</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>
  </template>

  <script setup>
  import { ref, onMounted, computed } from 'vue';
  import axios from 'axios';
  import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

  const rows = ref([]);
  const page = ref(1);
  const total = ref(0);
  const perPage = ref(20);
  const status = ref('');
  const q = ref('');
  const oneUpTarget = ref(null);

  const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
  const tooltipText = computed(() => oneUpTarget.value ? `Odobri kao proxy za: ${oneUpTarget.value}` : 'Odobri (1 nivo iznad)');

  // Sorting state (client-side for current page)
  const sortKey = ref('OrderDate');
  const sortDir = ref('desc');
  const sortIndicator = computed(() => (sortDir.value === 'asc' ? '▲' : '▼'));

  function setSort(key) {
    if (sortKey.value === key) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
      sortKey.value = key;
      sortDir.value = key === 'OrderDate' ? 'desc' : 'asc';
    }
  }

  function statusWeight(s) {
    const x = (s || '').toLowerCase();
    if (x.includes('odobreno')) return 3;
    if (x.includes('na odobrenju')) return 2;
    if (x.includes('odbijeno')) return 1;
    return 0;
  }

  function sortValue(o, key) {
    switch (key) {
      case 'OrderNumber': return String(o.OrderNumber || '');
      case 'OrderDate': return new Date(o.OrderDate || 0).getTime();
      case 'partner': return String(o.partner?.name || '');
      case 'creator': return String(o.creator?.name || '');
      case 'Status': return statusWeight(o.Status);
      case 'total_quantity': return Number(totalQuantity(o) || 0);
      default: return '';
    }
  }

  const displayRows = computed(() => {
    const arr = [...(rows.value || [])];
    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    arr.sort((a,b) => {
      const va = sortValue(a, key);
      const vb = sortValue(b, key);
      if (va < vb) return -1 * dir;
      if (va > vb) return 1 * dir;
      return 0;
    });
    return arr;
  });

  async function load(p=1) {
    page.value = p;
    try {
      const { data } = await axios.get('/productionorders/created', {
        params: { page: page.value, status: status.value, q: q.value }
      });
      rows.value = (data.data || []);
      total.value = data.total || 0;
      perPage.value = data.per_page || 20;
      page.value = data.current_page || p;
      oneUpTarget.value = data.one_up_target_funkcija || null;
    } catch (e) {
      console.error('Greška pri učitavanju', e);
    }
  }

  function pad2(n) { return String(n).padStart(2,'0'); }
  function formatDateOnly(dt) {
    if (!dt) return '';
    try {
      const d = new Date(dt);
      const dd = pad2(d.getDate());
      const mm = pad2(d.getMonth() + 1);
      const yyyy = d.getFullYear();
      return `${dd}/${mm}/${yyyy}`;
    } catch { return dt; }
  }

  function statusClass(s) {
    const x = (s || '').toLowerCase();
    if (x.includes('odobreno')) return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200';
    if (x.includes('na odobrenju')) return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200';
    if (x.includes('odbijeno')) return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200';
    return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200';
  }

  function formatQty(v) {
    const n = Number(v ?? 0);
    if (!isFinite(n)) return '0';
    return n % 1 === 0 ? n.toString() : n.toFixed(2);
  }

  function totalQuantity(o) {
    if (o.total_quantity != null) return Number(o.total_quantity);
    const details = Array.isArray(o.details) ? o.details : [];
    return details.reduce((sum, d) => sum + Number(d?.quantity || 0), 0);
  }

  function showOneUp(o) {
    // Show only if status is na odobrenju, my step is approved and immediate superior pending
    return ((o.Status || '').toLowerCase().startsWith('na odobrenju')) && (o.one_up_pending_count > 0) && (o.my_step_approved_count > 0);
  }

  onMounted(() => load(1));

  async function approveOneUp(o) {
    try {
      await axios.post(`/approvals/order/${o.id}/approve-one-up`, {});
      await load(page.value);
    } catch (e) {
      alert(e?.response?.data?.message || 'Greška pri odobravanju (1 nivo iznad)');
    }
  }
  </script>

  <style>
  </style>
