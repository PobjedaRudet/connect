<template>
  <ProductionAppLayout title="Izvještaji - Mjesečni">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Izvještaji — Mjesečni</h2>
    </template>
    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs text-gray-500">Godina</label>
            <input v-model.number="year" type="number" class="form-input w-full mt-1" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Status</label>
            <select v-model="status" class="form-input w-full mt-1">
              <option value="">(svi)</option>
              <option value="Na čekanju">Na čekanju</option>
              <option value="Odobreno">Odobreno</option>
              <option value="Odbijeno">Odbijeno</option>
            </select>
          </div>
          <div class="flex items-end">
            <button @click="reload" class="px-4 py-2 bg-gray-800 text-white rounded">Primijeni</button>
          </div>
        </div>

        <ChartCard title="Količine po mjesecima" :labels="chartLabels" :datasets="chartDatasets" canvas-id="chart-mjeseci" />

        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                  <th class="py-2 px-2">Godina</th>
                  <th class="py-2 px-2">Mjesec</th>
                  <th class="py-2 px-2">Broj naloga</th>
                  <th class="py-2 px-2">Uk. količina</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rows" :key="`${r.y}-${r.m}`" class="border-b border-gray-100 dark:border-gray-700">
                  <td class="py-2 px-2">{{ r.y }}</td>
                  <td class="py-2 px-2">{{ r.m }}</td>
                  <td class="py-2 px-2">{{ r.orders_count }}</td>
                  <td class="py-2 px-2">{{ Number(r.total_quantity || 0).toLocaleString() }}</td>
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
import { ref, computed } from 'vue';
import axios from 'axios';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';
import ChartCard from './components/ChartCard.vue';

const props = defineProps({
  year: Number,
  status: String,
  rows: { type: Array, default: () => [] }
});

const year = ref(props.year || new Date().getFullYear());
const status = ref(props.status || 'Odobreno');
const rows = ref(props.rows || []);

const monthNames = ['1','2','3','4','5','6','7','8','9','10','11','12'];
const chartLabels = computed(() => rows.value.map(r => monthNames[(r.m-1)]));
const chartDatasets = computed(() => [{
  label: 'Uk. količina',
  backgroundColor: '#f59e0b',
  data: rows.value.map(r => Number(r.total_quantity || 0))
}]);

async function reload() {
  const { data } = await axios.get('/api/izvjestaji/mjesecni', { params: { year: year.value, status: status.value } });
  rows.value = Array.isArray(data) ? data : [];
}
</script>
