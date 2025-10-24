<template>
  <ProductionAppLayout title="Praznici (neradni dani)">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Praznici (neradni dani)</h2>
    </template>

    <div class="py-6">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
          <div class="text-sm text-gray-500 mb-3">Dodaj novi praznik:</div>
          <form @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
              <div>
                <label class="block text-xs text-gray-500">Datum</label>
                <input v-model="form.date" type="date" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition" required />
                <div v-if="form.errors.date" class="text-red-600 text-xs mt-1">{{ form.errors.date }}</div>
              </div>
              <div class="md:col-span-2">
                <label class="block text-xs text-gray-500">Naziv</label>
                <input v-model="form.name" type="text" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition" placeholder="npr. Dan državnosti" required />
                <div v-if="form.errors.name" class="text-red-600 text-xs mt-1">{{ form.errors.name }}</div>
              </div>
            </div>
            <div class="mt-3 flex justify-end">
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Dodaj</button>
            </div>
          </form>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
          <div class="text-sm font-semibold mb-2">Spisak praznika</div>
          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="h in holidays" :key="h.id" class="py-2 flex items-center justify-between">
              <div>
                <div class="font-medium">{{ formatDate(h.date) }}</div>
                <div class="text-xs text-gray-500">{{ h.name }}</div>
              </div>
              <button class="px-3 py-1 text-sm bg-red-600 text-white rounded" @click="remove(h)">Obriši</button>
            </div>
            <div v-if="!holidays || holidays.length===0" class="text-sm text-gray-500">Nema unesenih praznika.</div>
          </div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

const props = defineProps({
  holidays: { type: Array, default: () => [] },
});

const holidays = props.holidays || [];

const form = useForm({ date: '', name: '' });
function submit() {
  form.post(route('planning.holidays.store'), { preserveScroll: true, onSuccess: () => form.reset() });
}
function remove(h) {
  if (!h?.id) return;
  router.delete(route('planning.holidays.destroy', h.id), { preserveScroll: true });
}
function formatDate(d) {
  try {
    const dt = new Date(d);
    const day = String(dt.getDate()).padStart(2, '0');
    const month = String(dt.getMonth() + 1).padStart(2, '0');
    const year = dt.getFullYear();
    return `${day}/${month}/${year}`;
  } catch { return d; }
}
</script>
