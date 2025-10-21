<template>
  <ProductionAppLayout title="Izvještaji - Po kupcima">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Izvještaji — Po kupcima</h2>
    </template>
    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-xs text-gray-500">Od datuma</label>
            <input v-model="from" type="date" class="form-input w-full mt-1" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Do datuma</label>
            <input v-model="to" type="date" class="form-input w-full mt-1" />
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

        <ChartCard title="Količine po kupcima" :labels="chartLabels" :datasets="chartDatasets" canvas-id="chart-kupci" />

        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                  <th class="py-2 px-2">Kupac</th>
                  <th class="py-2 px-2">Broj naloga</th>
                  <th class="py-2 px-2">Uk. količina</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rows" :key="r.partner_id ?? r.partner_name" class="border-b border-gray-100 dark:border-gray-700">
                  <td class="py-2 px-2">{{ r.partner_name }}</td>
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
  from: String,
  to: String,
  status: String,
  rows: { type: Array, default: () => [] }
});

const from = ref(props.from);
const to = ref(props.to);
const status = ref(props.status || 'Odobreno');
const rows = ref(props.rows || []);

const chartLabels = computed(() => rows.value.map(r => r.partner_name));
const chartDatasets = computed(() => [{
  label: 'Uk. količina',
  backgroundColor: '#4f46e5',
  data: rows.value.map(r => Number(r.total_quantity || 0))
}]);

async function reload() {
  const { data } = await axios.get('/api/izvjestaji/kupci', { params: { from: from.value, to: to.value, status: status.value } });
  rows.value = Array.isArray(data) ? data : [];
}
</script>
