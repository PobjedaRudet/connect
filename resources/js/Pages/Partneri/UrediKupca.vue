<template>
  <ProductionAppLayout title="Uredi kupca">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Uredi kupca</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Izmijenite podatke i sačuvajte.</p>
        </div>
        <div>
          <Link href="/kupci" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Nazad</Link>
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
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  partner: { type: Object, required: true },
});

const form = ref({
  name: props.partner.name || '',
  email: props.partner.email || '',
  address: props.partner.address || '',
  phone: props.partner.phone || '',
  type: props.partner.type || 'kupac',
  oznaka: props.partner.oznaka || '',
  city: props.partner.city || '',
  country: props.partner.country || '',
});

const message = ref('');
const messageClass = ref('text-green-600');

async function submit() {
  message.value = '';
  try {
    await router.put(`/kupci/${props.partner.id}`, form.value, { preserveState: true });
    messageClass.value = 'text-green-600';
    message.value = 'Sačuvano.';
  } catch (e) {
    messageClass.value = 'text-red-600';
    message.value = 'Greška pri unosu.';
  }
}

function reset() {
  form.value = { name: props.partner.name || '', email: props.partner.email || '', address: props.partner.address || '', phone: props.partner.phone || '', type: props.partner.type || 'kupac', oznaka: props.partner.oznaka || '', city: props.partner.city || '', country: props.partner.country || '' };
}
</script>
