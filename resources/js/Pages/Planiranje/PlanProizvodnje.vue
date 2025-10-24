<template>
  <ProductionAppLayout title="Planiranje proizvodnje">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Planiranje proizvodnje</h2>
    </template>
    <div class="py-6">
      <div class="flex justify-center">
        <!-- Main Card -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded shadow w-full max-w-3xl">
          <!-- Stepper -->
          <div class="flex items-center text-sm mb-4">
            <div class="flex items-center">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-white bg-blue-600">1</span>
              <span class="ml-2 font-medium">Objekat</span>
            </div>
            <div class="mx-3 h-[1px] grow bg-gray-200 dark:bg-gray-700"></div>
            <div class="flex items-center">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-white" :class="valid.objekat ? 'bg-blue-600' : 'bg-gray-400'">2</span>
              <span class="ml-2 font-medium">Datumi</span>
            </div>
            <div class="mx-3 h-[1px] grow bg-gray-200 dark:bg-gray-700"></div>
            <div class="flex items-center">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-white" :class="valid.dates ? 'bg-blue-600' : 'bg-gray-400'">3</span>
              <span class="ml-2 font-medium">Nalozi</span>
            </div>
            <div class="mx-3 h-[1px] grow bg-gray-200 dark:bg-gray-700"></div>
            <div class="flex items-center">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-white" :class="valid.orders ? 'bg-blue-600' : 'bg-gray-400'">4</span>
              <span class="ml-2 font-medium">Pregled</span>
            </div>
          </div>

          <!-- Step 1 + 2: Objekat + Datumi (refactored layout) -->
          <div class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
              <div>
                <label class="block text-xs text-gray-500">Objekat</label>
                <select v-model="form.objekat" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition">
                  <option disabled value="">-- odaberite objekat --</option>
                  <option v-for="o in objekti" :key="o" :value="o">{{ o }}</option>
                </select>
                <p v-if="attempted && !valid.objekat" class="text-xs text-red-600 mt-1">Objekat je obavezan.</p>
              </div>
              <div v-if="requiresLaboracija">
                <label class="block text-xs text-gray-500">Datum laboracije</label>
                <input v-model="form.laboracija_datum" type="date" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition" />
                <!-- Smjene prikazuj samo ako NIJE Kompletiranje ili Kompletiranje Nonel -->
                <div class="flex gap-4 mt-2" v-if="!['kompletiranje','kompletiranje nonel'].includes((form.objekat || '').toLowerCase())">
                  <label class="inline-flex items-center">
                    <input type="checkbox" :name="'laboracija_smjena_I'" value="I" v-model="form.laboracija_smjene" class="form-checkbox" />
                    <span class="ml-1 text-xs">I smjena</span>
                  </label>
                  <label class="inline-flex items-center">
                    <input type="checkbox" :name="'laboracija_smjena_II'" value="II" v-model="form.laboracija_smjene" class="form-checkbox" />
                    <span class="ml-1 text-xs">II smjena</span>
                  </label>
                </div>
                <p v-if="attempted && !valid.laboracija" class="text-xs text-red-600 mt-1">Datum i smjena su obavezni.</p>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs text-gray-500">Početak</label>
                <input v-model="bulk.start_date" type="date" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition" />
                <div v-if="bulk.start_date" class="text-xs text-gray-500 mt-1">{{ formatDateDisplay(bulk.start_date) }}</div>
              </div>
              <div>
                <label class="block text-xs text-gray-500">Kraj</label>
                <input v-model="bulk.end_date" type="date" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition" />
                <div v-if="bulk.end_date" class="text-xs text-gray-500 mt-1">{{ formatDateDisplay(bulk.end_date) }}</div>
              </div>
              <div>
                <label class="block text-xs text-gray-500">Datum isporuke</label>
                <input v-model="form.delivery_date" type="date" class="form-input w-full mt-1 border-2 border-orange-300 bg-orange-50 dark:bg-gray-900 rounded focus:border-orange-400 focus:ring-2 focus:ring-orange-200 transition" />
                <div v-if="form.delivery_date" class="text-xs text-gray-500 mt-1">{{ formatDateDisplay(form.delivery_date) }}</div>
              </div>
            </div>
            <div class="mt-1" v-if="attempted && !valid.dates">
              <p class="text-xs text-red-600">Unesite ispravan raspon datuma (Početak ≤ Kraj).</p>
            </div>
          </div>

          <!-- Step 3: Nalozi -->
          <div class="border rounded p-3 mb-4">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-semibold">Odaberite 1 ili više naloga</h3>
              <label class="text-xs inline-flex items-center gap-2">
                <input type="checkbox" v-model="showAssigned" class="form-checkbox" />
                <span>Prikaži i već planirane (onemogućeno)</span>
              </label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
              <div class="md:col-span-1">
                <input v-model="bulk.q" type="text" placeholder="Pretraga po broju, opisu ili kupcu..." class="form-input w-full" />
                <div class="max-h-60 overflow-auto border rounded mt-2 divide-y divide-gray-100 dark:divide-gray-700 bg-gray-50 dark:bg-gray-900">
                  <div v-for="o in filteredOrders" :key="o.id"
                       class="flex items-start gap-2 p-2 text-sm cursor-pointer"
                       :class="rowClass(o)"
                       @click="toggleSelect(o)">
                    <input type="checkbox" :value="o.id" v-model="bulk.selected" class="mt-0.5"
                           :disabled="isUnavailable(o)" @click.stop />
                    <div class="min-w-0">
                      <div class="truncate"><span class="font-medium">{{ o.OrderNumber }}</span> — {{ o.Description }}</div>
                      <div class="text-xs text-gray-600 dark:text-gray-300 truncate">
                        <span v-if="o.partner">{{ o.partner.name }}</span>
                      </div>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                      <span v-if="form.objekat" class="text-[10px] px-2 py-0.5 rounded bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                        Iskorištenost: {{ (currentPercentMap[o.id] || 0) }}%
                      </span>
                      <span v-if="isUnavailable(o)" class="text-[10px] px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200">Već planiran</span>
                    </div>
                  </div>
                </div>
                <div class="text-xs text-gray-500 mt-1">Odabrano: {{ bulk.selected.length }}</div>
                <div class="mt-2 flex gap-2">
                  <button type="button" @click="clearBulk" class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 rounded">Očisti izbor</button>
                </div>
                <div class="mt-4 p-2 border rounded bg-white dark:bg-gray-800">
                  <div class="text-xs font-semibold mb-2">Privremeni nalog (nije još kreiran)</div>
                  <input v-model="custom.label" type="text" placeholder="Naziv privremenog naloga" class="form-input w-full mb-2" />
                  <div class="flex items-center gap-2">
                    <input type="number" min="1" max="100" step="1" v-model.number="custom.percent" class="w-20 text-xs border rounded px-1 py-0.5" placeholder="%" />
                    <button type="button" class="px-2 py-1 text-xs bg-blue-600 text-white rounded disabled:opacity-50" :disabled="!custom.label || !custom.percent" @click="addCustomItem">Dodaj</button>
                  </div>
                </div>
                <p v-if="attempted && !valid.orders" class="text-xs text-red-600 mt-2">Odaberite najmanje jedan nalog.</p>
              </div>
              <div class="md:col-span-2">
                <h4 class="text-xs font-semibold mb-1">Sažetak odabira</h4>
                <div v-if="selectedOrders.length || customItems.length" class="flex flex-wrap gap-2">
                  <span v-for="s in selectedOrders" :key="s.id" class="inline-flex items-center gap-2 text-xs px-2 py-1 rounded border border-gray-200 dark:border-gray-700">
                    <span class="font-medium">{{ s.OrderNumber }}</span>
                    <span class="text-gray-500 truncate max-w-[240px]">— {{ s.Description }}</span>
                    <span v-if="s.partner" class="text-gray-600 dark:text-gray-300">• {{ s.partner.name }}</span>
                    <input type="number" min="1" max="100" step="1"
                      v-model.number="bulk.percent[s.id]"
                      class="w-16 text-xs border rounded px-1 py-0.5 ml-2"
                      placeholder="%" />
                    <span class="text-xs text-gray-400 ml-1">%</span>
                    <button class="ml-1 text-gray-400 hover:text-red-600" @click.prevent="removeSelected(s.id)">✕</button>
                  </span>
                  <span v-for="c in customItems" :key="c.id" class="inline-flex items-center gap-2 text-xs px-2 py-1 rounded border border-amber-300 bg-amber-50 dark:bg-amber-900/20">
                    <span class="font-medium">{{ c.label }}</span>
                    <span class="text-xs text-gray-500">(privremeni)</span>
                    <input type="number" min="1" max="100" step="1" v-model.number="c.percent" class="w-16 text-xs border rounded px-1 py-0.5 ml-2" />
                    <span class="text-xs text-gray-400 ml-1">%</span>
                    <button class="ml-1 text-gray-400 hover:text-red-600" @click.prevent="removeCustomItem(c.id)">✕</button>
                  </span>
                </div>
                <div v-else class="text-xs text-gray-500">Još nema odabranih naloga.</div>
              </div>
            </div>
          </div>

          <!-- Step 4: Submit -->
          <div class="mt-4 flex justify-end">
            <button @click="submit" :disabled="!formReady" class="px-4 py-2 rounded text-white"
                    :class="formReady ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed'">
              Snimi plan
            </button>
          </div>
        </div>
        <!-- removed sidebar with Dostupni nalozi (posljednjih 200) -->
      </div>
    </div>
  </ProductionAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

