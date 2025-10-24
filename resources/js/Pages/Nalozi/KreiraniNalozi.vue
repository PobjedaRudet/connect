<template>
  <ProductionAppLayout title="Kreirani nalozi">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Kreirani nalozi</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Pretraga</label>
              <input v-model="q" type="text" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200" placeholder="Broj, partner ili proizvod..." />
            </div>

            <div class="ml-auto">
              <button @click="load(1)" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Osvježi</button>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gray-50 dark:bg-gray-700/70 text-gray-700 dark:text-gray-200">
                  <th class="px-3 py-2 text-center w-8">
                    <input type="checkbox" v-model="selectAll" @change="toggleAll"/>
                  </th>
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
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('created_at')">
                    Kreirano <span class="text-[10px] opacity-70" v-if="sortKey==='created_at'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center">Akcija</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="o in displayRows" :key="o.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td class="px-3 py-2 text-center">
                    <input type="checkbox" v-model="o._sel" :disabled="o.is_void || (o.Status||'').startsWith('na odobrenju') || o.Status==='odobreno'"/>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <div class="font-medium flex flex-wrap items-center justify-center gap-2">
                      <a :href="route('productionorders.show', { order: o.id })" class="text-blue-600 hover:underline">{{ o.OrderNumber }}</a>
                      <span class="text-gray-700 dark:text-gray-200">—</span>
                      <span class="text-gray-800 dark:text-gray-100">
                        {{ primaryProductName(o) }}
                      </span>
                    </div>
                    <div v-if="o.Description" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs mx-auto">{{ o.Description }}</div>
                  </td>
                  <td class="px-3 py-2 whitespace-nowrap text-center">{{ formatDateOnly(o.OrderDate) }}</td>
                  <td class="px-3 py-2 text-center">
                    <span v-if="o.partner?.name" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                      {{ o.partner.name }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">{{ o.creator?.name ?? '' }}</td>
                  <td class="px-3 py-2 text-center">
                    <template v-if="o.is_void">
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Nevažeći</span>
                    </template>
                    <template v-else>
                      <span :class="statusClass(o.Status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px]">
                        {{ o.Status || 'Na čekanju' }}
                      </span>
                    </template>
                  </td>
                  <td class="px-3 py-2 text-center">{{ formatQty(totalQuantity(o)) }}</td>
                  <td class="px-3 py-2 whitespace-nowrap text-center">{{ formatDate(o.created_at) }}</td>
                  <td class="px-3 py-2 text-center">
                    <div class="flex items-center justify-center gap-2">
                      <button v-if="canEdit(o)" @click="goEdit(o)" class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600">Uredi</button>
                      <button v-if="isPending(o) && !o.is_void" @click="voidOrder(o)" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">Poništi</button>
                      <button @click="duplicate(o)" class="px-3 py-1 bg-gray-700 text-white rounded hover:bg-gray-800">Dupliciraj</button>
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
          <div class="mt-4 flex justify-end">
            <button @click="sendSelected" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Kreiraj odobrenja i pošalji</button>
          </div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>

</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

const rows = ref([]);
const page = ref(1);
const total = ref(0);
const perPage = ref(20);
// Always show only orders with Status = "Na čekanju" for Radnik
const status = ref('Na čekanju');
const q = ref('');

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
const selectAll = ref(false);

// Sorting state
const sortKey = ref('created_at');
const sortDir = ref('desc'); // 'asc' | 'desc'
const sortIndicator = computed(() => (sortDir.value === 'asc' ? '▲' : '▼'));

function setSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = key === 'created_at' ? 'desc' : 'asc';
  }
}

function statusWeight(s) {
  const x = (s || '').toLowerCase();
  if (x.includes('odobreno')) return 3;
  if (x.includes('na odobrenju')) return 2;
  if (x.includes('odbijeno')) return 1;
  return 0; // na čekanju / default
}

