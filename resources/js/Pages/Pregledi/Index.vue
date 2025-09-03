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

async function promijeniStatus(radnik) {
  const noviStatus = radnik.status == 1 ? 0 : 1;
  try {
    await axios.put(`/api/employees/${radnik.empID}/status`, { status: noviStatus });
    radnik.status = noviStatus;
  } catch (e) {
    alert('Greška pri promjeni statusa!');
  }
}
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
  const { data: preglediData } = await axios.get(`/api/employee/${radnik.empID}/pregledi`);
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
function formatDatum(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  const dan = String(d.getDate()).padStart(2, '0');
  const mjesec = String(d.getMonth() + 1).padStart(2, '0');
  const godina = d.getFullYear();
  return `${dan}.${mjesec}.${godina}`;
}

const showEditModal = ref(false);
const editKontrolni = ref(null);

function izmeniKontrolniPregled(kp) {
  editKontrolni.value = { ...kp };
  showEditModal.value = true;
}
function closeEditModal() {
  showEditModal.value = false;
  editKontrolni.value = null;
}
async function sacuvajIzmjenuKontrolnog() {
  if (!editKontrolni.value) return;
  try {
    await axios.put(`/api/kontrolni-pregledi/${editKontrolni.value.id}`, editKontrolni.value);
    closeEditModal();
    // Osvježi podatke o pregledima nakon izmjene
    const { data: preglediData } = await axios.get(`/api/employee/${selectedRadnik.value.empID}/pregledi`);
    pregledi.value = preglediData;
    const pregledIds = preglediData.map(p => p.id);
    if (pregledIds.length > 0) {
      const { data: kontrolniData } = await axios.get(`/api/kontrolni-pregledi/by-pregledi`, { params: { ids: pregledIds } });
      kontrolniPregledi.value = kontrolniData;
    } else {
      kontrolniPregledi.value = [];
    }
  } catch (error) {
    console.error('Greška prilikom čuvanja izmjena:', error);
  }
}
function izracunajSljedeciTermin(lastExamDate, period) {
  if (!lastExamDate || !period) return '';
  const d = new Date(lastExamDate);
  if (isNaN(d)) return '';
  d.setMonth(d.getMonth() + Number(period));
  const dan = String(d.getDate()).padStart(2, '0');
  const mjesec = String(d.getMonth() + 1).padStart(2, '0');
  const godina = d.getFullYear();
  return `${dan}.${mjesec}.${godina}`;
}

const showEditPregledModal = ref(false);
const editPregled = ref(null);
const showEditCustomOrganizacija = ref(false);
function onEditOrganizacijaChange(e) {
  if (e.target.value === 'custom') {
    showEditCustomOrganizacija.value = true;
    editPregled.value.organizacija = '';
  } else {
    showEditCustomOrganizacija.value = false;
    editPregled.value.organizacija = e.target.value;
  }
}

async function azurirajPregled(pregled) {
  editPregled.value = { ...pregled };
  // Prikaži custom input ako organizacija nije jedna od ponuđenih
  if (
    editPregled.value.organizacija !== "J.U. Dom zdravlja 'Dr.Isak Samokovlija' Goražde" &&
    editPregled.value.organizacija !== "PZU 'Eurofarm-Centar Poliklinika' PJ Goražde"
  ) {
    showEditCustomOrganizacija.value = true;
  } else {
    showEditCustomOrganizacija.value = false;
  }
  showEditPregledModal.value = true;
}

async function sacuvajIzmjenuPregleda() {
  if (!editPregled.value) return;
  if (!editPregled.value.organizacija || editPregled.value.organizacija.trim() === '') {
    alert('Polje "Organizacija" je obavezno!');
    return;
  }
  try {
    // Pripremi podatke koje backend očekuje
    const payload = {
      datum_pregleda: editPregled.value.datum_pregleda,
      type: editPregled.value.type,
      komentar: editPregled.value.komentar,
      organizacija: editPregled.value.organizacija
    };
    await axios.put(`/api/pregledi/${editPregled.value.id}`, payload);
    showEditPregledModal.value = false;
    editPregled.value = null;
    // Osvježi podatke
    await prikaziPreglede(selectedRadnik.value);
  } catch (error) {
    console.error('Greška prilikom ažuriranja pregleda:', error);
  }
}

