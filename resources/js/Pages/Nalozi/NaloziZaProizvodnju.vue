<template>
  <AppLayout title="Nalozi za proizvodnju">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Nalozi
      </h2>
    </template>
    <div class="flex py-12">
      <div class="flex max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white grid grid-cols-3 gap-4 dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg w-full">
          <div class="col-span-2 p-6 text-gray-900 dark:text-gray-100 border-r border-gray-300 dark:border-gray-700">
            <div class="text-center pb-5">
              Kreiraj nalog
            </div>
            <form @submit.prevent="submitForm">
              <input type="hidden" v-model="form.productListNew">
              <input type="hidden" v-model="form.user_id">
              <div class="grid grid-cols-4 gap-4">
                <div class="col-span-1">
                  <label for="OrderNumber" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Broj naloga</label>
                  <input type="text" v-model="form.OrderNumber" id="orderNumber" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" disabled />
                </div>
                <div class="col-span-1">
                  <label for="VezaNaNalog" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Veza na nalog</label>
                  <select v-model="form.VezaNaNalog" id="vezaNaNalog" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required>
                    <option v-for="order in workingOrders" :key="order.id" :value="order.id">{{ order.OrderNumber }}</option>
                  </select>
                </div>
                <div class="col-span-1">
                  <label for="OrderDate" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Order Date</label>
                  <input type="date" v-model="form.OrderDate" id="orderDate" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="Description" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Naziv</label>
                  <input list="productSuggestions" v-model="form.Description" id="productInput" class="form-control rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" placeholder="Unesi naziv proizvoda..." />
                  <datalist id="productSuggestions">
                    <option v-for="product in productSuggestions" :key="product" :value="product" />
                  </datalist>
                </div>
                <div class="col-span-1">
                  <label for="metraza" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Metraža</label>
                  <input type="number" v-model="form.Metraza" id="metraza" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" step="0.01" required />
                </div>
                <div class="col-span-1">
                  <label for="Status" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Status</label>
                  <input type="text" v-model="form.Status" id="status" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="VrstaProvodnika" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Vrsta provodnika</label>
                  <select v-model="form.VrstaProvodnika" id="vrstaProvodnika" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required>
                    <option value="Al">Al</option>
                    <option value="Cu">Cu</option>
                    <option value="Fe">Fe</option>
                    <option value="V">V</option>
                    <option value="Zn">Zn</option>
                  </select>
                </div>
                <div class="col-span-1">
                  <label for="Tip" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Tip</label>
                  <select v-model="form.Tip" id="tip" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required>
                    <option value="A">A</option>
                    <option value="B">B</option>
                  </select>
                </div>
                <div class="col-span-1">
                  <label for="BojaDuzinaProvodnika" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Boja Duzina Provodnika</label>
                  <input type="text" v-model="form.BojaDuzinaProvodnika" id="bojaDuzinaProvodnika" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="Pakovanje" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Pakovanje</label>
                  <input type="text" v-model="form.Pakovanje" id="pakovanje" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="AtestPaketa" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Atest Paketa</label>
                  <input type="text" v-model="form.AtestPaketa" id="atestPaketa" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="CeOznaka" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Ce Oznaka</label>
                  <input type="text" v-model="form.CeOznaka" id="ceOznaka" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="KlasaOpasnosti" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Klasa Opasnosti</label>
                  <input type="text" v-model="form.KlasaOpasnosti" id="klasaOpasnosti" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="UNBroj" class="block text-sm font-medium text-gray-700 dark:text-gray-200">UN Broj</label>
                  <input type="text" v-model="form.UNBroj" id="unBroj" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="RokIsporuke" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Rok Isporuke</label>
                  <input type="text" v-model="form.RokIsporuke" id="rokIsporuke" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="DatumPredaje" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Datum Predaje</label>
                  <input type="date" v-model="form.DatumPredaje" id="datumPredaje" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-1">
                  <label for="DatumPrijema" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Datum Prijema</label>
                  <input type="date" v-model="form.DatumPrijema" id="datumPrijema" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div class="col-span-2">
                  <label for="Napomena" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Napomena</label>
                  <textarea v-model="form.Napomena" id="napomena" class="form-input rounded-md shadow-sm mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required></textarea>
                </div>
              </div>
              <div class="flex items-center justify-end mt-4">
                <button id="pregledBtn" type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 dark:focus:ring-gray-600 disabled:opacity-25 transition ease-in-out duration-150">Pregled</button>
              </div>
            </form>
          </div>
          <div class="flex col-span-1 p-1 grid grid-cols-1 bg-white dark:bg-gray-800">
            <div class="mt-8">
              <label for="productSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Numere proizvoda</label>
              <ul id="productList" class="mt-4 list-disc list-inside text-gray-700 dark:text-gray-200"></ul>
              <ul id="productListNew" class="mt-4 list-disc list-inside text-gray-700 dark:text-gray-200"></ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const form = ref({
  productListNew: '',
  user_id: '',
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

const workingOrders = ref([]); // Popuni iz API-ja ili props
const productSuggestions = ref([]); // Popuni iz API-ja ili props

function submitForm() {
  // Pozovi API za spremanje naloga
  // npr. axios.post('/api/nalozi', form.value)
  alert('Forma je poslana!');
}
</script>
