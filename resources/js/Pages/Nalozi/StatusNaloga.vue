<template>
  <ProductionAppLayout title="Status naloga">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Status naloga</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Pregled naloga sa filterima i brzim akcijama.</p>
        </div>
        <div class="hidden sm:flex items-center gap-3 text-sm">
          <span class="text-gray-600 dark:text-gray-300">Ukupno:</span>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-medium">{{ total }}</span>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="px-4 pt-4 flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Pretraga</label>
              <input v-model="q" @input="queueLoad" type="text" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200" placeholder="Broj, partner ili proizvod..." />
            </div>
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Status</label>
              <select v-model="status" @change="queueLoad" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200">
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

          <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gray-50 dark:bg-gray-700/70 text-gray-700 dark:text-gray-200">
                  <th :class="['px-3 py-2 select-none cursor-pointer', thAlign]" @click="setSort('OrderNumber')">
                    Broj <span class="text-[10px] opacity-70" v-if="sortKey==='OrderNumber'">{{ sortIndicator }}</span>
                  </th>
                 <!--  <th :class="['px-3 py-2 select-none cursor-pointer', thAlign]" @click="setSort('OrderDate')">
                    Datum <span class="text-[10px] opacity-70" v-if="sortKey==='OrderDate'">{{ sortIndicator }}</span>
                  </th> -->
                  <th :class="['px-3 py-2 select-none cursor-pointer', thAlign]" @click="setSort('partner')">
                    Partner <span class="text-[10px] opacity-70" v-if="sortKey==='partner'">{{ sortIndicator }}</span>
                  </th>
                  <th :class="['px-3 py-2 select-none cursor-pointer', thAlign]" @click="setSort('creator')">
                    Kreirao <span class="text-[10px] opacity-70" v-if="sortKey==='creator'">{{ sortIndicator }}</span>
                  </th>
                  <th :class="['px-3 py-2 select-none cursor-pointer', thAlign]" @click="setSort('Status')">
                    Status <span class="text-[10px] opacity-70" v-if="sortKey==='Status'">{{ sortIndicator }}</span>
                  </th>
                  <th :class="['px-3 py-2 select-none cursor-pointer', thAlign]" @click="setSort('total_quantity')">
                    Uk. količina <span class="text-[10px] opacity-70" v-if="sortKey==='total_quantity'">{{ sortIndicator }}</span>
                  </th>
                  <th :class="['px-3 py-2 select-none cursor-pointer', thAlign]" @click="setSort('created_at')">
                    Kreirano <span class="text-[10px] opacity-70" v-if="sortKey==='created_at'">{{ sortIndicator }}</span>
                  </th>
                  <th :class="['px-3 py-2', thAlign]" style="min-width:180px;">Akcija</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="o in displayRows" :key="o.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td :class="['px-3 py-2 align-top', tdAlign]">
                    <div class="font-medium">
                      <a :href="`/productionorders/${o.id}`" class="text-blue-600 hover:underline">{{ o.OrderNumber }}</a>
                    </div>
                    <div v-if="o.Description" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ o.Description }}</div>
                  </td>
              <!--     <td :class="['px-3 py-2 align-top', tdAlign]">{{ formatDateOnly(o.OrderDate) }}</td> -->
                  <td :class="['px-3 py-2 align-top', tdAlign]">
                    <span v-if="o.partner?.name" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">{{ o.partner.name }}</span>
                  </td>
                  <td :class="['px-3 py-2 align-top', tdAlign]">{{ o.creator?.name ?? '' }}</td>
                  <td :class="['px-3 py-2 align-top', tdAlign]">
                    <div v-if="o.is_void">
                      <span class="text-red-600 font-bold text-xs align-middle">Poništen</span>
                      <div class="text-gray-500 text-[11px] mt-0.5">{{ o.Status || 'Na čekanju' }}</div>
                    </div>
                    <span v-else :class="statusClass(o.Status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold tracking-wide">
                      {{ o.Status || 'Na čekanju' }}
                    </span>
                  </td>
                  <td :class="['px-3 py-2 align-top', tdAlign]">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ formatQty(totalQuantity(o)) }}</span>
                  </td>
                  <td :class="['px-3 py-2 align-top whitespace-nowrap', tdAlign]">{{ formatDateHuman(o.created_at) }}</td>
                  <td :class="['px-3 py-2 align-top whitespace-nowrap', tdAlign]" style="min-width:180px;">
                    <div class="flex flex-nowrap items-center gap-1" :class="{ 'justify-center': isChiefOperations }">
                      <a :href="`/productionorders/${o.id}/export-word`"
                         class="px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-1"
                         title="Preuzmi Word dokument"
                         target="_blank" rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <span class="hidden sm:inline">Word</span>
                      </a>
                      <div v-if="((o.Status || '').toLowerCase().startsWith('na odobrenju')) && (o.one_up_pending_count > 0) && (o.my_step_approved_count > 0)">
                        <button @click="approveOneUp(o)" :title="tooltipText" class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 flex items-center gap-1">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                          <span class="hidden sm:inline">Odobri</span>
                        </button>
                      </div>
                      <button v-if="canEdit(o) && isOwner(o)" @click="goEdit(o)" class="px-2 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13h3l8-8a2.828 2.828 0 00-4-4l-8 8v3z" /></svg>
                        <span class="hidden sm:inline">Uredi</span>
                      </button>
                      <button v-if="isPending(o) && isOwner(o)" @click="removeOrder(o)" class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        <span class="hidden sm:inline">Obriši</span>
                      </button>
                      <button v-if="isOwner(o)" @click="duplicate(o)" class="px-2 py-1 bg-gray-700 text-white rounded hover:bg-gray-800 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16h8M8 12h8m-7 8h6a2 2 0 002-2V6a2 2 0 00-2-2H9a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span class="hidden sm:inline">Dupliciraj</span>
                      </button>
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
import { ref, onMounted, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

const rows = ref([]);
const page = ref(1);
const total = ref(0);
const perPage = ref(20);
const status = ref('');
const q = ref('');
const oneUpTarget = ref(null);
const pageCtx = usePage();
const currentUserId = computed(() => pageCtx?.props?.auth?.user?.id ?? null);
const userFunkcija = computed(() => pageCtx?.props?.auth?.user?.funkcija ?? null);
const isChiefOperations = computed(() => userFunkcija.value === 'Šef Operative');
const thAlign = computed(() => isChiefOperations.value ? 'text-center' : 'text-left');
const tdAlign = computed(() => isChiefOperations.value ? 'text-center' : '');

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
const tooltipText = computed(() => {
  return oneUpTarget.value ? `Odobri kao proxy za: ${oneUpTarget.value}` : 'Odobri (1 nivo iznad)';
});

// Client-side sorting for current page
const sortKey = ref('created_at');
const sortDir = ref('desc');
const sortIndicator = computed(() => (sortDir.value === 'asc' ? '▲' : '▼'));

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

// Debounced auto-refresh while typing / changing filters
let searchDebounce = null;
function queueLoad() {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => load(1), 350);
}