const pregledZaBrisanje = ref(null);
const showPotvrdaBrisanja = ref(false);

function potvrdiBrisanjePregleda(pregledId) {
  pregledZaBrisanje.value = pregledId;
  showPotvrdaBrisanja.value = true;
}

async function obrisiPregled() {
  if (!pregledZaBrisanje.value) return;
  try {
    await axios.delete(`/api/pregledi/${pregledZaBrisanje.value}`);
    showPotvrdaBrisanja.value = false;
    pregledZaBrisanje.value = null;
    // Osvježi podatke
    await prikaziPreglede(selectedRadnik.value);
  } catch (error) {
    console.error('Greška prilikom brisanja pregleda:', error);
  }
}

const showEditInvalidnostModal = ref(false);
const editInvalidnostRadnik = ref(null);
const novaInvalidnost = ref('');
function otvoriEditInvalidnost(radnik) {
  editInvalidnostRadnik.value = radnik;
  novaInvalidnost.value = radnik.invalidnost_radnika || '';
  showEditInvalidnostModal.value = true;
}
async function sacuvajInvalidnost() {
  if (!editInvalidnostRadnik.value) return;
  try {
    await axios.put(`/api/employees/${editInvalidnostRadnik.value.empID}/invalidnost`, { invalidnost_radnika: novaInvalidnost.value });
    editInvalidnostRadnik.value.invalidnost_radnika = novaInvalidnost.value;
    showEditInvalidnostModal.value = false;
    // Osvježi prikaz ako treba
  } catch (e) {
    alert('Greška pri ažuriranju invalidnosti!');
  }
}
</script>

