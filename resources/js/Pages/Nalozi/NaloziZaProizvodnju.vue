<template>
    <ProductionAppLayout title="Nalozi za proizvodnju">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Nalozi
            </h2>
        </template>
        <div class="flex py-12">
            <div class="flex max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white grid grid-cols-3 gap-4 dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg w-full">
                    <div
                        class="col-span-2 p-6 text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-700">
                        <div class="text-center pb-5">
                            {{ formTitle }}
                        </div>
                        <!-- Partner dropdown -->
                        <div class="mb-4">
                            <label for="partner_id"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-200">Partner <span class="text-red-500">*</span></label>
                            <select v-model="form.partner_id" id="partner_id"
                                :class="['form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200', form.partner_id === '' ? 'border-red-500' : '']"
                                required>
                                <option value="" disabled>Izaberi partnera</option>
                                <option v-for="partner in partners" :key="partner.id" :value="partner.id">
                                    {{ partner.name }}
                                </option>
                            </select>
                            <span v-if="partnerError" class="text-red-500 text-xs">Morate izabrati partnera!</span>
                        </div>
                        <form @submit.prevent="submitForm">
                            <input type="hidden" v-model="form.productListNew">
                            <input type="hidden" v-model="form.user_id">
                            <div class="grid grid-cols-4 gap-4">
                                <div class="col-span-1">
                                    <label for="OrderNumber"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Broj
                                        naloga</label>
                                    <input type="text" v-model="form.OrderNumber" id="orderNumber"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        disabled />
                                </div>
                                <div class="col-span-1">
                                    <label for="VezaNaNalog"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Veza na
                                        nalog</label>
                                    <select v-model="form.VezaNaNalog" id="vezaNaNalog"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200">
                                        <option value="">(veza na nalog)</option>
                                        <option v-for="order in workingOrders" :key="order.id" :value="order.id">
                                            {{ order.OrderNumber }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label for="OrderDate"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Order
                                        Date</label>
                                    <input type="date" v-model="form.OrderDate" id="orderDate"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="Description"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Naziv</label>
                                    <input list="productSuggestions" v-model="form.Description" id="productInput"
                                        class="form-control rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        placeholder="Unesi naziv proizvoda..." />
                                    <datalist id="productSuggestions">
                                        <option v-for="product in productSuggestions" :key="product" :value="product" />
                                    </datalist>
                                </div>
                                <div class="col-span-1">
                                    <label for="metraza"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Metraža</label>
                                    <input type="number" v-model.number="form.Metraza" id="metraza"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 disabled:opacity-60"
                                        :disabled="isBK6Selected || isBK8Selected || isBihnelSelected"
                                        step="0.01" required />
                                </div>

                                <div class="col-span-1">
                                    <label for="VrstaProvodnika"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Vrsta
                                        provodnika</label>
                                    <select v-model="form.VrstaProvodnika" id="vrstaProvodnika"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 disabled:opacity-60"
                                        :disabled="isBK6Selected || isBK8Selected || isBihnelSelected"
                                        required>
                                            <option value="-">-</option>
                                        <option value="Al">Al</option>
                                        <option value="Cu">Cu</option>
                                        <option value="Fe">Fe</option>
                                        <option value="V">V</option>
                                        <option value="Zn">Zn</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label for="Tip"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Tip</label>
                                    <select v-model="form.Tip" id="tip"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 disabled:opacity-60"
                                        :disabled="isBK6Selected || isBK8Selected || isBihnelSelected"
                                        required>
                                            <option value="-">-</option>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label for="BojaDuzinaProvodnika"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Boja Duzina
                                        Provodnika</label>
                                    <input type="text" v-model="form.BojaDuzinaProvodnika" id="bojaDuzinaProvodnika"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200 disabled:opacity-60"
                                        :disabled="isBK6Selected || isBK8Selected || isBihnelSelected"
                                        required />
                                </div>
                                <div class="col-span-2">
                                    <label for="Pakovanje"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Pakovanje</label>
                                    <textarea v-model="form.Pakovanje" id="pakovanje"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required></textarea>
                                </div>
                                <div class="col-span-1">
                                    <label for="AtestPaketa"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Atest
                                        Paketa</label>
                                    <input type="text" v-model="form.AtestPaketa" id="atestPaketa"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="CeOznaka"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Ce
                                        Oznaka</label>
                                    <input type="text" v-model="form.CeOznaka" id="ceOznaka"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="KlasaOpasnosti"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Klasa
                                        Opasnosti</label>
                                    <input type="text" v-model="form.KlasaOpasnosti" id="klasaOpasnosti"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="UNBroj"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">UN
                                        Broj</label>
                                    <input type="text" v-model="form.UNBroj" id="unBroj"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="DatumPredaje"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Datum
                                        Predaje</label>
                                    <input type="date" v-model="form.DatumPredaje" id="datumPredaje" @input="datumPredajeManuallyEdited = true"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="RokIsporuke"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Rok
                                        Isporuke</label>
                                    <input type="text" v-model="form.RokIsporuke" id="rokIsporuke"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-4">
                                    <label for="Dodatno"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Dodatno</label>
                                    <textarea v-model="form.dodatno" id="dodatno" @input="dodatnoManuallyEdited = true"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        rows="3"
                                        maxlength="250"
                                        placeholder="Unesite dodatne informacije..."
                                    ></textarea>
                                </div>

                                <div class="col-span-4">
                                    <label for="Napomena"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Napomena</label>
                                    <textarea v-model="form.Napomena" id="napomena"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required></textarea>
                                </div>
                            </div>
                            <div class="flex items-center justify-end mt-4">
                                <button id="pregledBtn" type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 dark:focus:ring-gray-600 disabled:opacity-25 transition ease-in-out duration-150">{{ submitLabel }}</button>
                            </div>
                        </form>
                    </div>
                    <div class="flex col-span-1 p-1 grid grid-cols-1 bg-white dark:bg-gray-800">
                        <div class="mt-8">
                            <label for="productSelect"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-200">Numere
                                proizvoda</label>
                            <table id="productListNew"
                                class="mt-4 w-full text-gray-700 dark:text-gray-200 border-collapse">
                                <thead>
                                    <tr class="bg-gray-100 dark:bg-gray-700">
                                        <th class="p-2 border">Odaberi</th>
                                        <th class="p-2 border">Naziv</th>
                                        <th class="p-2 border">Metraža</th>
                                        <th class="p-2 border">Količina</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(p, idx) in productListNew" :key="`${p.id ?? ''}-${p.NumeraProizvoda ?? idx}`"
                                        class="border-b">
                                        <td class="p-2 text-center">
                                            <input type="checkbox"
                                                :id="'product-checkbox-' + (p.id ?? p.NumeraProizvoda ?? idx)"
                                                v-model="p.selected" />
                                        </td>
                                        <td class="p-2">
                                            <label :for="'product-checkbox-' + (p.id || p.NumeraProizvoda || idx)"
                                                class="cursor-pointer">
                                                <span v-if="p.NumeraProizvoda">{{ p.NumeraProizvoda }}</span>
                                                <span v-else-if="p.SkraceniNaziv">{{ p.SkraceniNaziv }}</span>
                                                <span v-else>- stavka {{ idx + 1 }}</span>
                                            </label>
                                        </td>
                                        <td class="p-2 text-center">
                                            <span v-if="p.UoM_meter != null">{{ p.UoM_meter }}</span>
                                        </td>
                                        <td class="p-2">
                                            <input type="number" min="0" class="w-20 px-2 py-1 border rounded"
                                                v-model="p.kolicina" placeholder="Količina" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" v-cloak>
                                <!--         {{ debugText }} -->
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

const formTitle = computed(() => isEditMode.value ? 'Uredi nalog' : 'Kreiraj nalog');
const submitLabel = computed(() => isEditMode.value ? 'Sačuvaj izmjene' : 'Pregled');

// Da li je izabran BIHNEL (koristi se za disable polja Metraza/VrstaProvodnika/Tip)
const isBihnelSelected = computed(() => /BIHNEL/i.test(form.value.Description || ''));
// Da li je izabran BK-6 (disable: Metraza, Status, Vrsta provodnika, Tip, Boja)
const isBK6Selected = computed(() => /(^|\b)BK[-\s]?6(\b|$)/i.test(form.value.Description || ''));
// Da li je izabran BK-8 (disable polja kao BK-6)
const isBK8Selected = computed(() => /(^|\b)BK[-\s]?8(\s+(LP|MS))?(\b|$)/i.test(form.value.Description || ''));

const debugText = computed(() =>
    `Debug: desc=${form.value.Description} | metraza=${form.value.Metraza} | provodnik=${form.value.VrstaProvodnika} | tip=${form.value.Tip} | listLen=${productListNew.value.length}`
);

// Prikaz proizvoda za BIHNEL proizvode
async function setupProductListBihnel() {
    console.log('Setting up productlist Bihnel');
    const input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            const response = await axios.get(`/productslistBihnel?query=${input}`);
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

// Prikaz proizvoda za BK-8 proizvode
async function setupProductListBK8(variant) {
    console.log('Setting up productlist BK-8');
    let input = form.value.Description?.trim();
    productListNew.value = [];
    if (input && input.length > 0) {
        try {
            // Ako korisnik unese "BK-8 LP", očisti varijantni dio iz query-a, a varijantu pošalji posebno
            if (variant) {
                input = input.replace(/\b(LP|MS)\b/gi, '').trim();
            }
            const url = variant
                ? `/productslistBK8?query=${encodeURIComponent(input)}&variant=${encodeURIComponent(variant)}`
                : `/productslistBK8?query=${encodeURIComponent(input)}`;
            const response = await axios.get(url);
            let data = response.data || [];
            console.log('Received /productslistBK8 items:', data);
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
    if (isHydrating.value) return;
    if (isEditMode.value && preferDetailsList.value) { preferDetailsList.value = false; return; }
    if (!newValue || newValue.length === 0) {
        productSuggestions.value = [];
        return;
    }
    try {
        // 1) BK-8: prvo ponudi LP/MS, a ako je izabrano učitaj
        const isBK8 = /(^|\b)BK[-\s]?8(\b|$)/i.test(newValue);
        const hasLP = /\bLP\b/i.test(newValue);
        const hasMS = /\bMS\b/i.test(newValue);
        if (isBK8 && !(hasLP || hasMS)) {
            productSuggestions.value = ['BK-8 LP', 'BK-8 MS'];
            productListNew.value = [];
            // Postavi defaulte kao za BK-6
            form.value.Metraza = 0;
            form.value.VrstaProvodnika = '-';
            form.value.Tip = '-';
            form.value.BojaDuzinaProvodnika = '-';
            return;
        }
        if (isBK8 && (hasLP || hasMS)) {
            const chosen = hasLP ? 'LP' : 'MS';
            productSuggestions.value = [];
            // Postavi defaulte kao za BK-6
            form.value.Metraza = 0;
            form.value.VrstaProvodnika = '-';
            form.value.Tip = '-';
            form.value.BojaDuzinaProvodnika = '-';
            await setupProductListBK8(chosen);
            return;
        }

        // 2) BIHNEL: odmah učitaj
        if (/BIHNEL/i.test(newValue)) {
            productSuggestions.value = [];
            // Resetuj ova polja jer se zaključavaju i ne trebaju za BIHNEL
            form.value.Metraza = '';
            form.value.VrstaProvodnika = '';
            form.value.Tip = '';
            await setupProductListBihnel();
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

        // 4) Ostalo: dohvati generalne sugestije
        const { data } = await axios.get(`/products?query=${encodeURIComponent(newValue)}`);
        productSuggestions.value = [...new Set(data.map(p => p.SkraceniNaziv))];
    } catch (error) {
        console.error("Error fetching products:", error);
    }
});

watch([() => form.value.Description, () => form.value.VrstaProvodnika], async ([desc, provodnik]) => {
    if (isHydrating.value) return;
    if (isEditMode.value && preferDetailsList.value) { preferDetailsList.value = false; return; }
    if (desc && provodnik) {
        // Za BIHNEL već smo popunili vrijednosti direktno iz liste – preskačemo dummy endpoint
        if (/BIHNEL/i.test(desc)) return;
        // Za BK-6 već popunjavamo CE podatke kroz listu BK-6 – preskoči poziv
        if (/(^|\b)BK[-\s]?6(\b|$)/i.test(desc)) return;
        // Za BK-8 takođe CE podatke popunjavamo iz liste – preskoči poziv
        if (/(^|\b)BK[-\s]?8(\s+(LP|MS))?(\b|$)/i.test(desc)) return;
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
    // Izbjegni generički poziv kada su specijalni proizvodi (BK-6/BK-8/BIHNEL) aktivni
    if (/(^|\b)BK[-\s]?6(\b|$)/i.test(desc) || /(^|\b)BK[-\s]?8(\s+(LP|MS))?(\b|$)/i.test(desc) || /BIHNEL/i.test(desc)) {
        return;
    }
    if (desc && metraza && provodnik && tip) {
        try {
            console.log('Fetching /productslist with', { desc, metraza, provodnik, tip });
            const { data } = await axios.get(`/productslist?query=${encodeURIComponent(desc)}&uom_meter=${metraza}&provodnik=${encodeURIComponent(provodnik)}&tip=${encodeURIComponent(tip)}`);
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
    const isBK8 = /(^|\b)BK[-\s]?8(\b|$)/i.test(desc);
    const hasLP = /\bLP\b/i.test(desc);
    const hasMS = /\bMS\b/i.test(desc);
    const isBihnel = /BIHNEL/i.test(desc);

    try {
        if (isBihnel) {
            await setupProductListBihnel();
        } else if (isBK6) {
            await setupProductListBK6();
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
    console.log('OrderNumbers:', workingOrders.value.map(o => o.OrderNumber));
    // Dobavi workingOrders i ostale podatke po potrebi
});
</script>

<style>
[v-cloak] {
    display: none;
}
</style>