function formatDateDisplay(val) {
  if (!val) return '';
  // Accepts ISO (yyyy-mm-dd) and returns dd.mm.yyyy
  const m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(val);
  if (m) return `${m[3]}.${m[2]}.${m[1]}`;
  return val;
}

const props = defineProps({
  orders: { type: Array, default: () => [] },
  plans: { type: Array, default: () => [] },
  objekti: { type: Array, default: () => [] },
  assignedByObjekat: { type: Object, default: () => ({}) },
  percentByObjekat: { type: Object, default: () => ({}) },
});

const orders = ref(props.orders || []);
const plans = ref(props.plans || []);
const objekti = ref(props.objekti || []);
const percentByObjekat = ref(props.percentByObjekat || {});

const form = ref({
  objekat: '',
  laboracija_datum: '',
  laboracija_smjene: [], // Dodano za smjene
  delivery_date: '',
});

const requiresLaboracija = computed(() => {
  const o = (form.value.objekat || '').toLowerCase();
  return o === 'kompletiranje' || o === 'kompletiranje nonel';
});

const bulk = ref({ start_date: '', end_date: '', selected: [], q: '', percent: {}, delivery: {} });
const custom = ref({ label: '', percent: 100 });
const customItems = ref([]); // {id,label,percent}
const showAssigned = ref(false);
const attempted = ref(false);
const unavailableSet = computed(() => {
  const key = form.value.objekat || '';
  const percentMap = percentByObjekat.value && percentByObjekat.value[key] ? percentByObjekat.value[key] : {};
  // Nalog je nedostupan ako je zbir percent >= 100
  return new Set(Object.entries(percentMap).filter(([id, percent]) => percent >= 100).map(([id]) => Number(id)));
});

