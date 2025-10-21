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

          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                  <th class="px-3 py-2 text-left">Broj</th>
                  <th class="px-3 py-2 text-left">Datum</th>
                  <th class="px-3 py-2 text-left">Partner</th>
                  <th class="px-3 py-2 text-left">Kreirao</th>
                  <th class="px-3 py-2 text-left">Status</th>
                  <th class="px-3 py-2 text-left">Kreirano</th>
                  <th class="px-3 py-2 text-left">Akcija</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in rows" :key="o.id" class="border-b border-gray-200 dark:border-gray-700">
                  <td class="px-3 py-2">
                    <div class="font-medium">
                      <a :href="`/productionorders/${o.id}`" class="text-blue-600 hover:underline">{{ o.OrderNumber }}</a>
                    </div>
                    <div v-if="o.Description" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ o.Description }}</div>
                  </td>
                  <td class="px-3 py-2">{{ formatDateOnly(o.OrderDate) }}</td>
                  <td class="px-3 py-2">{{ o.partner?.name ?? '' }}</td>
                  <td class="px-3 py-2">{{ o.creator?.name ?? '' }}</td>
                  <td class="px-3 py-2">{{ o.Status }}</td>
                  <td class="px-3 py-2">{{ formatDate(o.created_at) }}</td>
                  <td class="px-3 py-2">
                    <div class="flex items-center gap-2">
                      <div v-if="((o.Status || '').toLowerCase().startsWith('na odobrenju')) && (o.one_up_pending_count > 0) && (o.my_step_approved_count > 0)">
                        <button @click="approveOneUp(o)" :title="tooltipText" class="px-3 py-1 bg-red-700 text-white rounded hover:bg-red-800">Odobri (1 nivo iznad)</button>
                      </div>
                      <button v-if="canEdit(o) && isOwner(o)" @click="goEdit(o)" class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600">Uredi</button>
                      <button v-if="isPending(o) && isOwner(o)" @click="removeOrder(o)" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">Obriši</button>
                      <button v-if="isOwner(o)" @click="duplicate(o)" class="px-3 py-1 bg-gray-700 text-white rounded hover:bg-gray-800">Dupliciraj</button>
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

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
const tooltipText = computed(() => {
  return oneUpTarget.value ? `Odobri kao proxy za: ${oneUpTarget.value}` : 'Odobri (1 nivo iznad)';
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
    return d.toLocaleDateString();
  } catch { return dt; }
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
</script>

<style>
</style>
