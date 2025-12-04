<template>
    <ProductionAppLayout :title="formTitle">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ formTitle }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Unesite podatke o partneru, specifikaciji i proizvodima, pa nastavite.</p>
                </div>
            </div>
        </template>
        <div class="py-8">
            <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    <!-- Left: Main form -->
                    <div class="lg:col-span-3">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Osnovni podaci</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Partner, broj naloga i datum.</p>
                                </div>
                                <button @click="resetForm" type="button" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded-md transition-colors duration-200 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Resetuj formu
                                </button>
                            </div>
                            <div class="p-5 text-gray-900 dark:text-gray-100">
                                <!-- Partner dropdown -->
                                <div class="mb-4">
                                    <label for="partner_id" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Partner <span class="text-red-500">*</span></label>
                                    <select v-model="form.partner_id" id="partner_id"
                                            :class="['form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-200', form.partner_id === '' ? 'border-red-500' : '']"
                                            required>
                                        <option value="" disabled>Izaberi partnera</option>
                                        <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option>
                                    </select>
                                    <div class="mt-2 text-xs">
                                        <a href="/kupci/novi" class="text-blue-600 hover:underline" target="_blank">Dodaj novog kupca</a>
                                    </div>
                                    <p v-if="partnerError" class="text-red-600 text-xs mt-1">Morate izabrati partnera.</p>
                                </div>

                                <form @submit.prevent="submitForm" class="space-y-6">
                                    <input type="hidden" v-model="form.productListNew">
                                    <input type="hidden" v-model="form.user_id">

                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <label for="orderNumber" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Broj naloga</label>
                                            <input type="text" v-model="form.OrderNumber" id="orderNumber"
                                                   class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" disabled />
                                        </div>
                                        <div>
                                            <label for="vezaNaNalog" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Veza na nalog</label>
                                            <select v-model="form.VezaNaNalog" id="vezaNaNalog"
                                                    class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200">
                                                <option value="">(veza na nalog)</option>
                                                <option v-for="order in workingOrders" :key="order.id" :value="order.id">{{ order.OrderNumber }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="orderDate" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Datum naloga</label>
                                            <input type="date" v-model="form.OrderDate" id="orderDate"
                                                   class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                                        </div>
                                        <div>
                                            <label for="productInput" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Naziv</label>
                                            <input list="productSuggestions" v-model="form.Description" id="productInput"
                                                   class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" placeholder="Unesi naziv proizvoda..." />
                                            <datalist id="productSuggestions">
                                                <option v-for="product in productSuggestions" :key="product" :value="product" />
                                            </datalist>
                                        </div>
                                    </div>

                                    <div class="px-0 pt-0">
                                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Specifikacija</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <div>
                                                <label for="metraza" class="block text-xs font-medium text-gray-600 dark:text-gray-300">Metraža</label>
                                                      <input type="text" v-model="metrazaInput" id="metraza" inputmode="decimal" pattern="[0-9]+(,[0-9]+)?" @input="onMetrazaInput"
                                                       class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 disabled:opacity-60"
                                                       :disabled="(isBK6Selected || isBK8Selected || (isBihnelSelected && !isBihnelMSSelected && !isBihnelLPSelected && !isBihnelSLSelected)) && !isPSEDCUSelected && !isPSEDALSelected && !isMSEDSelected" required />
                                            </div>

                                            <div>
                                    <label for="VrstaProvodnika"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Vrsta
                                        provodnika</label>
                                    <select v-model="form.VrstaProvodnika" id="vrstaProvodnika"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 disabled:opacity-60"
                                        :disabled="isBK6Selected || isBK8Selected || (isBihnelSelected && !isMSEDSelected)"
                                        required>
                                            <option value="-">-</option>
                                        <option value="Al">Al</option>
                                        <option value="Cu">Cu</option>
                                        <option value="Fe">Fe</option>
                                        <option value="V">V</option>
                                        <option value="Zn">Zn</option>
                                    </select>
                                            </div>
                                            <div>
                                    <label for="Tip"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Tip</label>
                                    <select v-model="form.Tip" id="tip"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 disabled:opacity-60"
                                        :disabled="isBK6Selected || isBK8Selected || (isBihnelSelected && !isMSEDSelected)"
                                        required>
                                            <option value="-">-</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                    </select>
                                            </div>
                                            <div>
                                    <label for="BojaDuzinaProvodnika"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Boja/Dužina
                                        Provodnika</label>
                                    <input type="text" v-model="form.BojaDuzinaProvodnika" id="bojaDuzinaProvodnika"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 disabled:opacity-60"
                                        :disabled="isBK6Selected || isBK8Selected || isBihnelSelected"
                                        required />
                                            </div>
                                            <div class="md:col-span-2">
                                    <label for="Pakovanje"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Pakovanje</label>
                                    <textarea v-model="form.Pakovanje" id="pakovanje"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required></textarea>
                                            </div>
                                            <div>
                                    <label for="AtestPaketa"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Atest
                                        Paketa</label>
                                    <input type="text" v-model="form.AtestPaketa" id="atestPaketa"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                            </div>
                                            <div>
                                    <label for="CeOznaka"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">CE
                                        Oznaka</label>
                                    <input type="text" v-model="form.CeOznaka" id="ceOznaka"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                            </div>
                                            <div>
                                    <label for="KlasaOpasnosti"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Klasa
                                        Opasnosti</label>
                                    <input type="text" v-model="form.KlasaOpasnosti" id="klasaOpasnosti"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                            </div>
                                            <div>
                                    <label for="UNBroj"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">UN
                                        Broj</label>
                                    <input type="text" v-model="form.UNBroj" id="unBroj"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                            </div>
                                            <div>
                                    <label for="DatumPredaje"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Datum
                                        Predaje</label>
                                    <input type="date" v-model="form.DatumPredaje" id="datumPredaje" @input="datumPredajeManuallyEdited = true"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                            </div>
                                            <div>
                                    <label for="RokIsporuke"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Rok
                                        Isporuke</label>
                                    <input type="text" v-model="form.RokIsporuke" id="rokIsporuke"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                            </div>
                                            <div class="md:col-span-2">
                                    <label for="Dodatno"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Dodatno</label>
                                    <textarea v-model="form.dodatno" id="dodatno" @input="dodatnoManuallyEdited = true"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        rows="3"
                                        maxlength="250"
                                        placeholder="Unesite dodatne informacije..."
                                    ></textarea>
                                            </div>
                                            <div class="md:col-span-2">
                                    <label for="Napomena"
                                        class="block text-xs font-medium text-gray-600 dark:text-gray-300">Napomena</label>
                                    <textarea v-model="form.Napomena" id="napomena"
                                        class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required></textarea>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-end mt-1">
                                            <button id="pregledBtn" type="submit"
                                                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-blue-300">
                                                Kreiraj nalog
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- Right: Products panel -->
                    <div class="lg:col-span-2">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm sticky top-20">
                            <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Numere proizvoda</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Označite i unesite količine.</p>
                                <div v-if="productListNew.length > 0 && productListNew[0].Naziv" class="mt-2 text-xs text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 p-2 rounded">
                                    <strong>Puni naziv:</strong> {{ productListNew[0].Naziv }}
                                </div>
                            </div>
                            <div class="p-4">
                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table id="productListNew" class="min-w-full text-sm">
                                        <thead class="sticky top-0 z-10">
                                            <tr class="bg-gray-50 dark:bg-gray-700/70 text-gray-700 dark:text-gray-200">
                                                <th class="p-2 text-center">Odaberi</th>
                                                <th class="p-2 text-left">Naziv</th>
                                                <th class="p-2 text-center">Metraža</th>
                                                <th class="p-2 text-center">Količina</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                            <tr v-for="(p, idx) in productListNew" :key="`${p.id ?? ''}-${p.NumeraProizvoda ?? idx}`" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                                <td class="p-2 text-center">
                                                    <input type="checkbox" :id="'product-checkbox-' + (p.id ?? p.NumeraProizvoda ?? idx)" v-model="p.selected" />
                                                </td>
                                                <td class="p-2">
                                                    <label :for="'product-checkbox-' + (p.id || p.NumeraProizvoda || idx)" class="cursor-pointer">
                                                        <div class="font-medium">
                                                          {{ p.SkraceniNaziv || p.Naziv || p.name || (p.product_name) || (p.id ? `Proizvod #${p.id}` : `Stavka #${idx+1}`) }}
                                                        </div>
                                                        <div v-if="p.NumeraProizvoda" class="text-xs text-gray-500 dark:text-gray-400">Numera: {{ p.NumeraProizvoda }}</div>
                                                    </label>
                                                </td>
                                                <td class="p-2 text-center">
                                                    <span v-if="p.UoM_meter != null">{{ p.UoM_meter }}</span>
                                                </td>
                                                <td class="p-2 text-center">
                                                    <input type="number" min="0" class="w-24 px-2 py-1 border rounded dark:bg-gray-700 dark:text-gray-100" v-model="p.kolicina" placeholder="Količina" />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
                                    <span>Odabrano: {{ productListNew.filter(p=>p.selected).length }}</span>
                                    <span class="opacity-75" v-cloak><!-- {{ debugText }} --></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ProductionAppLayout>
</template>


<script setup>
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    workingOrders: { type: Array, default: () => [] },
    partners: { type: Array, default: () => [] },
});

