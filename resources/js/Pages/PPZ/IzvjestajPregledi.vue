<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PpzNav from '@/Components/PpzNav.vue'
import { ref, computed } from 'vue';
import axios from 'axios';

const pregledi = ref([]);
const loading = ref(true);
const exportLoading = ref(false);

async function fetchPregledi() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/ppz-izvjestaj-pregledi');
    pregledi.value = data;
  } finally {
    loading.value = false;
  }
}

fetchPregledi();

function formatDatum(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  const dan = String(d.getDate()).padStart(2, '0');
  const mjesec = String(d.getMonth() + 1).padStart(2, '0');
  const godina = d.getFullYear();
  return `${dan}.${mjesec}.${godina}`;
}

async function exportToWord() {
  exportLoading.value = true;
  try {
    const response = await axios.post('/api/ppz-izvjestaj-pregledi-word', pregledi.value, {
      responseType: 'blob',
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'izvjestaj_pregledi.docx');
    document.body.appendChild(link);
    link.click();
    link.remove();
  } finally {
    exportLoading.value = false;
  }
}
</script>

<template>
  <AppLayout title="Izvještaj - Ljekarski pregledi">
    <PpzNav />
    <div class="min-h-screen bg-gray-100 p-8 mx-8">
      <div v-if="loading" class="text-center py-8 text-gray-500">Učitavanje...</div>
      <div v-else>
        <div class="mb-4 flex justify-end">
          <button @click="exportToWord" :disabled="exportLoading" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow flex items-center">
            <span v-if="exportLoading" class="animate-spin mr-2">⏳</span>
            Export u Word
          </button>
        </div>
        <table class="table-auto w-full bg-white shadow rounded">
          <thead class="bg-gray-200">
            <tr>
              <th class="px-4 py-2 text-center">#</th>
              <th class="px-4 py-2 text-left">Organizacija</th>
              <th class="px-4 py-2 text-center">Datum pregleda</th>
              <th class="px-4 py-2 text-left">Prezime (Srednje ime) Ime</th>
              <th class="px-4 py-2 text-left">Radno mjesto</th>
              <th class="px-4 py-2 text-center">Sposobnost</th>
              <th class="px-4 py-2 text-center">Profesionalno oboljenje</th>
              <th class="px-4 py-2 text-center">Invalidnost</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, idx) in pregledi" :key="idx" class="border-t">
              <td class="px-4 py-2 text-center">{{ idx + 1 }}</td>
              <td class="px-4 py-2 text-left">{{ p.organizacija }}</td>
              <td class="px-4 py-2 text-center">{{ formatDatum(p.datum_pregleda) }}</td>
              <td class="px-4 py-2 text-left">{{ p.lastName }}<span v-if="p.middleName"> ({{ p.middleName }})</span> {{ p.firstName }}</td>
              <td class="px-4 py-2 text-left">{{ p.radno_mjesto }}</td>
              <td class="px-4 py-2 text-center">{{ p.type }}</td>
              <td class="px-4 py-2 text-center">{{ p.profesionalno_oboljenje }}</td>
              <td class="px-4 py-2 text-center">{{ p.invalidnost_radnika }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