function sortValue(o, key) {
  switch (key) {
    case 'OrderNumber': return String(o.OrderNumber || '');
    case 'OrderDate': return new Date(o.OrderDate || 0).getTime();
    case 'partner': return String(o.partner?.name || '');
    case 'creator': return String(o.creator?.name || '');
    case 'Status': return statusWeight(o.Status);
    case 'total_quantity': return Number(totalQuantity(o) || 0);
    case 'created_at': return new Date(o.created_at || 0).getTime();
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
    const { data } = await axios.get('/productionorders/mine/created', {
      params: { page: page.value, status: status.value, q: q.value }
    });
    rows.value = (data.data || []).map(o => ({...o, _sel: false}));
    total.value = data.total || 0;
    perPage.value = data.per_page || 20;
    page.value = data.current_page || p;
  } catch (e) {
    console.error('Greška pri učitavanju', e);
  }
}

function pad2(n) { return String(n).padStart(2,'0'); }
function formatDate(dt) {
  if (!dt) return '';
  try {
    const d = new Date(dt);
    const dd = pad2(d.getDate());
    const mm = pad2(d.getMonth() + 1);
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
  } catch { return dt; }
}

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

function toggleAll() {
  rows.value = rows.value.map(o => ({
    ...o,
    _sel: selectAll.value && (!o.is_void) && (!((o.Status||'').startsWith('na odobrenju')) && o.Status!=='odobreno')
  }));
}

async function sendSelected() {
  const selected = rows.value.filter(o => o._sel).map(o => o.id);
  if (selected.length === 0) {
    alert('Odaberite barem jedan nalog.');
    return;
  }
  try {
    await axios.post('/approvals/send', { order_ids: selected });
    alert('Odobrenja kreirana i mail poslan nadređenima.');
    await load(page.value);
  } catch (e) {
    const msg = e?.response?.data?.message || 'Greška pri slanju na odobrenje';
    alert(msg);
  }
}

function canEdit(o) {
  const st = (o.Status || '').toLowerCase();
  if (o.is_void) return false;
  if (st.startsWith('na odobrenju')) return false;
  if (st === 'odobreno' || st === 'odbijeno') return false;
  return true;
}

function isPending(o) {
  const s = (o.Status || '').toLowerCase();
  return s === 'na čekanju' || s === 'na čekanju' || s === 'na cekanju' || s === '';
}

function goEdit(o) {
  window.location.href = `/nalozi/nalozi-za-proizvodnju?edit=${o.id}`;
}

async function duplicate(o) {
  try {
    const { data } = await axios.post(`/productionorders/${o.id}/duplicate`, {});
    const newId = data?.id;
    if (newId) {
      window.location.href = `/nalozi/nalozi-za-proizvodnju?edit=${newId}`;
    } else {
      alert('Nalog je dupliciran.');
      await load(page.value);
    }
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri dupliciranju naloga');
  }
}

function totalQuantity(o) {
  // Prefer backend aggregate if present, else sum details quantities
  if (o.total_quantity != null) return Number(o.total_quantity);
  const details = o.details || [];
  return details.reduce((sum, d) => sum + Number(d?.quantity || 0), 0);
}

function formatQty(v) {
  if (v == null || isNaN(v)) return '';
  // show integers without decimals
  return Number.isInteger(v) ? String(v) : v.toFixed(2);
}

function primaryProductName(o) {
  // Prefer first detail's product name; fallback to Description if no details
  const details = o.details || [];
  if (details.length > 0 && details[0]?.product) {
    return details[0].product.SkraceniNaziv || details[0].product.Naziv || '(proizvod)';
  }
  return o.Description || '(bez opisa)';
}

async function voidOrder(o) {
  if (!confirm(`Poništiti nalog ${o.OrderNumber}?`)) return;
  let reason = prompt('Razlog poništavanja (opciono):', '');
  try {
    await axios.delete(`/productionorders/${o.id}`, { data: { reason } });
    await load(page.value);
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri poništavanju naloga');
  }
}

// Debounced auto-refresh while typing
let searchDebounce = null;
watch([q], () => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => load(1), 350);
});
onMounted(() => load(1));
</script>

<style>
</style>
