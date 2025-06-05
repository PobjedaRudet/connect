<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios';

// Lista radnika dobijena iz Laravel kontrolera
const props = defineProps({
  radnici: Array
})

const rowsPerPage = 20;
const currentPage = ref(1);
const search = ref('');
const filteredRadnici = computed(() => {
  if (!search.value) return props.radnici;
  return props.radnici.filter(r =>
    (r.firstName + ' ' + r.lastName).toLowerCase().includes(search.value.toLowerCase())
  );
});
const totalPages = computed(() => Math.ceil(filteredRadnici.value.length / rowsPerPage) || 1);
const pagedRadnici = computed(() => filteredRadnici.value.slice((currentPage.value - 1) * rowsPerPage, currentPage.value * rowsPerPage));
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };

const selectedRadnik = ref(null);
const pregledi = ref([]);
const kontrolniPregledi = ref([]);
const showDetails = ref(false);
const selectedPregledId = ref(null);
function toggleKontrolniPregledi(pregledId) {
  selectedPregledId.value = selectedPregledId.value === pregledId ? null : pregledId;
}

async function prikaziPreglede(radnik) {
    console.log('Prikazivanje pregleda za radnika:', radnik);
  selectedRadnik.value = radnik;
  // Dohvati sve preglede za uposlenika
  const { data: preglediData } = await axios.get(`/api/employee/${radnik.id}/pregledi`);
  pregledi.value = preglediData;
  console.log('Dohvaćeni pregledi:', preglediData);
  // Dohvati sve kontrolne preglede povezane na te preglede
  const pregledIds = preglediData.map(p => p.id);
  if (pregledIds.length > 0) {
    const { data: kontrolniData } = await axios.get(`/api/kontrolni-pregledi/by-pregledi`, { params: { ids: pregledIds } });
    kontrolniPregledi.value = kontrolniData;
    console.log('Dohvaćeni kontrolni pregledi:', kontrolniData);
  } else {
    kontrolniPregledi.value = [];
  }
  showDetails.value = true;
}
function closeDetails() {
  showDetails.value = false;
  selectedRadnik.value = null;
  pregledi.value = [];
  kontrolniPregledi.value = [];
}
</script>

<template>
  <AppLayout title="Ljekarski pregledi">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Ljekarski pregledi
      </h2>
    </template>
    <div class="min-h-screen bg-gray-100 p-8">

      <div class="mb-4 flex justify-center">
        <input v-model="search" type="text" placeholder="Pretraži po imenu ili prezimenu..." class="border rounded px-3 py-2 w-72" />
      </div>
      <table class="table-auto w-full bg-white shadow rounded">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-4 py-2">#</th>
            <th class="px-4 py-2">Ime</th>
            <th class="px-4 py-2">Prezime</th>
            <th class="px-4 py-2">Akcije</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(radnik, idx) in pagedRadnici" :key="radnik.id" class="border-t">
            <td class="px-4 py-2 text-center">{{ (currentPage - 1) * rowsPerPage + idx + 1 }}</td>
            <td class="px-4 py-2">{{ radnik.firstName }}</td>
            <td class="px-4 py-2">{{ radnik.lastName }}</td>
            <td class="px-4 py-2">
              <button @click="prikaziPreglede(radnik)" class="text-blue-600 hover:underline">Pregledi</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="flex justify-between items-center mt-4">
        <div>
          Prikazano {{ (currentPage - 1) * rowsPerPage + 1 }} - {{ Math.min(currentPage * rowsPerPage, filteredRadnici.length) }} od ukupno {{ filteredRadnici.length }}
        </div>
        <div class="space-x-2">
          <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 disabled:opacity-50">Prethodna</button>
          <span>Stranica {{ currentPage }} / {{ totalPages }}</span>
          <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 disabled:opacity-50">Sljedeća</button>
        </div>
      </div>
      <!-- Pregledi detalji modal -->
      <div v-if="showDetails" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-2xl w-full relative">
          <button @click="closeDetails" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">&times;</button>
          <h3 class="text-lg font-semibold mb-4">Pregledi za: {{ selectedRadnik.firstName }} {{ selectedRadnik.lastName }}</h3>
          <h4 class="font-semibold mb-2">Ljekarski pregledi i kontrolni pregledi</h4>
          <table class="table-auto w-full mb-6">
            <thead>
              <tr class="bg-gray-100">
                <th class="px-4 py-2">Datum</th>
                <th class="px-4 py-2">Tip</th>
                <th class="px-4 py-2">Komentar</th>
                <th class="px-4 py-2">Kontrolni pregledi</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="pregled in pregledi" :key="pregled.id">
                <tr class="border-b hover:bg-gray-50">
                  <td class="px-4 py-2 align-top">{{ pregled.datum_pregleda }}</td>
                  <td class="px-4 py-2 align-top">{{ pregled.type }}</td>
                  <td class="px-4 py-2 align-top">{{ pregled.komentar }}</td>
                  <td class="px-4 py-2">
                    <button type="button"
                      @click.stop="toggleKontrolniPregledi(pregled.id)"
                      class="text-blue-500 underline cursor-pointer focus:outline-none">
                      <span v-if="selectedPregledId !== pregled.id">Prikaži</span>
                      <span v-else>Sakrij</span>
                    </button>
                  </td>
                </tr>
                <tr v-if="selectedPregledId === pregled.id">
                  <td colspan="4" class="bg-gray-50 px-4 py-2">
                    <div v-if="kontrolniPregledi.filter(kp => kp.pregledi_id === pregled.id).length">
                      <table class="table-auto w-full border">
                        <thead>
                          <tr class="bg-gray-50">
                            <th class="px-2 py-1">Datum</th>
                            <th class="px-2 py-1">Komentar</th>
                            <th class="px-2 py-1">Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="kp in kontrolniPregledi.filter(kp => kp.pregledi_id === pregled.id)" :key="kp.id">
                            <td class="px-2 py-1">{{ kp.datum_kontrolnog_pregleda }}</td>
                            <td class="px-2 py-1">{{ kp.kontrolni_komentar }}</td>
                            <td class="px-2 py-1">{{ kp.status ? 'Aktivan' : 'Završen' }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div v-else class="text-gray-400 italic">Nema kontrolnih pregleda</div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