// Mapa iskorištenosti za trenutno izabrani objekat
const currentPercentMap = computed(() => {
  const key = form.value.objekat || '';
  const map = percentByObjekat.value && percentByObjekat.value[key] ? percentByObjekat.value[key] : {};
  return map || {};
});

function isUnavailable(o) {
  return unavailableSet.value.has(Number(o?.id));
}

const filteredOrders = computed(() => {
  const q = (bulk.value.q || '').toLowerCase();
  let base = orders.value;
  if (q) {
    base = base.filter(o =>
      String(o.OrderNumber).toLowerCase().includes(q) ||
      String(o.Description || '').toLowerCase().includes(q) ||
      String(o.partner?.name || '').toLowerCase().includes(q)
    );
  }
  if (form.value.objekat && !showAssigned.value) {
    // hide orders already assigned in the chosen objekat unless user opts to see them
    base = base.filter(o => !unavailableSet.value.has(Number(o.id)));
  }
  return base;
});

import { watch } from 'vue';
watch(() => form.value.objekat, () => {
  // When objekat changes, drop any selected IDs that are unavailable
  const set = unavailableSet.value;
  bulk.value.selected = (bulk.value.selected || []).filter((id) => !set.has(Number(id)));
});

function rowClass(o) {
  const selected = bulk.value.selected.includes(o.id);
  const disabled = isUnavailable(o);
  return [
    selected ? 'bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-500' : 'hover:bg-gray-100 dark:hover:bg-gray-700',
    disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'
  ].join(' ');
}

function toggleSelect(o) {
  if (isUnavailable(o)) return;
  const id = o.id;
  const idx = bulk.value.selected.indexOf(id);
  if (idx >= 0) {
    bulk.value.selected.splice(idx, 1);
    // Ukloni percent samo ako nije ručno promijenjen
    // delete bulk.value.percent[id];
  } else {
    bulk.value.selected.push(id);
    if (typeof bulk.value.percent[id] === 'undefined') {
      bulk.value.percent[id] = 100;
    }
  }
}

