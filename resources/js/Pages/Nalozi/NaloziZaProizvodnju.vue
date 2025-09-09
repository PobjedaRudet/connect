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
                            Kreiraj nalog
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
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required>
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
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        step="0.01" required />
                                </div>
                                <div class="col-span-1">
                                    <label for="Status"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Status</label>
                                    <input type="text" v-model="form.Status" id="status"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="VrstaProvodnika"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Vrsta
                                        provodnika</label>
                                    <select v-model="form.VrstaProvodnika" id="vrstaProvodnika"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required>
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
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required>
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label for="BojaDuzinaProvodnika"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Boja Duzina
                                        Provodnika</label>
                                    <input type="text" v-model="form.BojaDuzinaProvodnika" id="bojaDuzinaProvodnika"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="Pakovanje"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Pakovanje</label>
                                    <input type="text" v-model="form.Pakovanje" id="pakovanje"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
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
                                    <label for="RokIsporuke"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Rok
                                        Isporuke</label>
                                    <input type="text" v-model="form.RokIsporuke" id="rokIsporuke"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="DatumPredaje"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Datum
                                        Predaje</label>
                                    <input type="date" v-model="form.DatumPredaje" id="datumPredaje"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-1">
                                    <label for="DatumPrijema"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Datum
                                        Prijema</label>
                                    <input type="date" v-model="form.DatumPrijema" id="datumPrijema"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required />
                                </div>
                                <div class="col-span-2">
                                    <label for="Napomena"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-200">Napomena</label>
                                    <textarea v-model="form.Napomena" id="napomena"
                                        class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200"
                                        required></textarea>
                                </div>
                            </div>
                            <div class="flex items-center justify-end mt-4">
                                <button id="pregledBtn" type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 dark:focus:ring-gray-600 disabled:opacity-25 transition ease-in-out duration-150">Pregled</button>
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
                                        <th class="p-2 border">Količina</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(p, idx) in productListNew" :key="p.id || p.NumeraProizvoda || idx"
                                        class="border-b">
                                        <td class="p-2 text-center">
                                            <input type="checkbox"
                                                :id="'product-checkbox-' + (p.id || p.NumeraProizvoda || idx)"
                                                v-model="p.selected" />
                                        </td>
                                        <td class="p-2">
                                            <label :for="'product-checkbox-' + (p.id || p.NumeraProizvoda || idx)"
                                                class="cursor-pointer">
                                                <span v-if="p.NumeraProizvoda !== undefined">{{ p.NumeraProizvoda
                                                }}</span>
                                                <span v-if="p.SkraceniNaziv"> - {{ p.SkraceniNaziv }}</span>
                                                <span v-else> - stavka {{ idx + 1 }}</span>
                                            </label>
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
    Status: '',
    VrstaProvodnika: '',
    Tip: '',
    BojaDuzinaProvodnika: '',
    Pakovanje: '',
    AtestPaketa: '',
    CeOznaka: '',
    KlasaOpasnosti: '',
    UNBroj: '',
    RokIsporuke: '',
    DatumPredaje: '',
    DatumPrijema: '',
    Napomena: '',
});

const workingOrders = ref(props.workingOrders || []);
const partners = ref(props.partners || []);
const productSuggestions = ref([]);
const productListNew = ref([]);

const debugText = computed(() =>
    `Debug: desc=${form.value.Description} | metraza=${form.value.Metraza} | provodnik=${form.value.VrstaProvodnika} | tip=${form.value.Tip} | listLen=${productListNew.value.length}`
);

async function getOrderNumber() {
    try {
        const { data } = await axios.get('/getOrderNumber');
        form.value.OrderNumber = data.orderNumber;
    } catch (error) {
        console.error("Error fetching order number:", error);
    }
}

watch(() => form.value.Description, async (newValue) => {
    if (newValue.length > 0) {
        try {
            const { data } = await axios.get(`/products?query=${newValue}`);
            productSuggestions.value = [...new Set(data.map(p => p.SkraceniNaziv))];
            if (newValue.includes('BIHNEL')) {
                form.value.Metraza = '';
                form.value.VrstaProvodnika = '';
                form.value.Tip = '';
            }
        } catch (error) {
            console.error("Error fetching products:", error);
        }
    }
});

watch([() => form.value.Description, () => form.value.VrstaProvodnika], async ([desc, provodnik]) => {
    if (desc && provodnik) {
        try {
            const { data } = await axios.get(`/getCeOznaka?naziv=${desc}&vrstaProvodnika=${provodnik}`);
            form.value.CeOznaka = data.CEMarkNumber;
            form.value.KlasaOpasnosti = data.HazardClass;
            form.value.UNBroj = data.UNNumber;
        } catch (error) {
            console.error("Error fetching CE oznaka:", error);
        }
    }
});

watch([() => form.value.Description, () => form.value.Metraza, () => form.value.VrstaProvodnika, () => form.value.Tip], async ([desc, metraza, provodnik, tip]) => {
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

async function submitForm() {
    partnerError.value = false;
    if (!form.value.partner_id) {
        partnerError.value = true;
        return;
    }
    try {
        // Pripremi proizvode tako da svaki ima quantity (backend očekuje ovo polje)
        const selectedProducts = productListNew.value.filter(p => p.selected).map(p => ({
            id: p.id,
            quantity: p.kolicina !== undefined ? p.kolicina : p.quantity // fallback ako je već quantity
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
        await axios.post('/productionorders', payload);

        alert('Podaci su poslani!');
        window.location.reload();
    } catch (error) {
        alert('Greška pri slanju podataka!');
        console.error(error);
    }
}

onMounted(() => {
    getOrderNumber();
    console.log('OrderNumbers:', workingOrders.value.map(o => o.OrderNumber));
    // Dobavi workingOrders i ostale podatke po potrebi
});
</script>

<style>
[v-cloak] {
    display: none;
}
</style>
