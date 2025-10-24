<template>
  <ProductionAppLayout title="Novi proizvod">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Novi proizvod</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Unesite podatke o proizvodu i sačuvajte.</p>
        </div>
        <div>
          <Link href="/proizvodi" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Nazad</Link>
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
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Naziv <span class="text-red-500">*</span></label>
                  <input v-model="form.Naziv" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" required />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Skraćeni naziv</label>
                  <input v-model="form.SkraceniNaziv" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Tip</label>
                  <input v-model="form.Tip" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Jedinica mjere</label>
                  <input v-model="form.JedinicaMjere" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Code</label>
                  <input v-model="form.Code" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">UoM (meter)</label>
                  <input v-model="form.UoM_meter" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Usporenje (ms)</label>
                  <input v-model.number="form.UsporenjeMs" type="number" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">UN Number</label>
                  <input v-model="form.UNNumber" type="text" maxlength="16" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Hazard Class</label>
                  <input v-model="form.HazardClass" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">CE oznaka</label>
                  <input v-model="form.CEMarkNumber" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Numera proizvoda</label>
                  <input v-model.number="form.NumeraProizvoda" type="number" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
                </div>
                <div>
                  <label class="block text-xs text-gray-600 dark:text-gray-300">Vrsta provodnika</label>
                  <input v-model="form.VrstaProvodnika" type="text" class="form-input rounded-md mt-1 block w-full dark:bg-gray-700 dark:text-gray-200" />
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

const form = ref({
  Naziv: '',
  SkraceniNaziv: '',
  Tip: '',
  JedinicaMjere: '',
  Code: '',
  UoM_meter: '',
  UsporenjeMs: null,
  UNNumber: '',
  HazardClass: '',
  CEMarkNumber: '',
  NumeraProizvoda: null,
  VrstaProvodnika: '',
});

const message = ref('');
const messageClass = ref('text-green-600');

async function submit() {
  message.value = '';
  try {
    await router.post('/proizvodi', form.value, { preserveState: true });
    messageClass.value = 'text-green-600';
    message.value = 'Sačuvano.';
  } catch (e) {
    messageClass.value = 'text-red-600';
    message.value = 'Greška pri unosu.';
  }
}

function reset() {
  form.value = { Naziv: '', SkraceniNaziv: '', Tip: '', JedinicaMjere: '', Code: '', UoM_meter: '', UsporenjeMs: null, UNNumber: '', HazardClass: '', CEMarkNumber: '', NumeraProizvoda: null, VrstaProvodnika: '' };
}
</script>