function removeSelected(id) {
  bulk.value.selected = (bulk.value.selected || []).filter((x) => x !== id);
}

function addCustomItem() {
  const label = (custom.value.label || '').trim();
  const percent = Number(custom.value.percent) || 100;
  if (!label || percent <= 0 || percent > 100) return;
  const id = Date.now() + Math.random();
  customItems.value.push({ id, label, percent });
  custom.value = { label: '', percent: 100 };
}

function removeCustomItem(id) {
  customItems.value = customItems.value.filter(c => c.id !== id);
}

const selectedOrders = computed(() => {
  const set = new Set(bulk.value.selected);
  return orders.value.filter((o) => set.has(o.id));
});

const valid = computed(() => {
  const hasObj = !!form.value.objekat;
  const labOk = !requiresLaboracija.value ||
    (!!form.value.laboracija_datum && (form.value.laboracija_smjene || []).length > 0);
  const hasDates = !!bulk.value.start_date && !!bulk.value.end_date && (bulk.value.start_date <= bulk.value.end_date);
  const hasOrders = (bulk.value.selected || []).length > 0;
  const hasAny = hasOrders || (customItems.value.length > 0);
  const percentOk = (bulk.value.selected || []).every(id => {
    const p = Number(bulk.value.percent[id]);
    return !isNaN(p) && p > 0 && p <= 100;
  });
  const customPercentOk = customItems.value.every(c => c.percent > 0 && c.percent <= 100);
  const deliveryOk = !!form.value.delivery_date;
  return { objekat: hasObj, laboracija: labOk, dates: hasDates, orders: hasAny && percentOk && customPercentOk, delivery: deliveryOk };
});

const formReady = computed(() => valid.value.objekat && valid.value.laboracija && valid.value.dates && valid.value.orders && valid.value.delivery);

function presetThisWeek() {
  const now = new Date();
  const day = now.getDay(); // 0 Sun .. 6 Sat
  const monday = new Date(now);
  monday.setDate(now.getDate() - ((day + 6) % 7));
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);
  bulk.value.start_date = monday.toISOString().slice(0,10);
  bulk.value.end_date = sunday.toISOString().slice(0,10);
}

function presetNextWeek() {
  presetThisWeek();
  const s = new Date(bulk.value.start_date); s.setDate(s.getDate() + 7);
  const e = new Date(bulk.value.end_date); e.setDate(e.getDate() + 7);
  bulk.value.start_date = s.toISOString().slice(0,10);
  bulk.value.end_date = e.toISOString().slice(0,10);
}

function presetThisMonth() {
  const now = new Date();
  const s = new Date(now.getFullYear(), now.getMonth(), 1);
  const e = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  bulk.value.start_date = s.toISOString().slice(0,10);
  bulk.value.end_date = e.toISOString().slice(0,10);
}

function clearBulk() {
  bulk.value.start_date = '';
  bulk.value.end_date = '';
  bulk.value.selected = [];
  bulk.value.q = '';
  bulk.value.percent = {};
  custom.value = { label: '', percent: 100 };
  customItems.value = [];
}

async function submit() {
  try {
    attempted.value = true;
    if (!formReady.value) return;

    const payload = {
      objekat: form.value.objekat,
      laboracija_datum: form.value.laboracija_datum || null,
      laboracija_smjene: form.value.laboracija_smjene || [],
      delivery_date: form.value.delivery_date,
      items: [
        ...bulk.value.selected.map((id) => ({
          order_id: Number(id),
          start_date: bulk.value.start_date,
          end_date: bulk.value.end_date,
          percent: Number(bulk.value.percent[id]) || 100,
        })),
        ...customItems.value.map(c => ({
          placeholder_label: c.label,
          start_date: bulk.value.start_date,
          end_date: bulk.value.end_date,
          percent: Number(c.percent) || 100,
        })),
      ],
    };
    await axios.post('/planiranje/proizvodnja', payload);
    alert('Plan je snimljen');
    // Automatski osvježi podatke bez reload-a
    // Povuci nove podatke sa servera
    const resp = await axios.get(window.location.pathname);
    if (resp && resp.data && resp.data.props) {
      // Ažuriraj orders i percentByObjekat
      orders.value = resp.data.props.orders || [];
      plans.value = resp.data.props.plans || [];
      percentByObjekat.value = resp.data.props.percentByObjekat || {};
      // Očisti izbor
      clearBulk();
      attempted.value = false;
    } else {
      window.location.reload(); // fallback
    }
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