const form = ref({
    productListNew: [],
    user_id: '',
    partner_id: '',
    OrderNumber: '',
    VezaNaNalog: '',
    OrderDate: '',
    Description: '',
    Metraza: '',
    // Status is set server-side; hidden from form
    VrstaProvodnika: '',
    Tip: '',
    BojaDuzinaProvodnika: '',
    Pakovanje: '',
    AtestPaketa: 'DA   4G/Y21/S/08',
    CeOznaka: 'DA',
    KlasaOpasnosti: '',
    UNBroj: '',
    RokIsporuke: '-',
    DatumPredaje: '',
    // DatumPrijema is intentionally not part of the creation payload; it will be set upon final approval by Šef Operative
    dodatno: '',
    Napomena: '-',
});

// UI model for Metraza that accepts comma or dot; normalized into form.Metraza (number)
const metrazaInput = ref('');
const internalMetrazaSync = ref(false);

const workingOrders = ref(props.workingOrders || []);
const partners = ref(props.partners || []);
const dodatnoManuallyEdited = ref(false);
const productSuggestions = ref([]);
const productListNew = ref([]);
const isHydrating = ref(false);
const isEditMode = ref(false);
// In edit mode, preserve details-based product list until user changes inputs
const preferDetailsList = ref(false);
const editId = ref(null);

// Debounce timer za pretragu proizvoda
let descriptionDebounceTimer = null;

const formTitle = computed(() => isEditMode.value ? 'Uredi nalog' : 'Kreiraj nalog');
const submitLabel = computed(() => isEditMode.value ? 'Sačuvaj izmjene' : 'Pregled');

// Da li je izabran BIHNEL (koristi se za disable polja Metraza/VrstaProvodnika/Tip)
const isBihnelSelected = computed(() => /BIHNEL/i.test(form.value.Description || ''));
// Da li je izabran BIHNEL MS specifično (Metraža ostaje aktivna)
const isBihnelMSSelected = computed(() => /BIHNEL\s+MS/i.test(form.value.Description || ''));
// Da li je izabran BIHNEL LP specifično (Metraža ostaje aktivna)
const isBihnelLPSelected = computed(() => /BIHNEL\s+LP/i.test(form.value.Description || ''));
// Da li je izabran BIHNEL SL specifično (Metraža ostaje aktivna)
const isBihnelSLSelected = computed(() => /BIHNEL\s+SL/i.test(form.value.Description || ''));
// Da li je izabran PSED-CU (Metraža ostaje aktivna)
const isPSEDCUSelected = computed(() => /PSED[-\s]?CU/i.test(form.value.Description || ''));
// Da li je izabran PSED-AL (Metraža ostaje aktivna)
const isPSEDALSelected = computed(() => /PSED[-\s]?AL/i.test(form.value.Description || ''));
// Da li je izabran MSED (Metraža, Vrsta provodnika i Tip ostaju aktivni)
const isMSEDSelected = computed(() => /MSED/i.test(form.value.Description || ''));
// Da li je izabran BK-6 (disable: Metraza, Status, Vrsta provodnika, Tip, Boja)
const isBK6Selected = computed(() => /(^|\b)BK[-\s]?6(\b|$)/i.test(form.value.Description || ''));
// Da li je izabran BK-8 (disable polja kao BK-6)
// Treat BK-8 and DK-8 families the same for UI rules
const isBK8Selected = computed(() => /(^|\b)[BD]K[-\s]?8(\s+(LP|MS))?(\b|$)/i.test(form.value.Description || ''));

