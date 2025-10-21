<template>
  <ProductionAppLayout :title="`Nalog ${order?.OrderNumber || ''}`">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Detalji naloga — {{ order?.OrderNumber }}</h2>
        <button type="button" @click="goBack" class="text-sm text-blue-600 hover:underline">← Nazad</button>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
        <div v-if="order" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <!-- Grid: Left = Podaci o nalogu | Right = Proizvodi i količine -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Left column: Podaci o nalogu -->
              <div class="space-y-4">
                <SectionCard title="Podaci o nalogu">
                  <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <Detail label="Broj" :value="order?.OrderNumber" />
                    <Detail label="Datum" :value="formatDateOnly(order?.OrderDate)" />
                    <Detail label="Status" :value="order?.Status" />
                    <Detail label="Partner" :value="order?.partner?.name" />
                    <Detail label="Kreirao" :value="order?.creator?.name" />
                    <Detail label="Rok isporuke" :value="order?.RokIsporuke" />
                    <Detail label="Datum predaje" :value="formatDateOnly(order?.DatumPredaje)" />
                    <Detail label="Datum prijema" :value="formatDateOnly(order?.DatumPrijema)" />

                    <Detail label="Vrsta provodnika" :value="order?.VrstaProvodnika" />
                    <Detail label="Tip" :value="order?.Tip" />
                    <Detail label="Boja/Dužina provodnika" :value="order?.BojaDuzinaProvodnika" :full="true" />
                    <Detail label="Pakovanje" :value="order?.Pakovanje" />
                    <Detail label="Metraža" :value="order?.Metraza" />

                    <Detail label="CE oznaka" :value="order?.CeOznaka" />
                    <Detail label="Atest paketa" :value="order?.AtestPaketa" />
                    <Detail label="Klasa opasnosti" :value="order?.KlasaOpasnosti" />
                    <Detail label="UN broj" :value="order?.UNBroj" />

                    <Detail label="Napomena" :value="order?.Napomena" :full="true" />
                    <Detail label="Opis" :value="order?.Description" :full="true" />
                  </dl>
                </SectionCard>

                <SectionCard title="Odobrenja (timeline)">
                  <div class="space-y-3">
                    <div v-for="a in (order?.approvals || [])" :key="a.id" class="flex gap-3 items-start">
                      <div class="mt-1 w-2 h-2 rounded-full" :class="a.Odobreno === true ? 'bg-green-500' : (a.Odobreno === false ? 'bg-red-500' : 'bg-gray-400')"></div>
                      <div class="text-sm">
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ a.Funkcija }}
                          <span v-if="a.signed_by_proxy" class="ml-2 inline-flex items-center px-2 py-0.5 rounded bg-amber-500 text-white text-xs">proxy</span>
                        </div>
                        <div class="text-gray-700 dark:text-gray-300">
                          <span v-if="a.Odobreno === true">Odobreno</span>
                          <span v-else-if="a.Odobreno === false">Odbijeno</span>
                          <span v-else>Čeka</span>
                          <span v-if="a.DatumOdobravanja" class="ml-2 text-xs text-gray-500">({{ a.DatumOdobravanja }})</span>
                          <span v-if="a.user?.name" class="ml-2 text-xs text-gray-500">— {{ a.user.name }}</span>
                        </div>
                        <div v-if="a.Komentar" class="text-xs text-gray-600 dark:text-gray-300">Komentar: {{ a.Komentar }}</div>
                      </div>
                    </div>
                    <div v-if="(order?.approvals || []).length === 0" class="text-sm text-gray-500 dark:text-gray-400">Nema zapisa o odobrenjima.</div>
                  </div>
                </SectionCard>
              </div>

              <!-- Right column: Proizvodi i količine -->
              <div>
                <SectionCard title="Proizvodi i količine">
                  <div class="flex items-center justify-between mb-3 text-sm text-gray-600 dark:text-gray-300">
                    <span>Ukupno stavki: {{ (order?.details || []).length }}</span>
                  </div>
                  <div class="space-y-3">
                    <div v-for="(d, idx) in order?.details || []" :key="d.id" class="border border-gray-200 dark:border-gray-700 rounded-md p-3">
                      <div class="flex items-start justify-between gap-3">
                        <div>
                          <div class="font-medium text-gray-900 dark:text-gray-100">{{ (idx + 1) + '. ' + (d.product?.Naziv || d.product?.SkraceniNaziv || '—') }}</div>
                          <div class="text-xs text-gray-500 dark:text-gray-400">Šifra: {{ d.product?.SkraceniNaziv || '—' }}</div>
                          <div class="text-xs text-gray-500 dark:text-gray-400" v-if="d.product?.JedinicaMjere">JM: {{ d.product?.JedinicaMjere }}</div>
                        </div>
                        <div class="shrink-0">
                          <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-600 text-white text-sm font-semibold">{{ d.quantity }}</span>
                        </div>
                      </div>
                      <div v-if="d.note" class="mt-2 text-xs text-gray-600 dark:text-gray-300">Napomena: {{ d.note }}</div>
                    </div>
                    <div v-if="(order?.details || []).length === 0" class="text-sm text-gray-500 dark:text-gray-400">Nema stavki.</div>
                  </div>
                </SectionCard>
              </div>
            </div>

          </div>
        </div>
        <div v-else class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-700 dark:text-gray-300">Podaci naloga nisu pronađeni.</div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

