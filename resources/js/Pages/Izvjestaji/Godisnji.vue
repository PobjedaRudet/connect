<template>
  <ProductionAppLayout title="Izvještaji - Godišnji">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Izvještaji — Godišnji</h2>
        <div class="hidden md:flex gap-2">
          <Link :href="route('reports.customers')" class="px-3 py-1.5 rounded-md text-sm"
                :class="currentTab==='customers' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">Kupci</Link>
          <Link :href="route('reports.products')" class="px-3 py-1.5 rounded-md text-sm"
                :class="currentTab==='products' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">Proizvodi</Link>
          <Link :href="route('reports.monthly')" class="px-3 py-1.5 rounded-md text-sm"
                :class="currentTab==='monthly' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">Mjesečni</Link>
          <Link :href="route('reports.yearly')" class="px-3 py-1.5 rounded-md text-sm"
                :class="currentTab==='yearly' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">Godišnji</Link>
        </div>
      </div>
    </template>
    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <!-- Filters + actions -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs text-gray-500">Status</label>
              <select v-model="status" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">(svi)</option>
                <option value="Na čekanju">Na čekanju</option>
                <option value="Odobreno">Odobreno</option>
                <option value="Odbijeno">Odbijeno</option>
              </select>
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
              <button @click="reload" class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Primijeni</button>
              <button @click="resetFilters" class="px-3 py-2 rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Reset</button>
              <button @click="exportCsv" class="px-3 py-2 rounded-md bg-white ring-1 ring-gray-300 text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-700">CSV</button>
              <button @click="window.print()" class="px-3 py-2 rounded-md bg-white ring-1 ring-gray-300 text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-700">Print</button>
            </div>
          </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
            <div class="text-xs text-gray-500">Ukupno naloga</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ totalOrders.toLocaleString() }}</div>
          </div>
          <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
            <div class="text-xs text-gray-500">Ukupna količina</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ totalQty.toLocaleString() }}</div>
          </div>
          <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
            <div class="text-xs text-gray-500">Prosjek po nalogu</div>
            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ avgQty.toLocaleString() }}</div>
          </div>
        </div>

        <ChartCard title="Količine po godinama" :labels="chartLabels" :datasets="chartDatasets" canvas-id="chart-godine" />

        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 bg-gray-50 dark:bg-gray-900 z-10">
                <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                  <th @click="toggleSort('y')" class="py-2 px-2 cursor-pointer select-none">Godina <SortIcon :active="sortKey==='y'" :dir="sortDir" /></th>
                  <th @click="toggleSort('orders_count')" class="py-2 px-2 cursor-pointer select-none text-right">Broj naloga <SortIcon :active="sortKey==='orders_count'" :dir="sortDir" /></th>
                  <th @click="toggleSort('total_quantity')" class="py-2 px-2 cursor-pointer select-none text-right">Uk. količina <SortIcon :active="sortKey==='total_quantity'" :dir="sortDir" /></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in displayedRows" :key="r.y" class="border-b border-gray-100 dark:border-gray-700 odd:bg-gray-50/50 dark:odd:bg-white/5 hover:bg-indigo-50/40 dark:hover:bg-indigo-900/20">
                  <td class="py-2 px-2">{{ r.y }}</td>
                  <td class="py-2 px-2 text-right">{{ Number(r.orders_count||0).toLocaleString() }}</td>
                  <td class="py-2 px-2 text-right">{{ Number(r.total_quantity || 0).toLocaleString() }}</td>
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
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';
import ChartCard from './components/ChartCard.vue';
import SortIcon from './components/SortIcon.vue';

const props = defineProps({
  status: String,
  rows: { type: Array, default: () => [] }
});

const status = ref(props.status || 'Odobreno');
const rows = ref(props.rows || []);
const currentTab = 'yearly';

// KPIs
const totalOrders = computed(() => rows.value.reduce((a, r) => a + Number(r.orders_count||0), 0));
const totalQty = computed(() => rows.value.reduce((a, r) => a + Number(r.total_quantity||0), 0));
const avgQty = computed(() => totalOrders.value ? Math.round(totalQty.value / totalOrders.value) : 0);

const chartLabels = computed(() => rows.value.map(r => r.y));
const chartDatasets = computed(() => [{
  label: 'Uk. količina',
  backgroundColor: '#ef4444',
  data: rows.value.map(r => Number(r.total_quantity || 0))
}]);

// Sorting
const sortKey = ref('y');
const sortDir = ref('asc');
const displayedRows = computed(() => {
  const copy = [...rows.value];
  copy.sort((a,b) => {
    const av = a[sortKey.value] ?? 0; const bv = b[sortKey.value] ?? 0;
    return sortDir.value==='asc' ? (av - bv) : (bv - av);
  });
  return copy;
});
function toggleSort(key){
  if (sortKey.value === key) { sortDir.value = sortDir.value==='asc' ? 'desc' : 'asc'; }
  else { sortKey.value = key; sortDir.value = key==='y' ? 'asc' : 'desc'; }
}

async function reload() {
  const { data } = await axios.get('/api/izvjestaji/godisnji', { params: { status: status.value } });
  rows.value = Array.isArray(data) ? data : [];
}

function resetFilters(){
  status.value = 'Odobreno';
  reload();
}

function exportCsv(){
  const headers = ['Godina','Broj naloga','Ukupna kolicina'];
  const lines = displayedRows.value.map(r => [r.y, r.orders_count, r.total_quantity]);
  const csv = [headers, ...lines].map(row => row.map(v => `"${(v??'').toString().replace(/"/g,'""')}"`).join(',')).join('\r\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = `izvjestaj-godisnji.csv`; a.click();
  URL.revokeObjectURL(url);
}
</script>
