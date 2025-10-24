<template>
  <ProductionAppLayout title="Gantt plan proizvodnje">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Gantt plan proizvodnje</h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <!-- Filters -->
  <div class="bg-white dark:bg-gray-800 p-4 rounded shadow grid grid-cols-1 md:grid-cols-8 gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500">Od</label>
            <input v-model="filters.from" type="date" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Do</label>
            <input v-model="filters.to" type="date" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs text-gray-500">Objekat</label>
            <select v-model="filters.objekat" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition">
              <option value="">Svi objekti</option>
              <option v-for="o in objekti" :key="o" :value="o">{{ o }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500">Grupisanje</label>
            <select v-model="filters.groupBy" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition">
              <option value="none">Bez grupisanja</option>
              <option value="order">Po nalogu</option>
              <option value="objekat">Po objektu</option>
              <option value="objekat_shift">Po objektu (smjene)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500">Zoom</label>
            <select v-model="filters.zoom" class="form-input w-full mt-1 border-2 border-blue-200 bg-blue-50 dark:bg-gray-900 rounded focus:border-blue-400 focus:ring-2 focus:ring-blue-200 transition">
              <option value="day">Dnevno</option>
              <option value="week">Sedmično</option>
              <option value="month">Mjesečno</option>
            </select>
          </div>

          <div class="flex gap-2 md:col-span-2">
            <button class="px-4 py-2 bg-gray-800 text-white rounded" @click="reload">Primijeni</button>
            <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded" @click="resetRange">Očisti</button>
            <button class="px-4 py-2 bg-blue-600 text-white rounded" @click="openInsert">Umetni</button>
          </div>
        </div>

        <!-- Gantt Chart -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
          <div class="flex">
            <!-- Left fixed labels -->
            <div class="shrink-0 flex flex-col" style="width: 280px;">
              <div class="h-6 font-semibold text-gray-700 dark:text-gray-200">{{ leftHeaderLabel }}</div>
              <div class="h-px bg-gray-200 dark:bg-gray-700 my-2" />
              <div class="flex flex-col">
                <!-- Empty header row for alignment with Gantt grid header -->
                <div class="h-6"></div>
                <div v-for="row in groupedRows" :key="'l-' + row.key" class="pr-2">
                  <template v-if="row.type==='header'">
                    <div class="h-6 py-1 text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center">{{ row.label }}</div>
                  </template>
                  <template v-else>
                    <div class="h-8 flex items-center text-sm whitespace-nowrap">
                      <div class="font-medium truncate max-w-[260px]">{{ row.label }}</div>
                      <div v-if="row.sub" class="text-xs text-gray-500 truncate max-w-[260px] ml-2">{{ row.sub }}</div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
            <!-- Right scrollable timeline -->
            <div class="relative grow overflow-x-auto flex flex-col" ref="scrollXRef">
              <div class="min-w-[900px] relative flex flex-col">
                <!-- Header units -->
                <div class="grid" :style="{ gridTemplateColumns: rightColumns }">
                  <!-- Two-tier header for day zoom: top week labels spanning 7 days, bottom day numbers -->
                  <template v-if="filters.zoom==='day'">
                    <div v-for="w in weekGroups" :key="'w-'+w.key"
                         class="h-5 text-[11px] text-center font-medium text-gray-700 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 flex items-center justify-center"
                         :style="{ gridColumn: (w.start + 1) + ' / span ' + w.span }">
                      {{ w.label }}
                    </div>
                    <div v-for="u in units" :key="'d-'+u.key" :class="dayHeaderClass(u)">
                      <span class="unit-label-inline">{{ String(u.start.getDate()).padStart(2,'0') }}</span>
                    </div>
                  </template>
                  <template v-else>
                    <div v-for="u in units" :key="u.key" :class="headerUnitClass(u)">
                      <template v-if="filters.dateStyle==='vertical'">
                        <span class="unit-label-vertical">{{ headerFormattedLabel(u) }}</span>
                      </template>
                      <template v-else-if="filters.dateStyle==='stack'">
                        <span class="unit-day">{{ headerLabelParts(u).day }}</span>
                        <span class="unit-month">{{ headerLabelParts(u).month }}</span>
                      </template>
                      <template v-else>
                        <span class="unit-label-inline">{{ u.label }}</span>
                      </template>
                    </div>
                  </template>
                </div>
                <div class="h-px bg-gray-200 dark:bg-gray-700 my-2" />
                <!-- Rows timeline -->
                <div v-for="row in groupedRows" :key="'r-' + row.key" class="relative">
                  <template v-if="row.type==='header'">
                    <div class="grid" :style="{ gridTemplateColumns: rightColumns }">
                      <div v-for="u in units" :key="row.key + ':' + u.key" class="h-6 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50"></div>
                    </div>
                  </template>
                  <template v-else>
                    <div class="grid" :style="{ gridTemplateColumns: rightColumns }">
                      <div
                        v-for="u in units"
                        :key="row.key + ':' + u.key"
                        :class="cellClass(row, u)"
                      ></div>
                    </div>
                    <!-- bars, positioned within the right pane -->
                    <div v-for="bar in row.bars" :key="bar.key" class="absolute h-6 rounded shadow-sm cursor-pointer"
                         :style="barStyle(bar)"
                         @mouseenter="showTooltip($event, bar)"
                         @mouseleave="hideTooltip"
                         @click="openOrder(bar)">
                      <span class="text-[10px] text-white px-2 leading-6 select-none">
                        <template v-if="bar.order?.OrderNumber">
                          {{ bar.order?.OrderNumber }}
                        </template>
                        <template v-else-if="bar.placeholder_label">
                          {{ bar.placeholder_label }}
                        </template>
                        <template v-if="bar.order?.partner">
                          • {{ partnerShort(bar.order.partner) }}
                        </template>
                        <template v-if="bar.percent ?? bar.item?.percent ?? bar.plan?.percent">
                          — {{ bar.percent ?? bar.item?.percent ?? bar.plan?.percent }}%
                        </template>
                      </span>
                    </div>
                  </template>
                </div>
                <!-- Tooltip -->
                <div v-if="tooltip.visible" class="pointer-events-none fixed z-50 text-xs bg-black/80 text-white px-2 py-1 rounded"
                     :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }">
                  <div class="font-semibold">{{ tooltip.order?.OrderNumber }} — {{ tooltip.order?.Description }}</div>
                  <div v-if="tooltip.order?.partner">{{ tooltip.order.partner.name }}</div>
                  <div v-if="!tooltip.order && tooltip.bar?.placeholder_label">Privremeni nalog: {{ tooltip.bar.placeholder_label }}</div>
                  <div>{{ tooltip.objekat }}</div>
                  <div>{{ tooltip.start }} → {{ tooltip.end }}</div>
                  <div v-if="tooltip.bar?.percent ?? tooltip.bar?.item?.percent ?? tooltip.bar?.plan?.percent">
                    <span>Planirano: {{ tooltip.bar?.percent ?? tooltip.bar?.item?.percent ?? tooltip.bar?.plan?.percent }}%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Legend -->
        <div class="text-xs text-gray-500 flex flex-wrap gap-4 items-center">
          <div class="font-semibold">Legenda:</div>
          <div v-for="e in legendEntries" :key="e.label" class="flex items-center gap-2">
            <span class="inline-block w-3 h-3 rounded" :style="{ backgroundColor: e.color }"></span>
            <span>{{ e.label }}</span>
          </div>
        </div>
      </div>
      <!-- Insert modal -->
      <div v-if="showInsert" class="modal-backdrop">
        <div class="modal-card p-4">
          <div class="text-lg font-semibold mb-4">Umetni projekat u plan</div>
          <div class="grid grid-cols-1 gap-3">
            <div>
              <label class="block text-xs text-gray-500">Objekat</label>
              <select v-model="insertForm.objekat" class="form-input w-full mt-1">
                <option value="" disabled>-- odaberite --</option>
                <option v-for="o in objekti" :key="o" :value="o">{{ o }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500">Nalog</label>
              <select v-model="insertForm.order_id" class="form-input w-full mt-1">
                <option value="" disabled>-- odaberite --</option>
                <option v-for="o in (props.availableOrders || [])" :key="o.id" :value="o.id">{{ o.OrderNumber }} — {{ o.Description }}</option>
              </select>
            </div>
            <div v-if="insertForm.objekat==='Kompletiranje' || insertForm.objekat==='Kompletiranje Nonel'">
              <label class="block text-xs text-gray-500">Datum laboracije</label>
              <input v-model="insertForm.laboracija_datum" type="date" class="form-input w-full mt-1" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-500">Početak</label>
                <input v-model="insertForm.start_date" type="date" class="form-input w-full mt-1" />
              </div>
              <div>
                <label class="block text-xs text-gray-500">Trajanje (radni dani)</label>
                <input v-model.number="insertForm.duration_days" type="number" min="1" class="form-input w-full mt-1" />
              </div>
            </div>
            <label class="inline-flex items-center gap-2 mt-1">
              <input type="checkbox" v-model="insertForm.move_others" class="form-checkbox" />
              <span class="text-sm">Pomjeri ostale projekte</span>
            </label>
            <div v-if="insertForm.order_id && insertForm.objekat && isDuplicateSelection" class="text-xs text-amber-600">
              Ovaj nalog je već planiran u izabranom objektu.
            </div>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded" @click="closeInsert">Otkaži</button>
            <button class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50" :disabled="isDuplicateSelection" @click="submitInsert">Umetni</button>
          </div>
        </div>
      </div>

      <!-- Link placeholder to order modal -->
      <div v-if="showLink" class="modal-backdrop">
        <div class="modal-card p-4">
          <div class="text-lg font-semibold mb-4">Veži privremeni nalog</div>
          <div class="grid grid-cols-1 gap-3">
            <div class="text-xs text-gray-500">
              <div><span class="font-semibold">Privremeni:</span> {{ linkContext.placeholder_label || '—' }}</div>
              <div><span class="font-semibold">Objekat:</span> {{ linkContext.objekat || '—' }}</div>
              <div><span class="font-semibold">Raspon:</span> {{ linkContext.start }} → {{ linkContext.end }}</div>
              <div v-if="linkContext.percent"><span class="font-semibold">Planirano:</span> {{ linkContext.percent }}%</div>
            </div>
            <div>
              <label class="block text-xs text-gray-500">Poveži na nalog</label>
              <select v-model="linkForm.order_id" class="form-input w-full mt-1">
                <option value="" disabled>-- odaberite --</option>
                <option v-for="o in (props.availableOrders || [])" :key="o.id" :value="o.id">{{ o.OrderNumber }} — {{ o.Description }}</option>
              </select>
            </div>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded" @click="closeLink">Otkaži</button>
            <button class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50" :disabled="!linkForm.order_id" @click="submitLink">Veži</button>
          </div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

const props = defineProps({
  items: { type: Array, default: () => [] },
  from: { type: String, default: '' },
  to: { type: String, default: '' },
  objekti: { type: Array, default: () => [] },
  selectedObjekat: { type: String, default: '' },
  groupBy: { type: String, default: 'none' },
  zoom: { type: String, default: 'day' },
  holidays: { type: Array, default: () => [] },
  availableOrders: { type: Array, default: () => [] },
});

const objekti = ref(props.objekti || []);
const filters = ref({
  from: props.from || '',
  to: props.to || '',
  objekat: props.selectedObjekat || '',
  groupBy: props.groupBy || 'objekat_shift',
  zoom: props.zoom || 'day', // 'day' | 'week' | 'month'
  dateStyle: 'vertical', // 'stack' | 'vertical' | 'inline'
});

// Basic guard: if dates invalid, default to current month
if (!filters.value.from || !filters.value.to) {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  filters.value.from = start.toISOString().slice(0, 10);
  filters.value.to = end.toISOString().slice(0, 10);
}

// Color palette for objekat
const palette = ['#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0ea5e9', '#16a34a', '#ea580c', '#db2777', '#22c55e'];
function hashString(s) { let h=0; for (let i=0;i<s.length;i++) { h = ((h<<5)-h) + s.charCodeAt(i); h|=0; } return Math.abs(h); }
function colorFor(objekat) {
  if (!objekat) return '#6b7280';
  const idx = hashString(String(objekat)) % palette.length;
  return palette[idx];
}

function parseObjekatLabel(o) {
  const str = String(o || '').trim();
  // Generic pattern: <Base> I|II smjena  (covers Laboracija, Laboracija Automatika, etc.)
  const m = str.match(/^(.*?)(?:\s+(I|II)\s+smjena)$/i);
  if (m) {
    const base = m[1].trim().replace(/\s+/g, ' ');
    const roman = (m[2] || '').toUpperCase();
    return { base, shift: `${roman} smjena` };
  }
  // Example: Kompletiranje Nonel -> base Kompletiranje, shift Nonel
  if (/^kompletiranje\b/i.test(str)) {
    const rest = str.substring('Kompletiranje'.length).trim();
    return { base: 'Kompletiranje', shift: rest };
  }
  return { base: str, shift: '' };
}

function adjustColor(hex, amount) {
  // hex #rrggbb, amount in [-30..30]
  try {
    const h = hex.replace('#','');
    const r = clamp(parseInt(h.substring(0,2),16) + amount, 0, 255);
    const g = clamp(parseInt(h.substring(2,4),16) + amount, 0, 255);
    const b = clamp(parseInt(h.substring(4,6),16) + amount, 0, 255);
    const toHex = (n) => n.toString(16).padStart(2,'0');
    return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
  } catch { return hex; }
}

function colorForBar(bar) {
  const { base, shift } = parseObjekatLabel(bar.objekat);
  const baseColor = colorFor(base);
  if (String(base).toLowerCase().startsWith('laboracija')) {
    if (shift === 'I smjena') return adjustColor(baseColor, 20);
    if (shift === 'II smjena') return adjustColor(baseColor, -10);
  }
  return baseColor;
}

const baseItems = computed(() => (props.items || []).filter(i => !filters.value.objekat || i.objekat === filters.value.objekat));

const groupedRows = computed(() => {
  const gb = filters.value.groupBy;
  if (gb === 'order') {
    const map = new Map();
    for (const i of baseItems.value) {
      const key = i.order?.id || `no-${i.id}`;
      if (!map.has(key)) map.set(key, { key: `order-${key}`, label: i.order?.OrderNumber || '-', sub: i.order?.Description || '', bars: [] });
      map.get(key).bars.push({ key: `bar-${i.id}`, item_id: i.id, start: i.start_date, end: i.end_date, objekat: i.objekat, order: i.order, percent: i.percent, placeholder_label: i.placeholder_label });
    }
    return Array.from(map.values());
  }
  if (gb === 'objekat') {
    const map = new Map();
    for (const i of baseItems.value) {
      const key = i.objekat || '—';
      if (!map.has(key)) map.set(key, { key: `obj-${key}`, label: key, sub: '', bars: [] });
      map.get(key).bars.push({ key: `bar-${i.id}`, item_id: i.id, start: i.start_date, end: i.end_date, objekat: i.objekat, order: i.order, percent: i.percent, placeholder_label: i.placeholder_label });
    }
    return Array.from(map.values());
  }
  if (gb === 'objekat_shift') {
    // Group by base objekat, with sub-rows for shifts; then sort children globally by required order
    const groups = new Map();
    for (const i of baseItems.value) {
      const parsed = parseObjekatLabel(i.objekat);
      const base = parsed.base;
      if (!groups.has(base)) groups.set(base, { key: `g-${base}`, label: base, children: [] });
      const group = groups.get(base);
      const childKey = parsed.shift || base;
      let child = group.children.find(c => c.key === `${group.key}-${childKey}`);
      if (!child) {
        const displayLabel = parsed.shift ? `${base} ${parsed.shift}` : base;
        child = { key: `${group.key}-${childKey}`, type: 'row', label: displayLabel, sub: '', bars: [] };
        group.children.push(child);
      }
      child.bars.push({ key: `bar-${i.id}`, item_id: i.id, start: i.start_date, end: i.end_date, objekat: i.objekat, order: i.order, percent: i.percent, placeholder_label: i.placeholder_label });
    }
    const rows = groups.size ? Array.from(groups.values()).flatMap(g => g.children) : [];
    // custom order: Laboracija I smjena, Laboracija II smjena, Laboracija Automatika I smjena, Laboracija Automatika II smjena, Kompletiranje, Kompletiranje Nonel, then others alpha
    const rank = (label) => {
      const l = String(label).toLowerCase();
      if (l === 'laboracija i smjena') return 1;
      if (l === 'laboracija ii smjena') return 2;
      if (l === 'laboracija automatika i smjena') return 3;
      if (l === 'laboracija automatika ii smjena') return 4;
      if (l === 'kompletiranje') return 5;
      if (l === 'kompletiranje nonel') return 6;
      return 100;
    };
    rows.sort((a,b) => {
      const ra = rank(a.label), rb = rank(b.label);
      if (ra !== rb) return ra - rb;
      return String(a.label).localeCompare(String(b.label));
    });
    return rows;
  }
  // none: one row per item
  return baseItems.value.map(i => ({
    key: `item-${i.id}`,
    label: i.order?.OrderNumber || '-',
    sub: i.order?.Description || i.objekat || '',
    bars: [{ key: `bar-${i.id}`, item_id: i.id, start: i.start_date, end: i.end_date, objekat: i.objekat, order: i.order, percent: i.percent, placeholder_label: i.placeholder_label }]
  }));
});

function parseDate(str) {
  const d = new Date(str);
  if (isNaN(d)) return null;
  return d;
}
const rangeStart = computed(() => parseDate(filters.value.from));
const rangeEnd = computed(() => parseDate(filters.value.to));
const totalDays = computed(() => {
  const s = rangeStart.value, e = rangeEnd.value;
  if (!s || !e || e < s) return 0;
  const dayMs = 86400000;
  return Math.floor((e - s) / dayMs) + 1;
});

const units = computed(() => {
  const s = rangeStart.value, e = rangeEnd.value;
  if (!s || !e || e < s) return [];
  const zoom = filters.value.zoom;
  const out = [];
  if (zoom === 'day') {
    const cur = new Date(s);
    while (cur <= e) {
      const key = cur.toISOString().slice(0, 10);
      const label = `${cur.getDate().toString().padStart(2, '0')}.${(cur.getMonth()+1).toString().padStart(2,'0')}`;
      out.push({ key, label, start: new Date(cur), end: new Date(cur) });
      cur.setDate(cur.getDate() + 1);
    }
  } else if (zoom === 'week') {
    const cur = new Date(s);
    // align to Monday
    const day = cur.getDay(); // 0=Sun..6=Sat
    const deltaToMon = (day + 6) % 7; // Sun->6, Mon->0
    cur.setDate(cur.getDate() - deltaToMon);
    while (cur <= e) {
      const start = new Date(cur);
      const end = new Date(cur); end.setDate(end.getDate() + 6);
      const k = start.toISOString().slice(0, 10);
      const label = `${start.getDate().toString().padStart(2,'0')}.${(start.getMonth()+1).toString().padStart(2,'0')}`;
      out.push({ key: `w-${k}`, label, start, end });
      cur.setDate(cur.getDate() + 7);
    }
  } else { // month
    const cur = new Date(s.getFullYear(), s.getMonth(), 1);
    while (cur <= e) {
      const start = new Date(cur);
      const end = new Date(cur.getFullYear(), cur.getMonth() + 1, 0);
      const key = `${start.getFullYear()}-${String(start.getMonth()+1).padStart(2,'0')}`;
      const label = `${String(start.getMonth()+1).padStart(2,'0')}.${String(start.getFullYear())}`;
      out.push({ key: `m-${key}`, label, start, end });
      cur.setMonth(cur.getMonth() + 1);
    }
  }
  return out;
});

const rightColumns = computed(() => `repeat(${units.value.length}, 32px)`);
const pxPerDay = computed(() => {
  if (!totalDays.value) return 0;
  return (units.value.length * 32) / totalDays.value;
});

function clamp(val, min, max) { return Math.max(min, Math.min(max, val)); }

function barStyle(bar) {
  const start = parseDate(bar.start);
  const end = parseDate(bar.end);
  const first = rangeStart.value;
  const last = rangeEnd.value;
  const pxd = pxPerDay.value;
  if (!start || !end || !first || !last || !pxd) return {};
  const dayMs = 86400000;
  const cStart = start < first ? first : start;
  const cEnd = end > last ? last : end;
  const offsetDays = Math.floor((cStart - first) / dayMs);
  const spanDays = Math.floor((cEnd - cStart) / dayMs) + 1;
  const leftPx = offsetDays * pxd; // relative to right scrollable pane
  const widthPx = clamp(spanDays * pxd, 0, 99999);
  const baseColor = colorForBar(bar);
  const bg = isDuplicateBar(bar) ? '#f59e0b' : baseColor; // amber for duplicates in same objekat
  return { left: leftPx + 'px', width: widthPx + 'px', top: '6px', backgroundColor: bg, color: '#fff' };
}

// Free-slot highlighting per unit (by objekat views)
const holidaySet = computed(() => new Set((props.holidays || []).map(String)));
function isHoliday(unit) {
  if (!unit?.start) return false;
  const key = unit.start.toISOString().slice(0,10);
  return holidaySet.value.has(key);
}
function isSunday(unit) {
  if (!unit?.start) return false;
  return unit.start.getDay() === 0;
}
function isNonWorking(unit) {
  return isSunday(unit) || isHoliday(unit);
}
function isOverlap(aStart, aEnd, bStart, bEnd) {
  return aStart <= bEnd && bStart <= aEnd;
}
function isUnitCovered(row, unit) {
  const uStart = unit.start;
  const uEnd = unit.end;
  if (!uStart || !uEnd || !row?.bars?.length) return false;
  for (const b of row.bars) {
    const bStart = parseDate(b.start);
    const bEnd = parseDate(b.end);
    if (!bStart || !bEnd) continue;
    if (isOverlap(bStart, bEnd, uStart, uEnd)) return true;
  }
  return false;
}
function cellClass(row, unit) {
  const base = 'h-8 border-t border-gray-100 dark:border-gray-700';
  // Mark holidays red, Sundays gray, and don't mark green for non-working days
  if (filters.value.zoom === 'day') {
    if (isHoliday(unit)) return base + ' bg-red-100 dark:bg-red-900/30';
    if (isSunday(unit)) return base + ' bg-gray-100 dark:bg-gray-800/50';
  }
  // Highlight only when grouping by objekat-based rows
  if (filters.value.groupBy === 'objekat' || filters.value.groupBy === 'objekat_shift') {
    return isUnitCovered(row, unit) ? base : base + ' bg-green-50 dark:bg-green-900/30';
  }
  return base;
}

function headerUnitClass(unit) {
  const style = filters.value.dateStyle;
  const baseCommon = 'text-[10px] flex items-center justify-center';
  const height = style === 'vertical' ? 'h-16' : (style === 'stack' ? 'h-10' : 'h-6');
  const base = `${height} ${baseCommon}`;
  if (filters.value.zoom === 'day') {
    if (isHoliday(unit)) return base + ' bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200';
    if (isSunday(unit)) return base + ' bg-gray-100 text-gray-600 dark:bg-gray-800/50 dark:text-gray-300';
  }
  return base + ' text-gray-500';
}

function headerLabelParts(unit) {
  const d = unit?.start instanceof Date ? unit.start : null;
  if (!d) return { day: unit?.label || '', month: '' };
  if (filters.value.zoom === 'day') {
    const day = String(d.getDate()).padStart(2,'0');
    const month = String(d.getMonth()+1).padStart(2,'0');
    return { day, month };
  }
  if (filters.value.zoom === 'week') {
    const day = String(d.getDate()).padStart(2,'0');
    const month = String(d.getMonth()+1).padStart(2,'0');
    return { day, month };
  }
  // month zoom
  const month = String(d.getMonth()+1).padStart(2,'0');
  const day = String(d.getFullYear()).slice(-2);
  return { day: month, month: `'${day}` };
}

function headerFormattedLabel(unit) {
  // Use dd.mm.yyyy format for clarity across zooms
  const d = unit?.start instanceof Date ? unit.start : null;
  if (!d) return unit?.label ?? '';
  const day = String(d.getDate()).padStart(2,'0');
  const month = String(d.getMonth()+1).padStart(2,'0');
  const year = String(d.getFullYear());
  return `${day}.${month}.${year}`;
}

// Week groups for day zoom (start on Sunday), for two-tier header
const weekGroups = computed(() => {
  if (filters.value.zoom !== 'day') return [];
  const arr = units.value || [];
  const groups = [];
  let idx = 0;
  while (idx < arr.length) {
    const u = arr[idx];
    const d = u.start instanceof Date ? new Date(u.start) : null;
    if (!d) break;
    // Compute Sunday start of this week
    const sunday = new Date(d);
    sunday.setDate(d.getDate() - d.getDay()); // getDay: 0=Sun
    // Span until Saturday or end of range
    const dow = d.getDay();
    const remainingInWeek = 7 - dow; // if dow=0 -> 7
    const span = Math.min(remainingInWeek || 7, arr.length - idx);
    groups.push({
      key: sunday.toISOString().slice(0,10) + ':' + idx,
      label: `${String(sunday.getDate()).padStart(2,'0')}.${String(sunday.getMonth()+1).padStart(2,'0')}.${sunday.getFullYear()}`,
      start: idx,
      span,
    });
    idx += span;
  }
  return groups;
});

function dayHeaderClass(unit) {
  const base = 'h-6 text-[11px] text-center flex items-center justify-center';
  if (filters.value.zoom === 'day') {
    if (isHoliday(unit)) return base + ' bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200';
    if (isSunday(unit)) return base + ' bg-gray-100 text-gray-600 dark:bg-gray-800/50 dark:text-gray-300';
  }
  return base + ' text-gray-600';
}

// Legend entries aligned to bar colors
const legendEntries = computed(() => {
  const map = new Map();
  for (const i of baseItems.value) {
    let label = i.objekat || '—';
    if (filters.value.groupBy === 'objekat_shift') {
      const { base, shift } = parseObjekatLabel(i.objekat);
      label = shift ? `${base} ${shift}` : base;
    }
    const color = colorForBar({ objekat: i.objekat });
    if (!map.has(label)) map.set(label, { label, color });
  }
  // stable order by label
  return Array.from(map.values()).sort((a,b) => a.label.localeCompare(b.label));
});

const leftHeaderLabel = computed(() => 'Objekti');

function resetRange() {
  router.visit(route('planning.gantt'));
}

function reload() {
  const q = new URLSearchParams();
  if (filters.value.from) q.set('from', filters.value.from);
  if (filters.value.to) q.set('to', filters.value.to);
  if (filters.value.objekat) q.set('objekat', filters.value.objekat);
  if (filters.value.groupBy && filters.value.groupBy !== 'none') q.set('groupBy', filters.value.groupBy);
  if (filters.value.zoom && filters.value.zoom !== 'day') q.set('zoom', filters.value.zoom);
  router.visit(route('planning.gantt') + (q.toString() ? ('?' + q.toString()) : ''));
}

// Tooltip & click handlers
const containerRef = ref(null);
const tooltip = ref({ visible: false, x: 0, y: 0, order: null, objekat: '', start: '', end: '' });
function showTooltip(e, bar) {
  tooltip.value.visible = true;
  tooltip.value.x = e.clientX + 12;
  tooltip.value.y = e.clientY + 12;
  tooltip.value.order = bar.order;
  tooltip.value.objekat = bar.objekat;
  tooltip.value.start = bar.start;
  tooltip.value.end = bar.end;
  tooltip.value.bar = bar;
}
function hideTooltip() { tooltip.value.visible = false; }
function openOrder(bar) {
  if (bar?.order?.id) {
    router.visit(route('productionorders.show', bar.order.id));
  } else if (bar?.placeholder_label && bar?.item_id) {
    openLink(bar);
  }
}

// ===== Insert modal (umetanje projekta) =====
const showInsert = ref(false);
const insertForm = ref({ objekat: '', order_id: '', start_date: filters.value.from, duration_days: 1, move_others: true, laboracija_datum: '' });
function openInsert() {
  insertForm.value.objekat = filters.value.objekat || '';
  insertForm.value.start_date = filters.value.from;
  showInsert.value = true;
}
function closeInsert() { showInsert.value = false; }
function submitInsert() {
  router.post(route('planning.insert'), insertForm.value, {
    preserveScroll: true,
    onSuccess: () => { showInsert.value = false; },
  });
}

// Link placeholder modal logic
const showLink = ref(false);
const linkForm = ref({ plan_item_id: '', order_id: '' });
const linkContext = ref({ placeholder_label: '', objekat: '', start: '', end: '', percent: null });
function openLink(bar) {
  linkForm.value.plan_item_id = bar.item_id;
  linkForm.value.order_id = '';
  linkContext.value = {
    placeholder_label: bar.placeholder_label || '',
    objekat: bar.objekat || '',
    start: bar.start || '',
    end: bar.end || '',
    percent: bar.percent ?? null,
  };
  showLink.value = true;
}
function closeLink() { showLink.value = false; }
function submitLink() {
  router.post(route('planning.linkOrder'), linkForm.value, {
    preserveScroll: true,
    onSuccess: () => {
      showLink.value = false;
      // reload current view to reflect changes
      reload();
    },
  });
}

// Helpers
function partnerShort(p) {
  return p?.oznaka || p?.name || '';
}

const dupMap = computed(() => {
  const map = new Map();
  for (const it of (props.items || [])) {
    const key = `${it.objekat || ''}|${it.order?.id || 'x'}`;
    map.set(key, (map.get(key) || 0) + 1);
  }
  return map;
});
function isDuplicateBar(bar) {
  const key = `${bar.objekat || ''}|${bar.order?.id || 'x'}`;
  return (dupMap.value.get(key) || 0) > 1;
}

const isDuplicateSelection = computed(() => {
  if (!insertForm.value?.order_id || !insertForm.value?.objekat) return false;
  const key = `${insertForm.value.objekat}|${insertForm.value.order_id}`;
  return (dupMap.value.get(key) || 0) > 0;
});
</script>

<style scoped>
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 60; }
.modal-card { background: white; color: #111827; border-radius: 0.5rem; width: 480px; max-width: calc(100% - 2rem); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
.dark .modal-card { background: #1f2937; color: #e5e7eb; }
.unit-label-vertical { writing-mode: vertical-rl; text-orientation: upright; }
.unit-label-inline { white-space: nowrap; }
.unit-day { display:block; font-size: 12px; line-height: 12px; font-weight: 600; }
.unit-month { display:block; font-size: 10px; line-height: 10px; color: #6b7280; }
</style>