const props = defineProps({
  order: { type: Object, default: null }
});

const localOrder = ref(props.order);
const order = computed(() => localOrder.value);

function formatDateOnly(dt) {
  if (!dt) return '';
  try {
    const d = new Date(dt);
    return d.toLocaleDateString();
  } catch { return dt; }
}

onMounted(async () => {
  if (!localOrder.value) {
    // try to parse id from URL and fetch
    const match = window.location.pathname.match(/\/productionorders\/(\d+)/);
    if (match) {
      const id = match[1];
      try {
        const res = await fetch(`/api/productionorders/${id}`);
        if (res.ok) {
          const json = await res.json();
          localOrder.value = json.order || null;
        }
      } catch (e) {
        console.error('Greška pri učitavanju detalja naloga', e);
      }
    }
  }
});

function goBack() {
  // Preferred: go back in history if possible
  if (window.history.length > 1) {
    window.history.back();
    return;
  }
  // Next: use referrer if it exists and is same-origin
  const ref = document.referrer || '';
  try {
    const url = new URL(ref);
    if (url.origin === window.location.origin) {
      window.location.href = ref;
      return;
    }
  } catch {}
  // Fallback: po zahtjevu vraćamo na Kreirane naloge
  window.location.href = '/nalozi/kreirani';
}

// Local render-function components to avoid runtime template compilation
const SectionCard = defineComponent({
  name: 'SectionCard',
  props: { title: { type: String, default: '' } },
  setup(props, { slots }) {
    return () => h('div', { class: 'rounded-lg border border-gray-200 dark:border-gray-700' }, [
      h(
        'div',
        { class: 'px-4 py-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-medium' },
        props.title
      ),
      h('div', { class: 'p-4' }, slots.default ? slots.default() : null),
    ]);
  },
});

const Detail = defineComponent({
  name: 'Detail',
  props: {
    label: { type: String, default: '' },
    value: { type: [String, Number, Boolean, Object, null], default: null },
    full: { type: Boolean, default: false },
  },
  setup(props) {
    const formatValue = (v) => (v === undefined || v === null || v === '' ? '—' : v);
    return () =>
      h(
        'div',
        { class: props.full ? 'sm:col-span-2' : '' },
        [
          h('dt', { class: 'text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400' }, props.label),
          h('dd', { class: 'text-gray-800 dark:text-gray-200' }, formatValue(props.value)),
        ]
      );
  },
});
</script>

<style scoped>
</style>
