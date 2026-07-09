<template>
  <AppLayout title="Kontrolni pregledi">
    <PpzNav />
    <div class="p-6 flex justify-center">
      <div class="w-full max-w-4xl">
        <h2 class="text-lg font-semibold mb-4">Lista uposlenika za kontrolni ljekarski pregled</h2>
        <div class="flex justify-between items-center mb-2">
          <input v-model="search" type="text" placeholder="Pretraži po imenu ili prezimenu..." class="border rounded px-3 py-2 w-72 mr-4" />
          <div>
            <label class="mr-2 font-medium">Prikaži:</label>
            <select v-model="rowsPerPage" class="form-select border rounded px-2 py-1" style="width: 5rem;">
              <option v-for="option in [10, 20, 50, 100]" :key="option" :value="option">{{ option }}</option>
            </select>
            <span class="ml-2">redova</span>
          </div>
        </div>
        <div class="overflow-x-auto rounded-lg shadow bg-white">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr class="bg-blue-100">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Ime i prezime</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">SAP Id</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Pozicija</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Datum pregleda</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Ustanova</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider"></th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-100">
              <tr v-for="(item, index) in pagedPregledi" :key="item.id">
                <td class="px-6 py-4 text-center">{{ (currentPage - 1) * rowsPerPage + index + 1 }}</td>
                <td class="px-6 py-4">{{ item.employee.firstName }} {{ item.employee.lastName }}</td>
                <td class="px-6 py-4">{{ item.employee.empID }}</td>
                <td class="px-6 py-4">{{ item.employee.radno_mjesto }}</td>
                <td class="px-6 py-4">{{ formatDate(item.datum_pregleda) }}</td>
                <td class="px-6 py-4">{{ item.organizacija }}</td>
                <td class="px-6 py-4">
                  <button @click="openPopup(item)" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Unesi</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-between items-center mt-4">
          <div>
            Prikazano {{ startRow + 1 }} - {{ endRow }} od ukupno {{ kontrolniPregledi.length }}
          </div>
          <div class="space-x-2">
            <button @click="prevPage" :disabled="currentPage === 1" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 disabled:opacity-50">Prethodna</button>
            <span>Stranica {{ currentPage }} / {{ totalPages }}</span>
            <button @click="nextPage" :disabled="currentPage === totalPages" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 disabled:opacity-50">Sljedeća</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Popup -->
    <div v-if="showPopup" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
      <div class="bg-white rounded-lg shadow-lg p-8 max-w-sm w-full">
        <h3 class="text-lg font-semibold mb-4">Unos kontrolnog pregleda</h3>
        <form @submit.prevent="submitPopup">
          <div class="mb-4">
            <label class="block text-sm font-medium mb-1">Datum kontrolnog pregleda</label>
            <input type="date" v-model="popupForm.datum" class="form-input w-full" required />
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium mb-1">Komentar</label>
            <textarea v-model="popupForm.komentar" class="form-textarea w-full" rows="2"></textarea>
          </div>
          <div class="mb-4">
            <label class="block text-sm font-medium mb-1">Dodatni kontrolni pregled</label>
            <input type="checkbox" v-model="popupForm.status" class="form-checkbox mr-2" />
            <span class="text-sm">Treba zakazati novi kontrolni pregled</span>
          </div>
          <div class="flex justify-end space-x-3">
            <button type="button" @click="closePopup" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">Odustani</button>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Spremi</button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PpzNav from '@/Components/PpzNav.vue'
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

const kontrolniPregledi = ref([]);
const rowsPerPage = ref(10);
const currentPage = ref(1);
const showPopup = ref(false);
const popupForm = ref({ datum: '', komentar: '', status: false });
const selectedEmployee = ref(null);
const search = ref('');

const totalPages = computed(() => Math.ceil(kontrolniPregledi.value.length / rowsPerPage.value) || 1);
const startRow = computed(() => (currentPage.value - 1) * rowsPerPage.value);
const endRow = computed(() => Math.min(startRow.value + rowsPerPage.value, kontrolniPregledi.value.length));
const filteredPregledi = computed(() => {
  if (!search.value) return kontrolniPregledi.value;
  return kontrolniPregledi.value.filter(item => {
    const imePrezime = `${item.employee.firstName} ${item.employee.lastName}`.toLowerCase();
    return imePrezime.includes(search.value.toLowerCase());
  });
});
const pagedPregledi = computed(() => filteredPregledi.value.slice(startRow.value, endRow.value));

const formatDate = (dateStr) => {
  const date = new Date(dateStr);
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}.${month}.${year}`;
};

const fetchKontrolniPregledi = async () => {
  const { data } = await axios.get('/api/pregledi/kontrolni');
  kontrolniPregledi.value = data;
  currentPage.value = 1;
};

const prevPage = () => {
  if (currentPage.value > 1) currentPage.value--;
};
const nextPage = () => {
  if (currentPage.value < totalPages.value) currentPage.value++;
};

watch(rowsPerPage, () => {
  currentPage.value = 1;
});

function openPopup(item) {
  selectedEmployee.value = item;
  popupForm.value = { datum: '', komentar: '', status: false };
  showPopup.value = true;
}
function closePopup() {
  showPopup.value = false;
}
async function submitPopup() {
  if (!selectedEmployee.value) return;
  try {
    await axios.post('/api/kontrolni-pregledi', {
      pregledi_id: selectedEmployee.value.id,
      employee_id: selectedEmployee.value.employee.id,
      datum_kontrolnog_pregleda: popupForm.value.datum,
      kontrolni_komentar: popupForm.value.komentar,
      status: popupForm.value.status ? 1 : 0,
    });
    showPopup.value = false;
    await fetchKontrolniPregledi();
  } catch (e) {
    const msg = e?.response?.data?.message
      || Object.values(e?.response?.data?.errors || {}).flat().join('\n')
      || 'Greška pri unosu kontrolnog pregleda!';
    alert(msg);
  }
}

onMounted(fetchKontrolniPregledi);
</script>