const debugText = computed(() =>
    `Debug: desc=${form.value.Description} | metraza=${form.value.Metraza} | provodnik=${form.value.VrstaProvodnika} | tip=${form.value.Tip} | listLen=${productListNew.value.length}`
);

// Sanitize Metraza input to allow only digits and a single comma
function onMetrazaInput(e) {
    if (!e || !e.target) return;
    let v = String(e.target.value || '');
    // Convert dots to commas for UX, then strip invalid characters
    v = v.replace(/\./g, ',');
    v = v.replace(/[^0-9,]/g, '');
    // Ensure only one comma: keep first, remove the rest
    const firstComma = v.indexOf(',');
    if (firstComma !== -1) {
        const head = v.slice(0, firstComma + 1);
        const tail = v.slice(firstComma + 1).replace(/,/g, '');
        v = head + tail;
    }
    // Reflect sanitized value
    if (e.target.value !== v) e.target.value = v;
    metrazaInput.value = v;
}

// Prikaz proizvoda za BIHNEL proizvode
async function setupProductListBihnel(metraza = null) {
    console.log('Setting up productlist Bihnel', { metraza });
    const input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            let url = `/productslistBihnel?query=${encodeURIComponent(input)}`;
            // Konvertuj metrazu u broj ako je potrebno, a zatim u string za API
            if (metraza !== null && metraza !== '') {
                const metrazaNum = typeof metraza === 'string' ? Number(metraza.replace(',', '.')) : Number(metraza);
                if (!isNaN(metrazaNum)) {
                    url += `&metraza=${metrazaNum}`;
                }
            }
            const response = await axios.get(url);
            const data = response.data;
            console.log('Received /productslistBihnel items:', data);
            // Sortiraj po UoM_meter asc
            data.sort((a, b) => {
            const am = Number(a.UoM_meter);
            const bm = Number(b.UoM_meter);
            if (Number.isNaN(am) || Number.isNaN(bm)) return 0;
            return am - bm;
            });
            // Pripremi listu za prikaz
            productListNew.value = data.map(product => ({
            ...product,
            selected: false,
            kolicina: ''
            }));
            // Ako smo dobili BIHNEL proizvode, popuni CE polja iz prvog zapisa
            if (data.length > 0) {
                const first = data[0];
                form.value.CeOznaka = first.CEMarkNumber || '';
                form.value.KlasaOpasnosti = first.HazardClass || '';
                form.value.UNBroj = first.UNNumber != null ? String(first.UNNumber) : '';
            }
        } catch (error) {
            console.error("Error fetching data:c", error);
        }
    } else {
        console.log("Nema product inputa ili je prazan");
    }
}

// Prikaz proizvoda za PSED proizvode
async function setupProductListPSED(metraza = null) {
    const input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            let url = `/productslistPSED?query=${encodeURIComponent(input)}`;
            // Konvertuj metrazu u broj ako je potrebno, a zatim u string za API
            if (metraza !== null && metraza !== '') {
                const metrazaNum = typeof metraza === 'string' ? Number(metraza.replace(',', '.')) : Number(metraza);
                console.log('PSED setupProductListPSED - metraza:', metraza, 'converted:', metrazaNum, 'isNaN:', isNaN(metrazaNum));
                if (!isNaN(metrazaNum)) {
                    url += `&metraza=${metrazaNum}`;
                }
            }
            // Dodaj Tip filter ako je izabran A ili B
            if (form.value.Tip && form.value.Tip !== '-') {
                url += `&tip=${encodeURIComponent(form.value.Tip)}`;
            }
            console.log('PSED API URL:', url);
            const response = await axios.get(url);
            const data = response.data;
            console.log('PSED API response:', data.length, 'items');
            console.log('PSED API response data:', data);
            // Sortiraj po UoM_meter asc
            data.sort((a, b) => {
                const am = Number(a.UoM_meter);
                const bm = Number(b.UoM_meter);
                if (Number.isNaN(am) || Number.isNaN(bm)) return 0;
                return am - bm;
            });
            // Pripremi listu za prikaz
            productListNew.value = data.map(product => ({
                ...product,
                selected: false,
                kolicina: ''
            }));
            console.log('PSED productListNew.value after mapping:', productListNew.value.length, 'items');
        } catch (error) {
            console.error("Error fetching PSED data:", error);
        }
    } else {
        console.log("Nema product inputa ili je prazan");
    }
}

// Prikaz proizvoda za MSED proizvode
async function setupProductListMSED() {
    console.log('Setting up productlist MSED');
    const input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            let url = `/productslistMSED?query=${encodeURIComponent(input)}`;

            // Dodaj Metraza filter ako je unesena
            if (form.value.Metraza !== null && form.value.Metraza !== '') {
                const metrazaNum = typeof form.value.Metraza === 'string' ? Number(form.value.Metraza.replace(',', '.')) : Number(form.value.Metraza);
                if (!isNaN(metrazaNum)) {
                    url += `&metraza=${metrazaNum}`;
                }
            }

            // Dodaj Vrsta Provodnika filter ako je izabrana
            if (form.value.VrstaProvodnika && form.value.VrstaProvodnika !== '-') {
                url += `&vrstaProvodnika=${encodeURIComponent(form.value.VrstaProvodnika)}`;
            }

            // Dodaj Tip filter ako je izabran A ili B
            if (form.value.Tip && form.value.Tip !== '-') {
                url += `&tip=${encodeURIComponent(form.value.Tip)}`;
            }

            const response = await axios.get(url);
            const data = response.data;
            console.log('Received /productslistMSED items:', data);

            // Sortiraj po UoM_meter asc
            data.sort((a, b) => {
                const am = Number(a.UoM_meter);
                const bm = Number(b.UoM_meter);
                if (Number.isNaN(am) || Number.isNaN(bm)) return 0;
                return am - bm;
            });

            // Pripremi listu za prikaz
            productListNew.value = data.map(product => ({
                ...product,
                selected: false,
                kolicina: ''
            }));
        } catch (error) {
            console.error("Error fetching MSED data:", error);
        }
    } else {
        console.log("Nema product inputa ili je prazan");
    }
}

