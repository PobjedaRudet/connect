<template>
  <ProductionAppLayout title="Moja odobrenja">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Moja odobrenja</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Pregled i upravljanje nalozima na vašem koraku odobrenja.</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="hidden sm:flex items-center gap-2 text-sm bg-gray-100 dark:bg-gray-700 px-3 py-1.5 rounded-md">
            <span class="text-gray-600 dark:text-gray-300">Trenutni korak:</span>
            <span class="font-medium text-gray-900 dark:text-gray-100">{{ myFunkcija || '-' }}</span>
          </div>
          <button @click="load" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700">
            Osvježi
          </button>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <div class="text-sm text-gray-600 dark:text-gray-300">Ukupno: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ rows.length }}</span></div>
              <div class="text-sm text-gray-600 dark:text-gray-300" v-if="selected.length>0">Odabrano: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ selected.length }}</span></div>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
              <input v-model.trim="q" type="text" placeholder="Pretraga (broj, partner, opis)" class="form-input rounded-md w-full sm:w-72 dark:bg-gray-700 dark:text-gray-200" />
        <button @click="bulkApprove" :disabled="selected.length===0" class="px-3 py-1.5 rounded text-white"
          :class="selected.length===0 ? 'bg-gray-400 cursor-not-allowed' : 'bg-green-500 hover:bg-green-600'">
                Odobri odabrano<span v-if="selected.length>0"> ({{ selected.length }})</span>
              </button>
            </div>
          </div>

          <div v-if="rows.length === 0" class="p-4 text-sm text-gray-500 dark:text-gray-300">Nema naloga za odobrenje.</div>

          <div v-else class="p-0 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gray-50 dark:bg-gray-700/70 text-gray-700 dark:text-gray-200">
                  <th class="px-3 py-2 text-left w-10">
                    <input type="checkbox" :checked="visibleRows.length>0 && selected.length===visibleRows.length" @change="toggleSelectAll($event)" />
                  </th>
                  <th class="px-3 py-2 text-left cursor-pointer select-none" @click="toggleSort('OrderNumber')">
                    Broj
                    <SortIcon :active="sortKey==='OrderNumber'" :dir="sortDir" />
                  </th>
                  <th class="px-3 py-2 text-left cursor-pointer select-none" @click="toggleSort('partner')">
                    Partner
                    <SortIcon :active="sortKey==='partner'" :dir="sortDir" />
                  </th>
                  <th class="px-3 py-2 text-left">Uk. količina</th>
                  <th class="px-3 py-2 text-left">Opis</th>
                  <th class="px-3 py-2 text-left w-72">Akcija</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="o in visibleRows" :key="o.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td class="px-3 py-2 align-top">
                    <input type="checkbox" :value="o.current_approval_id" v-model="selected" />
                  </td>
                  <td class="px-3 py-2 align-top">
                    <a :href="`/productionorders/${o.id}`" class="font-medium text-blue-600 hover:underline">{{ o.OrderNumber }}</a>
                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Kreirano: {{ formatDate(o) }} • {{ creatorName(o) }}</div>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">
                      {{ o.partner || '—' }}
                    </span>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200 text-xs font-semibold">
                      {{ formatQty(totalQuantity(o)) }}
                    </span>
                  </td>
                  <td class="px-3 py-2 align-top">
                    <div class="max-w-xl">
                      <div class="truncate" :title="o.Description">{{ o.Description }}</div>
                    </div>
                  </td>
                  <td class="px-3 py-2">
                    <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                      <button @click="approve(o)" class="px-3 py-1.5 bg-green-500 text-white rounded hover:bg-green-600">Odobri</button>
                      <button @click="reject(o)" class="px-3 py-1.5 bg-red-500 text-white rounded hover:bg-red-600">Odbij</button>
                      <button @click="voidOrder(o)" class="px-3 py-1.5 bg-gray-700 text-white rounded hover:bg-gray-800">Poništi</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

async function voidOrder(o) {
  if (!o?.id) return;
  if (!confirm(`Poništiti nalog ${o.OrderNumber}?`)) return;
  const reason = prompt('Razlog poništavanja (opciono):', '') || '';
  try {
    await axios.delete(`/productionorders/${o.id}`, { data: { reason } });
    await load();
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri poništavanju naloga');
  }
}

const rows = ref([]);
const myFunkcija = ref('');
const selected = ref([]); // holds current_approval_id values
const q = ref('');
const sortKey = ref('');
const sortDir = ref('asc'); // 'asc' | 'desc'
const totalById = ref({}); // fallback totals
const createdAtById = ref({});
const creatorById = ref({});

