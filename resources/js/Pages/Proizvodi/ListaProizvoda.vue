<template>
  <ProductionAppLayout title="Proizvodi">
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Proizvodi</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Pregled, pretraga i uređivanje proizvoda.</p>
        </div>
        <div>
          <Link href="/proizvodi/novi" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded">Novi proizvod</Link>
        </div>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-4 flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Pretraga</label>
              <input v-model="q" @input="queueLoad" type="text" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200" placeholder="Naziv, skraćeni naziv, code, tip, jm..." />
            </div>
            <div class="ml-auto">
              <button @click="reload" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Osvježi</button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gray-50 dark:bg-gray-700/70 text-gray-700 dark:text-gray-200">
                  <th class="px-3 py-2 text-left">Naziv</th>
                  <th class="px-3 py-2 text-left">Skraćeni</th>
                  <th class="px-3 py-2 text-left">Tip</th>
                  <th class="px-3 py-2 text-left">JM</th>
                  <th class="px-3 py-2 text-left">Code</th>
                  <th class="px-3 py-2 text-left">Numera</th>
                  <th class="px-3 py-2 text-left">Akcije</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="p in products.data" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td class="px-3 py-2">{{ p.Naziv }}</td>
                  <td class="px-3 py-2">{{ p.SkraceniNaziv }}</td>
                  <td class="px-3 py-2">{{ p.Tip }}</td>
                  <td class="px-3 py-2">{{ p.JedinicaMjere }}</td>
                  <td class="px-3 py-2">{{ p.Code }}</td>
                  <td class="px-3 py-2">{{ p.NumeraProizvoda }}</td>
                  <td class="px-3 py-2">
                    <Link :href="`/proizvodi/${p.id}/edit`" class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600">Uredi</Link>
                  </td>
                </tr>
                <tr v-if="!products.data || products.data.length===0">
                  <td colspan="7" class="px-3 py-4 text-center text-gray-500">Nema podataka.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="p-4 flex items-center justify-between text-xs text-gray-600 dark:text-gray-300">
            <div>
              Stranica {{ products.current_page }} / {{ products.last_page }} ({{ products.total }} ukupno)
            </div>
            <div class="flex gap-2">
              <button :disabled="products.current_page<=1" @click="goPage(products.current_page-1)" class="px-3 py-1 border rounded disabled:opacity-50">«</button>
              <button :disabled="products.current_page>=products.last_page" @click="goPage(products.current_page+1)" class="px-3 py-1 border rounded disabled:opacity-50">»</button>
            </div>
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
  q: { type: String, default: '' },
  products: { type: Object, required: true },
});

const q = ref(props.q);

let searchDebounce = null;
function queueLoad(){
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => reload(), 300);
}

function reload(){
  router.get('/proizvodi', { q: q.value }, { preserveState: true, replace: true });
}

function goPage(p){
  router.get('/proizvodi', { q: q.value, page: p }, { preserveState: true, replace: true });
}
</script>