// Prikaz proizvoda za TED-CU / TED-AL proizvode (fleksibilno: ne zahtijeva sve filtere)
async function setupProductListTED(metraza = null) {
    console.log('Setting up productlist TED', { metraza });
    const input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            let url = `/productslist?query=${encodeURIComponent(input)}`;
            // Metraza (ako postoji) – koristi uom_meter parametar kao kod generičkog upita
            if (metraza !== null && metraza !== '') {
                const metrazaNum = typeof metraza === 'string' ? Number(metraza.replace(',', '.')) : Number(metraza);
                if (!isNaN(metrazaNum)) {
                    url += `&uom_meter=${metrazaNum}`;
                }
            }
            // Vrsta provodnika ako je definisana (i nije '-')
            if (form.value.VrstaProvodnika && form.value.VrstaProvodnika !== '-') {
                url += `&provodnik=${encodeURIComponent(form.value.VrstaProvodnika)}`;
            }
            // Tip ako je definisan (i nije '-')
            if (form.value.Tip && form.value.Tip !== '-') {
                url += `&tip=${encodeURIComponent(form.value.Tip)}`;
            }
            const response = await axios.get(url);
            const data = response.data;
            console.log('Received /productslist (TED) items:', data);
            if (Array.isArray(data)) {
                // Sortiraj po NumeraProizvoda ako postoji, fallback bez sortiranja
                data.sort((a, b) => {
                    const an = Number(a?.NumeraProizvoda);
                    const bn = Number(b?.NumeraProizvoda);
                    if (Number.isNaN(an) || Number.isNaN(bn)) return 0;
                    return an - bn;
                });
                productListNew.value = data.map(product => ({
                    ...product,
                    selected: false,
                    kolicina: ''
                }));
            } else {
                productListNew.value = [];
            }
        } catch (error) {
            console.error('Error fetching TED product list:', error);
        }
    } else {
        console.log('Nema product inputa ili je prazan (TED)');
    }
}

// Prikaz proizvoda za BK-6 proizvode
async function setupProductListBK6() {
    console.log('Setting up productlist BK-6');
    const input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            const response = await axios.get(`/productslistBK6?query=${input}`);
            const data = response.data;
            console.log('Received /productslistBK6 items:', data);
            // Sortiraj po 'UsporenjeMs' asc; fallback na moguće stare ključeve ('Usporenje'/'usporenje'), pa na UoM_meter
            data.sort((a, b) => {
                const au = Number(a.UsporenjeMs ?? a.Usporenje ?? a.usporenje);
                const bu = Number(b.UsporenjeMs ?? b.Usporenje ?? b.usporenje);
                if (!Number.isNaN(au) && !Number.isNaN(bu)) return au - bu;
                const am = Number(a.UoM_meter);
                const bm = Number(b.UoM_meter);
                if (!Number.isNaN(am) && !Number.isNaN(bm)) return am - bm;
                return 0;
            });
            // Pripremi listu za prikaz
            productListNew.value = data.map(product => ({
                ...product,
                selected: false,
                kolicina: ''
            }));
            // Popuni CE polja iz prvog zapisa (BK-6)
            if (data.length > 0) {
                const first = data[0];
                form.value.CeOznaka = first.CEMarkNumber ?? first.ce_oznaka ?? '';
                form.value.KlasaOpasnosti = first.HazardClass ?? first.klasa_opasnosti ?? '';
                {
                    const un = first.UNNumber ?? first.un_broj;
                    form.value.UNBroj = un != null ? String(un) : '';
                }
            }
        } catch (error) {
            console.error("Error fetching data (BK-6):", error);
        }
    } else {
        console.log("Nema product inputa ili je prazan (BK-6)");
    }
}

// Prikaz proizvoda za DK-6 proizvode (slično BK-6, ali bez posebnih LP/MS varijanti)
async function setupProductListDK6() {
    console.log('Setting up productlist DK-6');
    const input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            const response = await axios.get(`/productslistBK6?query=${encodeURIComponent(input)}`);
            const data = response.data;
            console.log('Received /productslistBK6 (DK-6) items:', data);
            // Sortiraj po UoM_meter asc (bez oslanjanja na usporenje)
            data.sort((a, b) => {
                const am = Number(a.UoM_meter);
                const bm = Number(b.UoM_meter);
                if (Number.isNaN(am) || Number.isNaN(bm)) return 0;
                return am - bm;
            });
            // Pripremi listu za prikaz
            productListNew.value = data.map(product => ({
                ...product,
                selected: false,
                kolicina: ''
            }));
            // Ako postoje CE podaci, pokušaj populirati iz prvog zapisa
            if (data.length > 0) {
                const first = data[0];
                form.value.CeOznaka = first.CEMarkNumber ?? first.ce_oznaka ?? form.value.CeOznaka;
                form.value.KlasaOpasnosti = first.HazardClass ?? first.klasa_opasnosti ?? form.value.KlasaOpasnosti;
                const un = first.UNNumber ?? first.un_broj;
                if (un != null) form.value.UNBroj = String(un);
            }
        } catch (error) {
            console.error("Error fetching data (DK-6):", error);
        }
    } else {
        console.log("Nema product inputa ili je prazan (DK-6)");
    }
}

