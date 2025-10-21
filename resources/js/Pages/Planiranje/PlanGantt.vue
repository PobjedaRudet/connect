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
            <input v-model="filters.from" type="date" class="form-input w-full mt-1" />
          </div>
          <div>
            <label class="block text-xs text-gray-500">Do</label>
            <input v-model="filters.to" type="date" class="form-input w-full mt-1" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs text-gray-500">Objekat</label>
            <select v-model="filters.objekat" class="form-input w-full mt-1">
              <option value="">Svi objekti</option>
              <option v-for="o in objekti" :key="o" :value="o">{{ o }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500">Grupisanje</label>
            <select v-model="filters.groupBy" class="form-input w-full mt-1">
              <option value="none">Bez grupisanja</option>
              <option value="order">Po nalogu</option>
              <option value="objekat">Po objektu</option>
              <option value="objekat_shift">Po objektu (smjene)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500">Zoom</label>
            <select v-model="filters.zoom" class="form-input w-full mt-1">
              <option value="day">Dnevno</option>
              <option value="week">Sedmično</option>
              <option value="month">Mjesečno</option>
            </select>
          </div>
          <div class="flex gap-2 md:col-span-2">
            <button class="px-4 py-2 bg-gray-800 text-white rounded" @click="reload">Primijeni</button>
            <button class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded" @click="resetRange">Očisti</button>
          </div>
        </div>

        <!-- Gantt Chart -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded shadow">
          <div class="flex">
            <!-- Left fixed labels -->
            <div class="shrink-0" style="width: 280px;">
              <div class="h-6 font-semibold text-gray-700 dark:text-gray-200">{{ leftHeaderLabel }}</div>
              <div class="h-px bg-gray-200 dark:bg-gray-700 my-2" />
              <div>
                <div v-for="row in groupedRows" :key="'l-' + row.key" class="pr-2">
                  <template v-if="row.type==='header'">
                    <div class="h-6 py-1 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ row.label }}</div>
                  </template>
                  <template v-else>
                    <div class="h-8 text-sm whitespace-nowrap">
                      <div class="font-medium truncate max-w-[260px]">{{ row.label }}</div>
                      <div v-if="row.sub" class="text-xs text-gray-500 truncate max-w-[260px]">{{ row.sub }}</div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
            <!-- Right scrollable timeline -->
            <div class="relative grow overflow-x-auto" ref="scrollXRef">
              <div class="min-w-[900px] relative">
                <!-- Header units -->
                <div class="grid" :style="{ gridTemplateColumns: rightColumns }">
                  <div v-for="u in units" :key="u.key" class="h-6 text-xs text-center text-gray-500">{{ u.label }}</div>
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
                      <span class="text-[10px] text-white px-2 leading-6 select-none">{{ bar.order?.OrderNumber }}</span>
                    </div>
                  </template>
                </div>
                <!-- Tooltip -->
                <div v-if="tooltip.visible" class="pointer-events-none fixed z-50 text-xs bg-black/80 text-white px-2 py-1 rounded"
                     :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }">
                  <div class="font-semibold">{{ tooltip.order?.OrderNumber }} — {{ tooltip.order?.Description }}</div>
                  <div>{{ tooltip.objekat }}</div>
                  <div>{{ tooltip.start }} → {{ tooltip.end }}</div>
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
});

const objekti = ref(props.objekti || []);
const filters = ref({
  from: props.from || '',
  to: props.to || '',
  objekat: props.selectedObjekat || '',
  groupBy: props.groupBy || 'objekat_shift',
  zoom: props.zoom || 'day', // 'day' | 'week' | 'month'
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
  const str = String(o || '');
  if (str.toLowerCase().includes('laboracija')) {
    if (str.toLowerCase().includes('ii smjena')) return { base: 'Laboracija', shift: 'II smjena' };
    if (str.toLowerCase().includes('i smjena')) return { base: 'Laboracija', shift: 'I smjena' };
    return { base: 'Laboracija', shift: '' };
  }
  // Example: Kompletiranje Nonel -> base Kompletiranje, shift Nonel
  if (str.toLowerCase().startsWith('kompletiranje ')) {
    const parts = str.split(' ');
    return { base: 'Kompletiranje', shift: parts.slice(1).join(' ') };
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
  if (base === 'Laboracija') {
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
      map.get(key).bars.push({ key: `bar-${i.id}`, start: i.start_date, end: i.end_date, objekat: i.objekat, order: i.order });
    }
    return Array.from(map.values());
  }
  if (gb === 'objekat') {
    const map = new Map();
    for (const i of baseItems.value) {
      const key = i.objekat || '—';
      if (!map.has(key)) map.set(key, { key: `obj-${key}`, label: key, sub: '', bars: [] });
      map.get(key).bars.push({ key: `bar-${i.id}`, start: i.start_date, end: i.end_date, objekat: i.objekat, order: i.order });
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
      child.bars.push({ key: `bar-${i.id}`, start: i.start_date, end: i.end_date, objekat: i.objekat, order: i.order });
    }
    const rows = groups.size ? Array.from(groups.values()).flatMap(g => g.children) : [];
    // custom order: Laboracija I smjena, Laboracija II smjena, Kompletiranje, Kompletiranje Nonel, then others alpha
    const rank = (label) => {
      const l = String(label).toLowerCase();
      if (l === 'laboracija i smjena') return 1;
      if (l === 'laboracija ii smjena') return 2;
      if (l === 'kompletiranje') return 3;
      if (l === 'kompletiranje nonel') return 4;
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
    bars: [{ key: `bar-${i.id}`, start: i.start_date, end: i.end_date, objekat: i.objekat, order: i.order }]
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
      const label = `${cur.getDate().toString().padStart(2, '0')}.${(cur.getMonth()+1).toString().padStart(2, '0')}`;
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
  return { left: leftPx + 'px', width: widthPx + 'px', top: '6px', backgroundColor: colorForBar(bar), color: '#fff' };
}

// Free-slot highlighting per unit (by objekat views)
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
  // Highlight only when grouping by objekat-based rows
  if (filters.value.groupBy === 'objekat' || filters.value.groupBy === 'objekat_shift') {
    return isUnitCovered(row, unit) ? base : base + ' bg-green-50 dark:bg-green-900/30';
  }
  return base;
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
}
function hideTooltip() { tooltip.value.visible = false; }
function openOrder(bar) {
  if (bar?.order?.id) router.visit(route('productionorders.show', bar.order.id));
}
</script>
