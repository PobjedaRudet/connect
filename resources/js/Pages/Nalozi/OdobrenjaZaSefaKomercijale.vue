<template>
  <ProductionAppLayout title="OdobrenjaZaSefaKomercijale">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">OdobrenjaZaSefaKomercijale</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
          <div class="flex justify-between items-center mb-3">
            <div class="text-sm text-gray-600 dark:text-gray-300">Trenutni korak: {{ myFunkcija || '-' }}</div>
            <div class="flex items-center gap-2">
              <button @click="bulkApprove" :disabled="selected.length===0" class="px-3 py-1 rounded text-white"
                      :class="selected.length===0 ? 'bg-gray-400 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700'">
                Odobri odabrano<span v-if="selected.length>0"> ({{ selected.length }})</span>
              </button>
              <button @click="load" class="px-3 py-1 bg-blue-600 text-white rounded">Osvježi</button>
            </div>
          </div>

          <div v-if="rows.length === 0" class="text-sm text-gray-500 dark:text-gray-300">Nema naloga za odobrenje.</div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                  <th class="px-3 py-2 text-left w-8">
                    <input type="checkbox" :checked="rows.length>0 && selected.length===rows.length" @change="toggleSelectAll($event)" />
                  </th>
                  <th class="px-3 py-2 text-left">Broj</th>
                  <th class="px-3 py-2 text-left">Partner</th>
                  <th class="px-3 py-2 text-left">Opis</th>
                  <th class="px-3 py-2 text-left w-64">Akcija</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in rows" :key="o.id" class="border-b border-gray-200 dark:border-gray-700">
                  <td class="px-3 py-2">
                    <input type="checkbox" :value="o.current_approval_id" v-model="selected" />
                  </td>
                  <td class="px-3 py-2 font-medium">
                    <a :href="`/productionorders/${o.id}`" class="text-blue-600 hover:underline">{{ o.OrderNumber }}</a>
                  </td>
                  <td class="px-3 py-2">{{ o.partner || '' }}</td>
                  <td class="px-3 py-2">
                    <div class="truncate max-w-lg" :title="o.Description">{{ o.Description }}</div>
                  </td>
                  <td class="px-3 py-2">
                    <div class="flex gap-2 items-center">
                      <button @click="approve(o)" class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700">Odobri</button>
                      <input v-model="o._comment" type="text" placeholder="Komentar (obavezno za odbijanje)" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200 w-48"/>
                      <button @click="reject(o)" class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">Odbij</button>
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
import { ref, onMounted } from 'vue';
import axios from 'axios';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

const rows = ref([]);
const myFunkcija = ref('');
const selected = ref([]); // holds current_approval_id values

async function load() {
  try {
    const { data } = await axios.get('/approvals/pending');
    // data = { data: [{id, OrderNumber, Description, partner, current_approval_id}] }
    rows.value = (data?.data || []).map(o => ({...o, _comment: ''}));
    // sanitize selection to only include approvals that are still visible
    const visibleIds = new Set(rows.value.map(o => o.current_approval_id));
    selected.value = selected.value.filter(id => visibleIds.has(id));
    // Infer funkcija from status if needed; left empty since API doesn't return it
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
  const Komentar = (o._comment || '').trim();
  if (!Komentar) {
    alert('Komentar je obavezan za odbijanje.');
    return;
  }
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
    selected.value = rows.value.map(o => o.current_approval_id);
  } else {
    selected.value = [];
  }
}

async function bulkApprove() {
  if (selected.value.length === 0) return;
  // optional confirmation
  // if (!confirm(`Odobriti ${selected.value.length} odabranih naloga?`)) return;
  const ids = [...selected.value];
  const results = await Promise.allSettled(ids.map(id => axios.post(`/approvals/${id}/approve`, {})));
  const ok = results.filter(r => r.status === 'fulfilled').length;
  const fail = results.length - ok;
  if (fail > 0) {
    alert(`Odobreno: ${ok}, Neuspješno: ${fail}.`);
  }
  await load();
}

onMounted(() => load());
</script>

<style>
</style>