// Prikaz proizvoda za BK-8 proizvode
async function setupProductListBK8(variant) {
    console.log('Setting up productlist BK-8');
    let input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            const url = variant
                ? `/productslistBK8?query=${encodeURIComponent(input)}&variant=${encodeURIComponent(variant)}`
                : `/productslistBK8?query=${encodeURIComponent(input)}`;
            const response = await axios.get(url);
            let data = response.data || [];
            console.log('Received /productslistBK8 items:', data);
            // Sortiraj kao za BK-6: po 'UsporenjeMs' asc; fallback na moguće stare ključeve ('Usporenje'/'usporenje'), pa na UoM_meter
            data.sort((a, b) => {
                const au = Number(a.UsporenjeMs ?? a.Usporenje ?? a.usporenje);
                const bu = Number(b.UsporenjeMs ?? b.Usporenje ?? b.usporenje);
                if (!Number.isNaN(au) && !Number.isNaN(bu)) return au - bu;
                const am = Number(a.UoM_meter);
                const bm = Number(b.UoM_meter);
                if (!Number.isNaN(am) && !Number.isNaN(bm)) return am - bm;
                return 0;
            });
            // Pripremi listu za prikaz
            productListNew.value = data.map(product => ({
                ...product,
                selected: false,
                kolicina: ''
            }));
            // Popuni CE polja iz prvog zapisa (BK-8)
            if (data.length > 0) {
                const first = data[0];
                form.value.CeOznaka = first.CEMarkNumber ?? first.ce_oznaka ?? '';
                form.value.KlasaOpasnosti = first.HazardClass ?? first.klasa_opasnosti ?? '';
                {
                    const un = first.UNNumber ?? first.un_broj;
                    form.value.UNBroj = un != null ? String(un) : '';
                }
            }
        } catch (error) {
            console.error("Error fetching data (BK-8):", error);
        }
    } else {
        console.log("Nema product inputa ili je prazan (BK-8)");
    }
}

async function getOrderNumber() {
    try {
        const { data } = await axios.get('/getOrderNumber');
        form.value.OrderNumber = data.orderNumber;
    } catch (error) {
        console.error("Error fetching order number:", error);
    }
}

watch(() => form.value.Description, async (newValue) => {
    // Cancel previous debounce timer
    if (descriptionDebounceTimer) {
        clearTimeout(descriptionDebounceTimer);
        descriptionDebounceTimer = null;
    }

    if (isHydrating.value) return;
    if (isEditMode.value && preferDetailsList.value) { preferDetailsList.value = false; return; }

    // Odmah resetuj listu proizvoda prije nego što se pokrene pretraga
    productListNew.value = [];

    if (!newValue || newValue.length === 0) {
        productSuggestions.value = [];
        return;
    }

    // Debounce: pričekaj da korisnik prestane kucati prije nego što učitaš listu
    descriptionDebounceTimer = setTimeout(async () => {
        try {
        // Auto-set VrstaProvodnika based on suffix in naziv (e.g., PSED-Cu -> Cu, PSED-Al -> Al)
        // Works for variants like MSED-Cu, MMSED-Cu, CSED-Cu, etc.
        const matchMetal = newValue.match(/-\s*(cu|al)\b/i);
        if (matchMetal) {
            const metal = matchMetal[1].toLowerCase();
            const normalized = metal === 'cu' ? 'Cu' : (metal === 'al' ? 'Al' : form.value.VrstaProvodnika);
            if (normalized && form.value.VrstaProvodnika !== normalized) {
                form.value.VrstaProvodnika = normalized;
            }
        }

        // 1) K-8 porodica: BK-8 ima LP/MS varijante, DK-8 bez varijanti (direktan prikaz liste)
    const k8Match = newValue.match(/(^|\b)(BK|DK)[-\s]?8(\b|$)/i);
    const isK8 = !!k8Match;
    const k8Family = k8Match ? k8Match[2].toUpperCase() : null;
        const hasLP = /\bLP\b/i.test(newValue);
        const hasMS = /\bMS\b/i.test(newValue);
        if (isK8) {
            if (k8Family === 'BK') {
                // Ako je samo BK-8 bez LP/MS, sada odmah učitaj kompletnu listu BK-8 proizvoda
                const chosen = hasLP ? 'LP' : (hasMS ? 'MS' : undefined);
                productSuggestions.value = [];
                // Postavi defaulte kao za BK-6
                form.value.Metraza = 0;
                form.value.VrstaProvodnika = '-';
                form.value.Tip = '-';
                form.value.BojaDuzinaProvodnika = '-';
                await setupProductListBK8(chosen);
                return;
            } else if (k8Family === 'DK') {
                // DK-8: nema LP/MS varijanti – direktno učitaj listu
                productSuggestions.value = [];
                await setupProductListBK8(undefined);
                return;
            }
        }

        // 2) BIHNEL: odmah učitaj
        if (/BIHNEL/i.test(newValue)) {
            productSuggestions.value = [];
            // Za BIHNEL MS, LP i SL ostavi Metraza omogućenu, ostalo zaključaj
            const isBihnelMS = /BIHNEL\s+MS/i.test(newValue);
            const isBihnelLP = /BIHNEL\s+LP/i.test(newValue);
            const isBihnelSL = /BIHNEL\s+SL/i.test(newValue);
            const hasMetraza = isBihnelMS || isBihnelLP || isBihnelSL;

            if (!hasMetraza) {
                form.value.Metraza = '';
            }
            form.value.VrstaProvodnika = '';
            form.value.Tip = '';
            await setupProductListBihnel(hasMetraza ? form.value.Metraza : null);
            return;
        }

        // 2b) PSED-CU ili PSED-AL: odmah učitaj sa metražom
        if (/PSED[-\s]?CU/i.test(newValue) || /PSED[-\s]?AL/i.test(newValue)) {
            productSuggestions.value = [];
            // Za PSED-CU i PSED-AL Metraža ostaje omogućena
            await setupProductListPSED(form.value.Metraza || null);
            return;
        }

        // 2c) MSED: odmah učitaj sa metražom, vrsta provodnika i tip
        if (/MSED/i.test(newValue)) {
            productSuggestions.value = [];
            // Za MSED Metraža, Vrsta provodnika i Tip ostaju omogućeni
            await setupProductListMSED();
            return;
        }

        // 2d) TED-CU ili TED-AL: odmah učitaj fleksibilnu listu
        if (/TED[-\s]?(CU|AL)/i.test(newValue)) {
            productSuggestions.value = [];
            await setupProductListTED(form.value.Metraza || null);
            return;
        }

        // 3) BK-6: odmah učitaj + postavi default vrijednosti za zaključana polja
        if (/(^|\b)BK[-\s]?6(\b|$)/i.test(newValue)) {
            productSuggestions.value = [];
            form.value.Metraza = 0;
            form.value.VrstaProvodnika = '-';
            form.value.Tip = '-';
            form.value.BojaDuzinaProvodnika = '-';
            await setupProductListBK6();
            return;
        }

        // 3b) DK-6: odmah učitaj listu (bez zaključavanja polja)
        if (/(^|\b)DK[-\s]?6(\b|$)/i.test(newValue)) {
            productSuggestions.value = [];
            await setupProductListDK6();
            return;
        }

        // 4) Ostalo: dohvati generalne sugestije
        const { data } = await axios.get(`/products?query=${encodeURIComponent(newValue)}`);
        productSuggestions.value = [...new Set(data.map(p => p.SkraceniNaziv))];

        // 5) Ako korisnik unese puni naziv (npr. "INICIRAJUĆA CJEVČICA" ili "ŽICA") koji se ne poklapa
        //    sa skraćenim nazivima, pozovi generički endpoint za prikaz liste proizvoda
        const matchesShortName = data.some(p =>
            p.SkraceniNaziv && p.SkraceniNaziv.toLowerCase() === newValue.toLowerCase()
        );

        // Ako nije pronađen tačan match sa skraćenim nazivom, traži po punom nazivu
        if (!matchesShortName) {
            try {
                const { data: productList } = await axios.get(`/productslist?query=${encodeURIComponent(newValue)}`);
                if (Array.isArray(productList) && productList.length > 0) {
                    // Sortiraj po NumeraProizvoda
                    productList.sort((a, b) => {
                        const an = Number(a?.NumeraProizvoda);
                        const bn = Number(b?.NumeraProizvoda);
                        if (Number.isNaN(an) || Number.isNaN(bn)) return 0;
                        return an - bn;
                    });
                    // Pripremi listu za prikaz
                    productListNew.value = productList.map(product => ({
                        ...product,
                        selected: false,
                        kolicina: ''
                    }));
                    console.log(`Loaded ${productList.length} products for generic name: ${newValue}`);
                } else {
                    // Ako nema rezultata, ostavi listu praznu
                    productListNew.value = [];
                }
            } catch (error) {
                console.error("Error fetching generic product list:", error);
                productListNew.value = [];
            }
        } else {
            // Ako postoji match sa skraćenim nazivom, ne prikazuj generičku listu
            // Lista će biti popunjena kroz specifične setup funkcije ili ostati prazna
        }
        } catch (error) {
            console.error("Error fetching products:", error);
        }
    }, 400); // Pričekaj 400ms nakon što korisnik prestane kucati
});

