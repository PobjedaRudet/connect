<template>
  <ProductionAppLayout title="Status naloga">
    <template #header>
  <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Status naloga</h2>
    </template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4">
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Pretraga</label>
              <input v-model="q" type="text" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200" placeholder="Broj ili opis..." />
            </div>
            <div>
              <label class="block text-xs text-gray-500 dark:text-gray-400">Status</label>
              <select v-model="status" class="form-input rounded-md dark:bg-gray-700 dark:text-gray-200">
                <option value="">(sve)</option>
                <option value="na odobrenju">na odobrenju</option>
                <option value="odobreno">odobreno</option>
                <option value="odbijeno">odbijeno</option>
              </select>
            </div>
            <div class="ml-auto">
              <button @click="load(1)" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Osvježi</button>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10">
                <tr class="bg-gray-50 dark:bg-gray-700/70 text-gray-700 dark:text-gray-200">
                  <th class="px-3 py-2 text-left select-none cursor-pointer" @click="setSort('OrderNumber')">
                    Broj <span class="text-[10px] opacity-70" v-if="sortKey==='OrderNumber'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('OrderDate')">
                    Datum <span class="text-[10px] opacity-70" v-if="sortKey==='OrderDate'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('partner')">
                    Partner <span class="text-[10px] opacity-70" v-if="sortKey==='partner'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('creator')">
                    Kreirao <span class="text-[10px] opacity-70" v-if="sortKey==='creator'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('Status')">
                    Status <span class="text-[10px] opacity-70" v-if="sortKey==='Status'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center select-none cursor-pointer" @click="setSort('total_quantity')">
                    Uk. količina <span class="text-[10px] opacity-70" v-if="sortKey==='total_quantity'">{{ sortIndicator }}</span>
                  </th>
                  <th class="px-3 py-2 text-center" style="min-width:220px;">Akcija</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="o in displayRows" :key="o.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td class="px-3 py-2 text-left">
                    <div class="font-medium flex flex-wrap items-left justify-left gap-2">
                      <a :href="`/productionorders/${o.id}`" class="text-blue-600 hover:underline">{{ o.OrderNumber }}</a>
                      <span class="text-gray-700 dark:text-gray-200">—</span>
                      <span class="text-gray-800 dark:text-gray-100 truncate max-w-[280px]">{{ o.Description || '' }}</span>
                    </div>
                  </td>
                  <td class="px-3 py-2 whitespace-nowrap text-center">{{ formatDateOnly(o.OrderDate) }}</td>
                  <td class="px-3 py-2 text-center">
                    <span v-if="o.partner?.name" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                      {{ o.partner.name }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">{{ o.creator?.name ?? '' }}</td>
                  <td class="px-3 py-2 text-center">
                    <span v-if="o.is_void" class="inline-flex items-center gap-2">
                      <span :class="statusClass(o.Status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px]">
                        {{ o.Status || 'Na čekanju' }}
                      </span>
                      <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-bold text-xs align-middle ml-1">Poništen</span>
                    </span>
                    <span v-else :class="statusClass(o.Status)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px]">
                      {{ o.Status || 'Na čekanju' }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">
                      {{ formatQty(totalQuantity(o)) }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <div class="flex flex-nowrap items-center justify-center gap-4">
                      <!-- Export grupa -->
                      <div class="flex flex-col sm:flex-row gap-1">
                        <a :href="`/productionorders/${o.id}/export-word`"
                           class="action-btn bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100 hover:text-blue-900"
                           title="Preuzmi Word dokument"
                           target="_blank" rel="noopener">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                          <span class="hidden sm:inline">Word</span>
                        </a>
                      </div>
                      <!-- Dupliciraj grupa -->
                      <div class="flex flex-col sm:flex-row gap-1">
                        <button @click="duplicate(o)" class="action-btn bg-gray-50 text-gray-800 border-gray-200 hover:bg-gray-100 hover:text-gray-900" title="Dupliciraj nalog">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16h8M8 12h8m-7 8h6a2 2 0 002-2V6a2 2 0 00-2-2H9a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                          <span class="hidden sm:inline">Dupliciraj</span>
                        </button>
                      </div>
                      <!-- Proxy grupa -->
                      <div v-if="showOneUp(o)" class="flex flex-col sm:flex-row gap-1">
                        <button @click="approveOneUp(o)" :title="tooltipText" class="action-btn bg-red-50 text-red-700 border-red-200 hover:bg-red-100 hover:text-red-900" >
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                          <span class="hidden sm:inline">Proxy</span>
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4 flex items-center justify-between text-xs text-gray-600 dark:text-gray-300">
            <div>
              Stranica {{ page }} / {{ totalPages }} ({{ total }} ukupno)
            </div>
            <div class="flex gap-2">
              <button :disabled="page<=1" @click="load(page-1)" class="px-3 py-1 border rounded disabled:opacity-50">«</button>
              <button :disabled="page>=totalPages" @click="load(page+1)" class="px-3 py-1 border rounded disabled:opacity-50">»</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ProductionAppLayout>
</template>

      <script setup>
      import { ref, onMounted, computed } from 'vue';
      import axios from 'axios';
      import ProductionAppLayout from '@/Layouts/ProductionAppLayout.vue';

      const rows = ref([]);
      const page = ref(1);
      const total = ref(0);
      const perPage = ref(20);
      const status = ref('');
      const q = ref('');
      const oneUpTarget = ref(null);

      const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
      const tooltipText = computed(() => oneUpTarget.value ? `Odobri kao proxy za: ${oneUpTarget.value}` : 'Odobri (1 nivo iznad)');

      // Sorting state (client-side for current page)
      const sortKey = ref('OrderDate');
      const sortDir = ref('desc');
      const sortIndicator = computed(() => (sortDir.value === 'asc' ? '▲' : '▼'));

      function setSort(key) {
        if (sortKey.value === key) {
          sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
        } else {
          sortKey.value = key;
          sortDir.value = key === 'OrderDate' ? 'desc' : 'asc';
        }
      }

      function statusWeight(s) {
        const x = (s || '').toLowerCase();
        if (x.includes('odobreno')) return 3;
        if (x.includes('na odobrenju')) return 2;
        if (x.includes('odbijeno')) return 1;
        return 0;
      }

      function sortValue(o, key) {
        switch (key) {
          case 'OrderNumber': return String(o.OrderNumber || '');
          case 'OrderDate': return new Date(o.OrderDate || 0).getTime();
          case 'partner': return String(o.partner?.name || '');
          case 'creator': return String(o.creator?.name || '');
          case 'Status': return statusWeight(o.Status);
          case 'total_quantity': return Number(totalQuantity(o) || 0);
          default: return '';
        }
      }

      const displayRows = computed(() => {
        const arr = [...(rows.value || [])];
        const key = sortKey.value;
        const dir = sortDir.value === 'asc' ? 1 : -1;
        arr.sort((a,b) => {
          const va = sortValue(a, key);
          const vb = sortValue(b, key);
          if (va < vb) return -1 * dir;
          if (va > vb) return 1 * dir;
          return 0;
        });
        return arr;
      });

      async function load(p=1) {
        page.value = p;
        try {
          const { data } = await axios.get('/productionorders/created', {
            params: { page: page.value, status: status.value, q: q.value }
          });
          rows.value = (data.data || []);
          total.value = data.total || 0;
          perPage.value = data.per_page || 20;
          page.value = data.current_page || p;
          oneUpTarget.value = data.one_up_target_funkcija || null;
        } catch (e) {
          console.error('Greška pri učitavanju', e);
        }
      }

      function pad2(n) { return String(n).padStart(2,'0'); }
      function formatDateOnly(dt) {
        if (!dt) return '';
        try {
          const d = new Date(dt);
          const dd = pad2(d.getDate());
          const mm = pad2(d.getMonth() + 1);
          const yyyy = d.getFullYear();
          return `${dd}/${mm}/${yyyy}`;
        } catch { return dt; }
      }

      function statusClass(s) {
        const x = (s || '').toLowerCase();
        if (x.includes('odobreno')) return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200';
        if (x.includes('na odobrenju')) return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200';
        if (x.includes('odbijeno')) return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200';
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200';
      }

      function formatQty(v) {
        const n = Number(v ?? 0);
        if (!isFinite(n)) return '0';
        return n % 1 === 0 ? n.toString() : n.toFixed(2);
      }

      function totalQuantity(o) {
        if (o.total_quantity != null) return Number(o.total_quantity);
        const details = Array.isArray(o.details) ? o.details : [];
        return details.reduce((sum, d) => sum + Number(d?.quantity || 0), 0);
      }

      function showOneUp(o) {
        // Show only if status is na odobrenju, my step is approved and immediate superior pending
        return ((o.Status || '').toLowerCase().startsWith('na odobrenju')) && (o.one_up_pending_count > 0) && (o.my_step_approved_count > 0);
      }

      onMounted(() => load(1));

      async function approveOneUp(o) {
        try {
          await axios.post(`/approvals/order/${o.id}/approve-one-up`, {});
          await load(page.value);
        } catch (e) {
          alert(e?.response?.data?.message || 'Greška pri odobravanju (1 nivo iznad)');
        }
      }
      async function duplicate(o) {
        try {
          const { data } = await axios.post(`/productionorders/${o.id}/duplicate`, {});
          const newId = data?.id;
          if (newId) {
            window.location.href = `/nalozi/nalozi-za-proizvodnju?edit=${newId}`;
          } else {
            alert('Nalog je dupliciran.');
            await load(page.value);
          }
        } catch (e) {
          alert(e?.response?.data?.message || 'Greška pri dupliciranju naloga');
        }
      }
      </script>

      <style scoped>
      .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.22rem 0.65rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px 0 rgba(0,0,0,0.04);
        font-weight: 400;
        font-size: 0.92rem;
        background: #f9fafb;
        cursor: pointer;
        transition: color 0.15s, background 0.15s, box-shadow 0.15s, transform 0.15s;
        outline: none;
        opacity: 0.97;
      }
      .action-btn:hover {
        background: #e5e7ef;
        color: #1e293b;
        box-shadow: 0 2px 8px 0 rgba(30,41,59,0.08);
        opacity: 1;
        transform: translateY(-1px) scale(1.01);
      }
      .action-btn:focus {
        box-shadow: 0 0 0 2px #2563eb33, 0 1px 3px 0 rgba(0,0,0,0.04);
      }
      </style>
