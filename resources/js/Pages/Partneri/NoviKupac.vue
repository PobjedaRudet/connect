<template>
  <ProductionAppLayout title="Novi kupac">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Novi kupac</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Unesite podatke o kupcu i sačuvajte.</p>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <form @submit.prevent="submit" class="space-y-5">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Naziv (name) <span class="text-red-500">*</span></label>
                  <input v-model="form.name" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">E-mail</label>
                  <input v-model="form.email" type="email" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Adresa</label>
                  <input v-model="form.address" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Telefon</label>
                  <input v-model="form.phone" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Tip</label>
                  <select v-model="form.type" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200">
                    <option value="kupac">kupac</option>
                    <option value="dobavljač">dobavljač</option>
                    <option value="ostalo">ostalo</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Oznaka</label>
                  <input v-model="form.oznaka" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Grad</label>
                  <input v-model="form.city" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Država</label>
                  <input v-model="form.country" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
              </div>

              <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" @click="reset" class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Reset</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded">Sačuvaj</button>
              </div>

              <p v-if="message" class="text-sm mt-2" :class="messageClass">{{ message }}</p>
            </form>
          </div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>
</template>

<script setup>
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';
import { ref } from 'vue';
import axios from 'axios';

const form = ref({
  name: '',
  email: '',
  address: '',
  phone: '',
  type: 'kupac',
  oznaka: '',
  city: '',
  country: '',
});

const message = ref('');
const messageClass = ref('text-green-600');

async function submit() {
  message.value = '';
  try {
    const { data } = await axios.post('/kupci', form.value);
    messageClass.value = 'text-green-600';
    message.value = data?.message || 'Sačuvano.';
    // Opcionalno: očisti formu
    reset();
  } catch (e) {
    messageClass.value = 'text-red-600';
    if (e?.response?.data?.errors) {
      const first = Object.values(e.response.data.errors)[0];
      message.value = Array.isArray(first) ? first[0] : 'Greška pri unosu.';
    } else {
      message.value = e?.response?.data?.message || 'Greška pri unosu.';
    }
  }
}

function reset() {
  form.value = { name: '', email: '', address: '', phone: '', type: 'kupac', oznaka: '', city: '', country: '' };
}
</script>