watch(q, queueLoad);
watch(status, queueLoad);

function formatDate(dt) {
  if (!dt) return '';
  try { return new Date(dt).toLocaleString(); } catch { return dt; }
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

function formatDateHuman(dt) {
  if (!dt) return '';
  try {
    const d = new Date(dt);
    const dd = pad2(d.getDate());
    const mm = pad2(d.getMonth() + 1);
    const yyyy = d.getFullYear();
    const HH = pad2(d.getHours());
    const MM = pad2(d.getMinutes());
    return `${dd}/${mm}/${yyyy} ${HH}:${MM}`;
  } catch { return dt; }
}

function pad2(n) { return String(n).padStart(2,'0'); }

onMounted(() => load(1));

async function approveOneUp(o) {
  try {
    await axios.post(`/approvals/order/${o.id}/approve-one-up`, {});
    await load(page.value);
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri odobravanju (1 nivo iznad)');
  }
}

function canEdit(o) {
  const st = (o.Status || '').toLowerCase();
  if (st.startsWith('na odobrenju')) return false;
  if (st === 'odobreno' || st === 'odbijeno') return false;
  return true;
}

function isPending(o) {
  const s = (o.Status || '').toLowerCase();
  return s === 'na čekanju' || s === 'na čekanju' || s === 'na cekanju' || s === '';
}

function isOwner(o) {
  const uid = Number(currentUserId?.value ?? NaN);
  const oid = Number(o?.user_id ?? NaN);
  return Number.isFinite(uid) && Number.isFinite(oid) && uid === oid;
}

function goEdit(o) {
  window.location.href = `/nalozi/nalozi-za-proizvodnju?edit=${o.id}`;
}

async function duplicate(o) {
  try {
    const { data } = await axios.post(`/productionorders/${o.id}/duplicate`, {});
    const newId = data?.id;
    if (newId) {
      // Open edit page for the new order so user can adjust
      window.location.href = `/nalozi/nalozi-za-proizvodnju?edit=${newId}`;
    } else {
      alert('Nalog je dupliciran.');
      await load(page.value);
    }
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri dupliciranju naloga');
  }
}

async function removeOrder(o) {
  if (!isOwner(o)) { alert('Možete brisati samo svoje naloge.'); return; }
  if (!isPending(o)) { alert('Moguće je obrisati samo naloge u statusu "Na čekanju".'); return; }
  if (!confirm(`Obrisati nalog ${o.OrderNumber}?`)) return;
  try {
    await axios.delete(`/productionorders/${o.id}`);
    await load(page.value);
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri brisanju naloga');
  }
}

// Derived rows with client-side sorting
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

function setSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortDir.value = key === 'created_at' || key === 'OrderDate' ? 'desc' : 'asc';
  }
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
</script>

<style>
</style>
