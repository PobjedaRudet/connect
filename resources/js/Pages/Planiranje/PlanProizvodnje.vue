<template>
  <ProductionAppLayout title="Planiranje proizvodnje">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Planiranje proizvodnje</h2>
    </template>
    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow md:col-span-2">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block text-xs text-gray-500">Objekat</label>
              <select v-model="form.objekat" class="form-input w-full mt-1">
                <option disabled value="">-- odaberite objekat --</option>
                <option v-for="o in objekti" :key="o" :value="o">{{ o }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500">Datum laboracije</label>
              <input v-model="form.laboracija_datum" type="date" class="form-input w-full mt-1" />
            </div>
          </div>

          <!-- Masovni unos raspona datuma za više naloga -->
          <div class="border rounded p-3 mb-4">
            <h3 class="text-sm font-semibold mb-3">Masovni unos (opcionalno)</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
              <div>
                <label class="block text-xs text-gray-500">Početak (za odabrane)</label>
                <input v-model="bulk.start_date" type="date" class="form-input w-full mt-1" />
              </div>
              <div>
                <label class="block text-xs text-gray-500">Kraj (za odabrane)</label>
                <input v-model="bulk.end_date" type="date" class="form-input w-full mt-1" />
              </div>
              <div class="md:col-span-2">
                <label class="block text-xs text-gray-500">Odaberite naloge</label>
                <input v-model="bulk.q" type="text" placeholder="Pretraga naloga..." class="form-input w-full mt-1 mb-2" />
                <div class="max-h-40 overflow-auto border rounded p-2 divide-y divide-gray-100 dark:divide-gray-700 bg-gray-50 dark:bg-gray-900">
                  <label v-for="o in filteredOrders" :key="o.id" class="flex items-center gap-2 py-1 text-sm">
                    <input type="checkbox" :value="o.id" v-model="bulk.selected" />
                    <span class="truncate"><span class="font-medium">{{ o.OrderNumber }}</span> — {{ o.Description }}</span>
                  </label>
                </div>
              </div>
            </div>
            <div class="mt-3 flex gap-2">
              <button type="button" @click="applyBulk" class="px-3 py-2 bg-gray-800 text-white rounded">Dodaj/primijeni na odabrane</button>
              <button type="button" @click="clearBulk" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded">Očisti izbor</button>
            </div>
          </div>

          <div class="border rounded p-3">
            <h3 class="text-sm font-semibold mb-3">Stavke plana</h3>
            <div v-for="(it, idx) in form.items" :key="idx" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3 items-end">
              <div class="md:col-span-2">
                <label class="block text-xs text-gray-500">Nalog</label>
                <select v-model.number="it.order_id" class="form-input w-full mt-1">
                  <option disabled value="">-- izaberite nalog --</option>
                  <option v-for="o in orders" :key="o.id" :value="o.id">{{ o.OrderNumber }} — {{ o.Description }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs text-gray-500">Početak</label>
                <input v-model="it.start_date" type="date" class="form-input w-full mt-1" />
              </div>
              <div>
                <label class="block text-xs text-gray-500">Kraj</label>
                <input v-model="it.end_date" type="date" class="form-input w-full mt-1" />
              </div>
            </div>

            <div class="flex gap-2">
              <button @click="addItem" type="button" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded">+ Dodaj nalog</button>
              <button @click="removeLast" type="button" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded" :disabled="form.items.length===1">- Ukloni zadnji</button>
            </div>
          </div>

          <div class="mt-4 flex justify-end">
            <button @click="submit" class="px-4 py-2 bg-gray-800 text-white rounded">Snimi plan</button>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
          <h3 class="text-sm font-semibold mb-2">Dostupni nalozi (posljednjih 200)</h3>
          <div class="max-h-96 overflow-auto divide-y divide-gray-100 dark:divide-gray-700 text-sm">
            <div v-for="o in orders" :key="o.id" class="py-2">
              <div class="font-medium">{{ o.OrderNumber }} — {{ o.Description }}</div>
              <div class="text-xs text-gray-500">{{ o.OrderDate }} • {{ o.Status }}</div>
            </div>
          </div>
          <div v-if="plans && plans.length" class="mt-6">
            <h3 class="text-sm font-semibold mb-2">Posljednji planovi</h3>
            <ul class="text-sm list-disc pl-5">
              <li v-for="p in plans" :key="p.id">
                {{ p.objekat }} — {{ p.laboracija_datum || '-' }} ({{ p.items?.length || 0 }} naloga)
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

const props = defineProps({
  orders: { type: Array, default: () => [] },
  plans: { type: Array, default: () => [] },
  objekti: { type: Array, default: () => [] },
});

const orders = ref(props.orders || []);
const plans = ref(props.plans || []);
const objekti = ref(props.objekti || []);

const form = ref({
  objekat: '',
  laboracija_datum: '',
  items: [{ order_id: '', start_date: '', end_date: '' }],
});

const bulk = ref({ start_date: '', end_date: '', selected: [], q: '' });
const filteredOrders = computed(() => {
  const q = (bulk.value.q || '').toLowerCase();
  if (!q) return orders.value;
  return orders.value.filter(o =>
    String(o.OrderNumber).toLowerCase().includes(q) ||
    String(o.Description || '').toLowerCase().includes(q)
  );
});

function addItem() {
  form.value.items.push({ order_id: '', start_date: '', end_date: '' });
}
function removeLast() {
  if (form.value.items.length > 1) form.value.items.pop();
}

function clearBulk() {
  bulk.value.start_date = '';
  bulk.value.end_date = '';
  bulk.value.selected = [];
  bulk.value.q = '';
}

function applyBulk() {
  // basic validation
  if (!bulk.value.start_date || !bulk.value.end_date) {
    alert('Unesite početni i krajnji datum.');
    return;
  }
  if (bulk.value.end_date < bulk.value.start_date) {
    alert('Krajnji datum ne može biti prije početnog.');
    return;
  }
  if (!bulk.value.selected.length) {
    alert('Odaberite najmanje jedan nalog.');
    return;
  }
  const existingByOrderId = new Map();
  form.value.items.forEach((it, idx) => {
    if (it.order_id) existingByOrderId.set(Number(it.order_id), idx);
  });
  for (const oid of bulk.value.selected) {
    const numId = Number(oid);
    if (existingByOrderId.has(numId)) {
      const idx = existingByOrderId.get(numId);
      form.value.items[idx].start_date = bulk.value.start_date;
      form.value.items[idx].end_date = bulk.value.end_date;
    } else {
      form.value.items.push({
        order_id: numId,
        start_date: bulk.value.start_date,
        end_date: bulk.value.end_date,
      });
    }
  }
  // keep one empty row at the end for manual input convenience
  if (!form.value.items.length || form.value.items[form.value.items.length - 1].order_id) {
    form.value.items.push({ order_id: '', start_date: '', end_date: '' });
  }
  // optional: clear selection (dates left in case user wants to apply again)
  bulk.value.selected = [];
}

async function submit() {
  try {
    const payload = {
      ...form.value,
      items: (form.value.items || []).filter(
        (it) => it.order_id && it.start_date && it.end_date
      ),
    };
    if (!payload.items.length) {
      alert('Dodajte barem jedan kompletan zapis (nalog + datumi).');
      return;
    }
    await axios.post('/planiranje/proizvodnja', payload);
    alert('Plan je snimljen');
    window.location.reload();
  } catch (e) {
    let msg = 'Greška pri snimanju plana';
    if (e && e.response && e.response.data) {
      const d = e.response.data;
      if (d.message && typeof d.message === 'string') msg = d.message;
      if (d.errors) {
        const lines = [];
        Object.entries(d.errors).forEach(([k, arr]) => {
          (arr || []).forEach((m) => lines.push(`${k}: ${m}`));
        });
        if (lines.length) msg += `\n\n` + lines.join('\n');
      }
    }
    alert(msg);
    console.error(e);
  }
}
</script>