watch([() => form.value.Description, () => form.value.VrstaProvodnika], async ([desc, provodnik]) => {
    if (isHydrating.value) return;
    if (isEditMode.value && preferDetailsList.value) { preferDetailsList.value = false; return; }
    if (desc && provodnik) {
        // Za BIHNEL već smo popunili vrijednosti direktno iz liste – preskačemo dummy endpoint
        if (/BIHNEL/i.test(desc)) return;
        // Za BK-6 već popunjavamo CE podatke kroz listu BK-6 – preskoči poziv
        if (/(^|\b)BK[-\s]?6(\b|$)/i.test(desc)) return;
        // Za DK-6 isto – preskoči poziv
        if (/(^|\b)DK[-\s]?6(\b|$)/i.test(desc)) return;
        // Za BK-8 takođe CE podatke popunjavamo iz liste – preskoči poziv
        if (/(^|\b)[BD]K[-\s]?8(\s+(LP|MS))?(\b|$)/i.test(desc)) return;
        try {
            const { data } = await axios.get(`/getCeOznaka?naziv=${desc}&vrstaProvodnika=${provodnik}`);
            form.value.CeOznaka = data.CEMarkNumber;
            form.value.KlasaOpasnosti = data.HazardClass;
            form.value.UNBroj = data.UNNumber != null ? String(data.UNNumber) : '';
        } catch (error) {
            console.error("Error fetching CE oznaka:", error);
        }
    }
});

