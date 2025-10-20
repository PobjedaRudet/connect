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

          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                  <th class="px-3 py-2 text-left w-8"><input type="checkbox" v-model="selectAll" @change="toggleAll"/></th>
                  <th class="px-3 py-2 text-left">Broj</th>
                  <th class="px-3 py-2 text-left">Datum</th>
                  <th class="px-3 py-2 text-left">Partner</th>
                  <th class="px-3 py-2 text-left">Kreirao</th>
                  <th class="px-3 py-2 text-left">Status</th>
                  <th class="px-3 py-2 text-left">Kreirano</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in rows" :key="o.id" class="border-b border-gray-200 dark:border-gray-700">
                  <td class="px-3 py-2"><input type="checkbox" v-model="o._sel" :disabled="(o.Status||'').startsWith('na odobrenju') || o.Status==='odobreno'"/></td>
                  <td class="px-3 py-2">
                    <div class="font-medium">
                      <a :href="route('productionorders.show', { order: o.id })" class="text-blue-600 hover:underline">{{ o.OrderNumber }}</a>
                    </div>
                    <div v-if="o.Description" class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ o.Description }}</div>
                  </td>
                  <td class="px-3 py-2">{{ formatDateOnly(o.OrderDate) }}</td>
                  <td class="px-3 py-2">{{ o.partner?.name ?? '' }}</td>
                  <td class="px-3 py-2">{{ o.creator?.name ?? '' }}</td>
                  <td class="px-3 py-2">{{ o.Status }}</td>
                  <td class="px-3 py-2">{{ formatDate(o.created_at) }}</td>
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
const status = ref('');
const q = ref('');

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
const selectAll = ref(false);

async function load(p=1) {
  page.value = p;
  try {
    const { data } = await axios.get('/productionorders/created', {
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

function toggleAll() {
  rows.value = rows.value.map(o => ({...o, _sel: selectAll.value && (!((o.Status||'').startsWith('na odobrenju')) && o.Status!=='odobreno')}));
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

watch([status, q], () => load(1));
onMounted(() => load(1));
</script>

<style>
</style>