<template>
  <AppLayout title="Ljekarski pregledi">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Ljekarski pregledi
      </h2>
    </template>
    <div class="min-h-screen bg-gray-100 p-8 mx-16">

      <div class="mb-4 flex justify-center">
        <input v-model="search" type="text" placeholder="Pretraži po imenu ili prezimenu..." class="border rounded px-3 py-2 w-72" />
      </div>
      <table class="table-auto w-full bg-white shadow rounded">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-4 py-2 text-center">#</th>
            <th class="px-4 py-2 text-left">Ime</th>
            <th class="px-4 py-2 text-left">Prezime</th>
            <th class="px-4 py-2 text-center">Period</th>
            <th class="px-4 py-2 text-left">Radno mjesto</th>
            <th class="px-4 py-2 text-center">Status</th>
            <th class="px-4 py-2 text-center">Invalidnost</th>
            <th class="px-4 py-2 text-center">Izmijeni invalidnost</th>
            <th class="px-4 py-2 text-center">Posljednji pregled</th>
            <th class="px-4 py-2 text-center">Sljedeći termin</th>
            <th class="px-4 py-2 text-center">Akcije</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(radnik, idx) in pagedRadnici" :key="radnik.id" class="border-t">
            <td class="px-4 py-2 text-center">{{ (currentPage - 1) * rowsPerPage + idx + 1 }}</td>
            <td class="px-4 py-2 text-left">{{ radnik.firstName }}</td>
            <td class="px-4 py-2 text-left">{{ radnik.lastName }}</td>
            <td class="px-4 py-2 text-center">{{ radnik.period }}</td>
            <td class="px-4 py-2 text-left">{{ radnik.radno_mjesto }}</td>
            <td class="px-4 py-2 text-center">
              <button
                :class="[
                  'px-3 py-1 rounded font-bold',
                  radnik.status == 1 ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
                ]"
                @click="promijeniStatus(radnik)"
              >
                {{ radnik.status == 1 ? 'Aktivan' : 'Neaktivan' }}
              </button>
            </td>
            <td class="px-4 py-2 text-center">{{ radnik.invalidnost_radnika }}</td>
            <td class="px-4 py-2 text-center">
              <button @click="otvoriEditInvalidnost(radnik)" class="bg-yellow-400 hover:bg-yellow-500 text-white px-3 py-1 rounded">Izmijeni</button>
            </td>
            <td class="px-4 py-2 text-center">
              {{ radnik.lastExamDate ? formatDatum(radnik.lastExamDate) : '' }}
            </td>
            <td class="px-4 py-2 text-center">
              {{ izracunajSljedeciTermin(radnik.lastExamDate, radnik.period) }}
            </td>
            <td class="px-4 py-2 text-center">
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
                <th class="px-4 py-2">Organizacija</th>
                <th class="px-4 py-2">Kontrolni pregledi</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="pregled in pregledi" :key="pregled.id">
                <tr class="border-b hover:bg-gray-50">
                  <td class="px-4 py-2 align-top">{{ formatDatum(pregled.datum_pregleda) }}</td>
                  <td class="px-4 py-2 align-top">{{ pregled.type }}</td>
                  <td class="px-4 py-2 align-top">{{ pregled.komentar }}</td>
                  <td class="px-4 py-2 align-top">{{ pregled.organizacija }}</td>
                  <td class="px-4 py-2">
                    <div class="flex flex-col space-y-1 items-start">
                      <button type="button"
                        @click.stop="toggleKontrolniPregledi(pregled.id)"
                        class="text-blue-500 underline cursor-pointer focus:outline-none w-full text-left">
                        <span v-if="selectedPregledId !== pregled.id">Prikaži</span>
                        <span v-else>Sakrij</span>
                      </button>
                      <button @click="azurirajPregled(pregled)" class="text-yellow-600 hover:underline w-full text-left">Izmijeni</button>
                      <button @click="potvrdiBrisanjePregleda(pregled.id)" class="text-red-600 hover:underline w-full text-left">Obriši</button>
                    </div>
      <!-- Modal za potvrdu brisanja pregleda -->
      <div v-if="showPotvrdaBrisanja" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md relative">
          <h3 class="text-lg font-semibold mb-4">Potvrda brisanja</h3>
          <p>Da li ste sigurni da želite obrisati ovaj pregled?</p>
          <div class="flex justify-end space-x-2 mt-6">
            <button @click="showPotvrdaBrisanja = false; pregledZaBrisanje = null" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Otkaži</button>
            <button @click="obrisiPregled()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">Obriši</button>
          </div>
        </div>
      </div>
                  </td>
                </tr>
                <tr v-if="selectedPregledId === pregled.id">
                  <td colspan="5" class="bg-gray-50 px-4 py-2">
                    <div v-if="kontrolniPregledi.filter(kp => Number(kp.pregledi_id) === Number(pregled.id)).length">
                      <table class="table-auto w-full border">
                        <thead>
                          <tr class="bg-gray-50">
                            <th class="px-2 py-1">Datum</th>
                            <th class="px-2 py-1">Komentar</th>
                            <th class="px-2 py-1">Status</th>
                            <th class="px-2 py-1">Akcije</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="kp in kontrolniPregledi.filter(kp => Number(kp.pregledi_id) === Number(pregled.id))" :key="kp.id">
                            <td class="px-2 py-1">{{ formatDatum(kp.datum_kontrolnog_pregleda) }}</td>
                            <td class="px-2 py-1">{{ kp.kontrolni_komentar }}</td>
                            <td class="px-2 py-1">{{ kp.status == 1 || kp.status === true ? 'Završen' : 'Aktivan' }}</td>
                            <td class="px-2 py-1">
                              <button class="bg-yellow-400 hover:bg-yellow-500 text-white px-3 py-1 rounded" @click="izmeniKontrolniPregled(kp)">
                                Izmijeni
                              </button>
                            </td>
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
      <!-- Edit modal for kontrolni pregled -->
      <div v-if="showEditModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md relative">
          <button @click="closeEditModal" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">&times;</button>
          <h3 class="text-lg font-semibold mb-4">Izmjena kontrolnog pregleda</h3>
          <div v-if="editKontrolni">
            <div class="mb-3">
              <label class="block text-gray-700 text-sm font-bold mb-1">Datum</label>
              <input type="text" :value="formatDatum(editKontrolni.datum_kontrolnog_pregleda)" class="border rounded px-3 py-2 w-full" readonly />
            </div>
            <div class="mb-3">
              <label class="block text-gray-700 text-sm font-bold mb-1">Komentar</label>
              <input type="text" v-model="editKontrolni.kontrolni_komentar" class="border rounded px-3 py-2 w-full" />
            </div>
            <div class="mb-3">
              <label class="block text-gray-700 text-sm font-bold mb-1">Status</label>
              <select v-model="editKontrolni.status" class="border rounded px-3 py-2 w-full">
                <option :value="true">Završen</option>
                <option :value="false">Aktivan</option>
              </select>
            </div>
            <div class="flex justify-end space-x-2">
              <button @click="closeEditModal" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Zatvori</button>
              <button @click="sacuvajIzmjenuKontrolnog()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Sačuvaj</button>
            </div>
          </div>
        </div>
      </div>
      <!-- Modal za izmjenu pregleda -->
      <div v-if="showEditPregledModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md relative">
          <button @click="showEditPregledModal = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">&times;</button>
          <h3 class="text-lg font-semibold mb-4">Izmjena pregleda</h3>
          <div v-if="editPregled">
            <div class="mb-3">
              <label class="block text-gray-700 text-sm font-bold mb-1">Datum</label>
              <input type="date" v-model="editPregled.datum_pregleda" class="border rounded px-3 py-2 w-full" />
            </div>
            <div class="mb-3">
              <label class="block text-gray-700 text-sm font-bold mb-1">Tip</label>
              <select v-model="editPregled.type" class="border rounded px-3 py-2 w-full">
                <option value="Sposoban">Sposoban</option>
                <option value="Nesposoban">Nesposoban</option>
                <option value="Privremeno nesposoban">Privremeno nesposoban</option>
                <option value="Ograničen">Ograničen</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="block text-gray-700 text-sm font-bold mb-1">Komentar</label>
              <input type="text" v-model="editPregled.komentar" class="border rounded px-3 py-2 w-full" />
            </div>
            <div class="mb-3">
              <label class="block text-gray-700 text-sm font-bold mb-1">Organizacija</label>
              <select v-model="editPregled.organizacija" @change="onEditOrganizacijaChange" class="border rounded px-3 py-2 w-full" required>
                <option value="J.U. Dom zdravlja 'Dr.Isak Samokovlija' Goražde">J.U. Dom zdravlja "Dr.Isak Samokovlija" Goražde</option>
                <option value="PZU 'Eurofarm-Centar Poliklinika' PJ Goražde">PZU "Eurofarm-Centar Poliklinika" PJ Goražde</option>
                <option value="custom">Drugo (upišite ručno)</option>
              </select>
              <input v-if="showEditCustomOrganizacija" type="text" v-model="editPregled.organizacija" placeholder="Unesite naziv ustanove" class="border rounded px-3 py-2 w-full mt-2" required />
            </div>
            <div class="flex justify-end space-x-2">
              <button @click="showEditPregledModal = false" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Zatvori</button>
              <button @click="sacuvajIzmjenuPregleda()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Sačuvaj</button>
            </div>
          </div>
        </div>
      </div>
      <!-- Modal za izmjenu invalidnosti -->
      <div v-if="showEditInvalidnostModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md relative">
          <button @click="showEditInvalidnostModal = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700">&times;</button>
          <h3 class="text-lg font-semibold mb-4">Izmjena invalidnosti</h3>
          <div class="mb-3">
            <label class="block text-gray-700 text-sm font-bold mb-1">Nova invalidnost</label>
            <input type="text" v-model="novaInvalidnost" class="border rounded px-3 py-2 w-full" />
          </div>
          <div class="flex justify-end space-x-2">
            <button @click="showEditInvalidnostModal = false" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Otkaži</button>
            <button @click="sacuvajInvalidnost()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Sačuvaj</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