watch([() => form.value.Description, () => form.value.Metraza, () => form.value.VrstaProvodnika, () => form.value.Tip], async ([desc, metraza, provodnik, tip]) => {
    if (isHydrating.value) return;
    if (isEditMode.value && preferDetailsList.value) { preferDetailsList.value = false; return; }

    // Specijalan slučaj: BIHNEL MS, LP ili SL sa metražom
    if ((/BIHNEL\s+MS/i.test(desc) || /BIHNEL\s+LP/i.test(desc) || /BIHNEL\s+SL/i.test(desc)) && metraza) {
        try {
            console.log('Fetching BIHNEL (MS/LP/SL) with metraza:', metraza);
            await setupProductListBihnel(metraza);
            return;
        } catch (error) {
            console.error("Error fetching BIHNEL with metraza:", error);
        }
    }

    // Specijalan slučaj: PSED-CU ili PSED-AL sa metražom i/ili tipom
    if (/PSED[-\s]?CU/i.test(desc) || /PSED[-\s]?AL/i.test(desc)) {
        try {
            await setupProductListPSED(metraza);
            return;
        } catch (error) {
            console.error("Error fetching PSED with metraza:", error);
        }
    }

    // Specijalan slučaj: MSED sa metražom, vrsta provodnika i/ili tipom
    if (/MSED/i.test(desc)) {
        try {
            console.log('Fetching MSED with metraza, provodnik and tip:', { metraza, provodnik, tip });
            await setupProductListMSED();
            return;
        } catch (error) {
            console.error("Error fetching MSED:", error);
        }
    }

    // Specijalan slučaj: TED-CU ili TED-AL – osvježi listu bez stroge potrebe za svim filterima
    if (/TED[-\s]?(CU|AL)/i.test(desc)) {
        try {
            console.log('Fetching TED with optional filters:', { metraza, provodnik, tip });
            await setupProductListTED(metraza);
            return;
        } catch (error) {
            console.error('Error fetching TED:', error);
        }
    }

    // Izbjegni generički poziv kada su specijalni proizvodi (BK-6/BK-8/BIHNEL/PSED/MSED) aktivni
    if (/(^|\b)BK[-\s]?6(\b|$)/i.test(desc)
        || /(^|\b)DK[-\s]?6(\b|$)/i.test(desc)
        || /(^|\b)[BD]K[-\s]?8(\s+(LP|MS))?(\b|$)/i.test(desc)
        || /BIHNEL/i.test(desc)
        || /PSED[-\s]?CU/i.test(desc)
        || /PSED[-\s]?AL/i.test(desc)
        || /MSED/i.test(desc)) {
        return;
    }
    // Izbjegni generički poziv i za TED
    if (/TED[-\s]?(CU|AL)/i.test(desc)) {
        return;
    }
    if (desc && metraza && provodnik && tip) {
        try {
            // Konvertuj metrazu u broj ako je potrebno
            const metrazaNum = typeof metraza === 'string' ? Number(metraza.replace(',', '.')) : Number(metraza);
            if (isNaN(metrazaNum)) {
                console.warn('Invalid metraza value:', metraza);
                return;
            }
            console.log('Fetching /productslist with', { desc, metraza: metrazaNum, provodnik, tip });
            const { data } = await axios.get(`/productslist?query=${encodeURIComponent(desc)}&uom_meter=${metrazaNum}&provodnik=${encodeURIComponent(provodnik)}&tip=${encodeURIComponent(tip)}`);
            if (!Array.isArray(data)) {
                console.warn('Unexpected /productslist response shape:', data);
                productListNew.value = [];
                return;
            }
            productListNew.value = data.sort((a, b) => {
                const an = Number(a?.NumeraProizvoda);
                const bn = Number(b?.NumeraProizvoda);
                if (Number.isNaN(an) || Number.isNaN(bn)) return 0;
                return an - bn;
            });
            console.log('Received /productslist items:', productListNew.value.length);
        } catch (error) {
            console.error("Error fetching product list:", error);
        }
    }
});

const partnerError = ref(false);
const datumPredajeManuallyEdited = ref(false);

// Funkcija za resetovanje forme
function resetForm() {
    if (confirm('Da li ste sigurni da želite da resetujete formu? Svi uneseni podaci će biti izgubljeni.')) {
        window.location.reload();
    }
}

// Auto-fill DatumPredaje with OrderDate unless user edits DatumPredaje manually
watch(() => form.value.OrderDate, (newVal) => {
    if (isHydrating.value) return;
    if (newVal && !datumPredajeManuallyEdited.value) {
        form.value.DatumPredaje = newVal;
    }
});

// Ako korisnik nije ručno mijenjao polje 'Dodatno' i nismo u edit modu,
// popuni ga po izboru partnera koristeći partners.oznaka
watch(() => form.value.partner_id, (newVal) => {
    if (isHydrating.value) return;
    if (isEditMode.value) return;
    if (!newVal) return;
    if (dodatnoManuallyEdited.value) return;
    const p = partners.value.find(pp => String(pp.id) === String(newVal));
    const oznaka = (p && p.oznaka) ? String(p.oznaka) : '';
    form.value.dodatno = `Etikete sa potrebnim informacijama : DA / ${oznaka}\nPalete omotati 7 puta streč folijom. \nPlan paletiranja: Naknadno.`;
});

async function submitForm() {
    partnerError.value = false;
    if (!form.value.partner_id) {
        partnerError.value = true;
        return;
    }
    try {
        // Pripremi proizvode tako da svaki ima quantity (backend očekuje ovo polje)
        const selectedProducts = productListNew.value
            .filter(p => p.selected)
            .map(p => ({
                id: p.id,
                quantity: p.kolicina !== undefined ? p.kolicina : p.quantity
            }));
        // Proveri da li je quantity validan broj
        for (const prod of selectedProducts) {
            if (prod.quantity === undefined || prod.quantity === null || prod.quantity === '' || isNaN(prod.quantity)) {
                alert('Svaki odabrani proizvod mora imati količinu!');
                return;
            }
        }
        const payload = { ...form.value, productListNew: selectedProducts };
        console.log('Submitted productListNew:', selectedProducts);
        console.log('Forma:', form.value);
        if (isEditMode.value && editId.value) {
            await axios.put(`/productionorders/${editId.value}`, payload);
            alert('Nalog je ažuriran.');
            window.location.href = '/nalozi/kreirani';
        } else {
            await axios.post('/productionorders', payload);
            alert('Podaci su poslani!');
            window.location.reload();
        }
    } catch (error) {
        alert('Greška pri slanju podataka!');
        console.error(error);
    }
}

async function loadForEdit(id) {
    try {
        isHydrating.value = true;
        const { data } = await axios.get(`/api/productionorders/${id}`);
        const ord = data?.order;
        if (!ord) return;
        editId.value = ord.id;
        form.value.partner_id = ord.partner?.id || '';
        form.value.OrderNumber = ord.OrderNumber || '';
        form.value.OrderDate = ord.OrderDate || '';
        form.value.Description = ord.Description || '';
        form.value.Metraza = ord.Metraza ?? '';
        form.value.VrstaProvodnika = ord.VrstaProvodnika ?? '';
        form.value.Tip = ord.Tip ?? '';
        form.value.BojaDuzinaProvodnika = ord.BojaDuzinaProvodnika ?? '';
        form.value.Pakovanje = ord.Pakovanje ?? '';
        form.value.AtestPaketa = ord.AtestPaketa ?? form.value.AtestPaketa;
        form.value.CeOznaka = ord.CeOznaka ?? '';
        form.value.KlasaOpasnosti = ord.KlasaOpasnosti ?? '';
        form.value.UNBroj = ord.UNBroj != null ? String(ord.UNBroj) : '';
        form.value.RokIsporuke = ord.RokIsporuke ?? '';
        form.value.DatumPredaje = ord.DatumPredaje || '';
        form.value.dodatno = ord.dodatno ?? '';
        form.value.Napomena = ord.Napomena ?? '';

        // Build selection map from existing details (id preferred, fallback by Numera+UoM)
        const selectionById = new Map();
        const selectionByKey = new Map();
        for (const d of (ord.details || [])) {
            const pid = d.product?.id ?? null;
            const qty = Number(d.quantity ?? 0);
            if (pid) selectionById.set(Number(pid), qty);
            const key = (d.product?.NumeraProizvoda ?? null) && (d.product?.UoM_meter ?? null)
                ? `${d.product.NumeraProizvoda}|${d.product.UoM_meter}`
                : null;
            if (key) selectionByKey.set(key, qty);
        }

        // Load full product list for current description and preselect existing choices
        await refreshProductListForEdit(selectionById, selectionByKey);
    } catch (e) {
        console.error('Greška pri učitavanju naloga za uređivanje', e);
        alert('Ne može se učitati nalog za uređivanje.');
    } finally {
        isHydrating.value = false;
    }
}