async function load() {
  try {
    const { data } = await axios.get('/approvals/pending');
    // data = { data: [{id, OrderNumber, Description, partner, current_approval_id}] }
    rows.value = (data?.data || []);
    // sanitize selection to only include approvals that are still visible
    const visibleIds = new Set(rows.value.map(o => o.current_approval_id));
    selected.value = selected.value.filter(id => visibleIds.has(id));
    // Infer funkcija from status if needed; left empty since API doesn't return it
    // Build totals cache
    totalById.value = {};
    createdAtById.value = {};
    creatorById.value = {};
    const toFetch = [];
    for (const o of rows.value) {
      const t = Number(o?.total_quantity ?? 0);
      if (t > 0) {
        totalById.value[o.id] = t;
      } else {
        toFetch.push(o.id);
      }
      if (o.created_at) createdAtById.value[o.id] = o.created_at;
      if (o.CreatedAt) createdAtById.value[o.id] = o.CreatedAt;
      if (o.user_name) creatorById.value[o.id] = o.user_name;
      if (o.creator_name) creatorById.value[o.id] = o.creator_name;
      if (o.user?.name) creatorById.value[o.id] = o.user.name;
      if (!createdAtById.value[o.id] && o.OrderDate) createdAtById.value[o.id] = o.OrderDate;
    }
    for (const orderId of toFetch) {
      try {
        const resp = await axios.get(`/api/productionorders/${orderId}`);
        const details = resp?.data?.order?.details || [];
        const sum = details.reduce((a, d) => a + Number(d?.quantity || 0), 0);
        totalById.value[orderId] = sum;
        const ord = resp?.data?.order;
        if (ord?.created_at && !createdAtById.value[orderId]) createdAtById.value[orderId] = ord.created_at;
        if (ord?.user?.name && !creatorById.value[orderId]) creatorById.value[orderId] = ord.user.name;
      } catch {}
    }
    // Fetch creation meta for rows that already had totals
    const creationFetch = rows.value
      .filter(o => !createdAtById.value[o.id] || !creatorById.value[o.id])
      .map(o => o.id)
      .filter(id => !toFetch.includes(id));
    for (const orderId of creationFetch) {
      try {
        const resp = await axios.get(`/api/productionorders/${orderId}`);
        const ord = resp?.data?.order;
        if (ord?.created_at && !createdAtById.value[orderId]) createdAtById.value[orderId] = ord.created_at;
        if (ord?.user?.name && !creatorById.value[orderId]) creatorById.value[orderId] = ord.user.name;
      } catch {}
    }
  } catch (e) {
    console.error('Greška pri učitavanju odobrenja', e);
  }
}

async function approve(o) {
  try {
    await axios.post(`/approvals/${o.current_approval_id}/approve`, {});
    await load();
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri odobravanju');
  }
}

async function reject(o) {
  const Komentar = (prompt('Unesite komentar za odbijanje:', '') || '').trim();
  if (!Komentar) { alert('Komentar je obavezan za odbijanje.'); return; }
  try {
    await axios.post(`/approvals/${o.current_approval_id}/reject`, { Komentar });
    await load();
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri odbijanju');
  }
}

function toggleSelectAll(ev) {
  const checked = ev.target.checked;
  if (checked) {
    selected.value = visibleRows.value.map(o => o.current_approval_id);
  } else {
    selected.value = [];
  }
}

async function bulkApprove() {
  if (selected.value.length === 0) return;
  try {
    await axios.post('/approvals/bulk-approve', { approval_ids: selected.value });
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri masovnom odobravanju');
  }
  await load();
}

onMounted(() => load());

// Filtering and sorting
const visibleRows = computed(() => {
  let arr = [...rows.value];
  const needle = (q.value || '').toLowerCase();
  if (needle) {
    arr = arr.filter(o =>
      String(o.OrderNumber || '').toLowerCase().includes(needle) ||
      String(o.partner || '').toLowerCase().includes(needle) ||
      String(o.Description || '').toLowerCase().includes(needle)
    );
  }
  if (sortKey.value) {
    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;
    arr.sort((a,b) => {
      const av = (a[key] ?? '').toString().toLowerCase();
      const bv = (b[key] ?? '').toString().toLowerCase();
      if (av < bv) return -1 * dir;
      if (av > bv) return 1 * dir;
      return 0;
    });
  }
  return arr;
});

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = 'asc';
  }
}

function formatQty(v) {
  const n = Number(v ?? 0);
  if (!isFinite(n)) return '0';
  return n % 1 === 0 ? n.toString() : n.toFixed(2);
}

function totalQuantity(o) {
  if (!o) return 0;
  if (totalById.value && totalById.value[o.id] != null) return Number(totalById.value[o.id]);
  if (o.total_quantity != null) return Number(o.total_quantity);
  if (o.total_qty != null) return Number(o.total_qty);
  if (o.TotalQuantity != null) return Number(o.TotalQuantity);
  if (o.totalQty != null) return Number(o.totalQty);
  const details = Array.isArray(o.details) ? o.details : Array.isArray(o.Items) ? o.Items : [];
  return details.reduce((sum, d) => sum + Number(d?.quantity ?? d?.kolicina ?? 0), 0);
}

function formatDate(o) {
  const raw = o?.created_at || o?.CreatedAt || createdAtById.value[o?.id];
  const fallback = o?.OrderDate;
  if (!raw) return '—';
  try {
    const d = new Date(raw);
    if (isNaN(d.getTime())) {
      if (fallback) {
        const fd = new Date(fallback);
        if (!isNaN(fd.getTime())) return fd.toLocaleDateString('sr-Latn', { year: 'numeric', month: '2-digit', day: '2-digit' });
      }
      return raw;
    }
    return d.toLocaleDateString('sr-Latn', { year: 'numeric', month: '2-digit', day: '2-digit' });
  } catch { return raw; }
}

function creatorName(o) {
  return o?.user_name || o?.creator_name || (o?.user && o.user.name) || creatorById.value[o?.id] || 'Nepoznato';
}
</script>

<script>
// Tiny inline component for sort chevrons
export default {
  components: {
    SortIcon: {
      props: { active: Boolean, dir: String },
      template: `
        <span class="inline-block align-middle ml-1 text-gray-400" v-if="active">
          <svg v-if="dir==='asc'" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 01-1.414 0L10 9.414l-3.293 2.879a1 1 0 11-1.414-1.414l4-3.5a1 1 0 011.414 0l4 3.5a1 1 0 010 1.414z" clip-rule="evenodd" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 001.414 0L10 4.828l3.293 2.879a1 1 0 101.414-1.414l-4-3.5a1 1 0 00-1.414 0l-4 3.5a1 1 0 000 1.414z" clip-rule="evenodd" />
          </svg>
        </span>
      `
    }
  }
}
</script>

<style>
</style>