async function refreshProductListForEdit(selectionById, selectionByKey) {
    const desc = (form.value.Description || '').trim();
    if (!desc) { productListNew.value = []; return; }

    // Decide which list to load, mirroring watcher logic
    const isBK6 = /(^|\b)BK[-\s]?6(\b|$)/i.test(desc);
    const isDK6 = /(^|\b)DK[-\s]?6(\b|$)/i.test(desc);
    const isBK8 = /(^|\b)[BD]K[-\s]?8(\b|$)/i.test(desc);
    const hasLP = /\bLP\b/i.test(desc);
    const hasMS = /\bMS\b/i.test(desc);
    const isBihnel = /BIHNEL/i.test(desc);

    try {
        if (isBihnel) {
            await setupProductListBihnel();
        } else if (isBK6) {
            await setupProductListBK6();
        } else if (isDK6) {
            await setupProductListDK6();
        } else if (isBK8) {
            const variant = hasLP ? 'LP' : (hasMS ? 'MS' : undefined);
            await setupProductListBK8(variant);
        } else {
            // Generic list fetch (same as watcher)
            const metraza = form.value.Metraza;
            const provodnik = form.value.VrstaProvodnika;
            const tip = form.value.Tip;
            if (desc && metraza && provodnik && tip) {
                const { data } = await axios.get(`/productslist?query=${encodeURIComponent(desc)}&uom_meter=${metraza}&provodnik=${encodeURIComponent(provodnik)}&tip=${encodeURIComponent(tip)}`);
                if (Array.isArray(data)) {
                    productListNew.value = data.sort((a, b) => {
                        const an = Number(a?.NumeraProizvoda);
                        const bn = Number(b?.NumeraProizvoda);
                        if (Number.isNaN(an) || Number.isNaN(bn)) return 0;
                        return an - bn;
                    }).map(p => ({ ...p, selected: false, kolicina: '' }));
                } else {
                    productListNew.value = [];
                }
            } else {
                productListNew.value = [];
            }
        }

        // Apply selection from details onto the loaded list
        for (const p of productListNew.value) {
            const pid = Number(p.id ?? NaN);
            const key = (p.NumeraProizvoda != null && p.UoM_meter != null)
                ? `${p.NumeraProizvoda}|${p.UoM_meter}`
                : null;
            let qty = undefined;
            if (Number.isFinite(pid) && selectionById.has(pid)) {
                qty = selectionById.get(pid);
            } else if (key && selectionByKey.has(key)) {
                qty = selectionByKey.get(key);
            }
            if (qty !== undefined) {
                p.selected = true;
                p.kolicina = qty;
            } else {
                p.selected = false;
                if (p.kolicina == null) p.kolicina = '';
            }
        }
    } catch (err) {
        console.error('Greška pri učitavanju liste numera (edit)', err);
    }
}

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const edit = params.get('edit');
    if (edit) {
        isEditMode.value = true;
        preferDetailsList.value = true;
        await loadForEdit(edit);
    } else {
        await getOrderNumber();
    }
    // Initialize metraza UI string from current numeric value
    metrazaInput.value = (form.value.Metraza === '' || form.value.Metraza == null)
        ? ''
        : String(form.value.Metraza).replace('.', ',');
    console.log('OrderNumbers:', workingOrders.value.map(o => o.OrderNumber));
    // Dobavi workingOrders i ostale podatke po potrebi
});

// Normalize comma to dot and keep numeric in form.Metraza
watch(metrazaInput, (val) => {
    if (isHydrating.value) return;
    internalMetrazaSync.value = true;
    const raw = (val ?? '').toString();
    const normalized = raw.replace(',', '.');
    console.log('metrazaInput watch - raw:', raw, 'normalized:', normalized);
    if (normalized.trim() === '') {
        form.value.Metraza = '';
    } else {
        const num = Number(normalized);
        console.log('Converting to number:', num, 'isNaN:', Number.isNaN(num));
        form.value.Metraza = Number.isNaN(num) ? '' : num;
    }
    console.log('form.value.Metraza set to:', form.value.Metraza);
    internalMetrazaSync.value = false;

    // Odmah osvježi listu proizvoda ako je PSED/BIHNEL/MSED/TED aktivan
    const desc = form.value.Description || '';
    console.log('Checking for product type, desc:', desc, 'metraza:', form.value.Metraza);
    if (form.value.Metraza !== '' && form.value.Metraza !== null) {
        if (/PSED[-\s]?(CU|AL)/i.test(desc)) {
            console.log('PSED detected, calling setupProductListPSED with:', form.value.Metraza);
            setupProductListPSED(form.value.Metraza);
        } else if (/BIHNEL\s+(MS|LP|SL)/i.test(desc)) {
            setupProductListBihnel(form.value.Metraza);
        } else if (/MSED/i.test(desc)) {
            setupProductListMSED();
        } else if (/TED[-\s]?(CU|AL)/i.test(desc)) {
            setupProductListTED(form.value.Metraza);
        }
    }
});

// Reflect programmatic changes (e.g., setting 0 for BK-6) back into the UI string
watch(() => form.value.Metraza, (val) => {
    if (internalMetrazaSync.value) return;
    if (val === '' || val == null) {
        metrazaInput.value = '';
    } else {
        metrazaInput.value = String(val).replace('.', ',');
    }
});
</script>

<style>
[v-cloak] {
    display: none;
}
</style>
