<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

const props = defineProps({
  pages: { type: Array, default: () => [] },
  funkcije: { type: Array, default: () => [] },
  availableRoutes: { type: Array, default: () => [] },
})

const form = ref({ name: '', route_name: '', description: '' })
const saving = ref(false)
const assigning = ref({}) // pageId => saving bool
const selectedRouteName = ref('')
const routeFilter = ref('')

const existingRouteNames = computed(() => new Set((props.pages || []).map(p => p.route_name)))
const routeOptions = computed(() => {
  const term = routeFilter.value.trim().toLowerCase()
  return (props.availableRoutes || [])
    .filter(r => r && r.name)
    .map(r => ({
      value: r.name,
      label: r.name,
      uri: r.uri,
      exists: existingRouteNames.value.has(r.name),
      haystack: `${r.name} ${r.uri || ''}`.toLowerCase(),
    }))
    .filter(opt => !term || opt.haystack.includes(term))
    .sort((a, b) => a.label.localeCompare(b.label))
})

watch(selectedRouteName, (val) => {
  form.value.route_name = val || ''
  // If name is empty, derive a friendly default from route name
  if (!form.value.name && val) {
    const pretty = val
      .split('.')
      .map(s => s.charAt(0).toUpperCase() + s.slice(1))
      .join(' ')
    form.value.name = pretty
  }
})

async function savePage() {
  if (!form.value.name || !form.value.route_name) return
  try {
    saving.value = true
    await window.axios.post('/admin/page-access/pages', form.value)
    window.location.reload()
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri snimanju stranice')
  } finally { saving.value = false }
}

async function saveAssign(page) {
  try {
    assigning.value[page.id] = true
    await window.axios.post('/admin/page-access/assign', {
      page_id: page.id,
      funkcije: page.allowed_funkcije,
    })
    alert('Sačuvano')
  } catch (e) {
    alert(e?.response?.data?.message || 'Greška pri ažuriranju pristupa')
  } finally { assigning.value[page.id] = false }
}
</script>

<template>
  <Head title="Upravljanje pristupom stranicama" />
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-semibold mb-4">Upravljanje pristupom stranicama</h1>

    <div class="bg-white dark:bg-gray-900 rounded shadow p-4 mb-8">
      <h2 class="text-lg font-medium mb-3">Dodaj/uredi stranicu</h2>
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
        <div class="md:col-span-5">
          <label class="text-sm text-gray-600 block mb-1">Odaberi rutu</label>
          <input v-model="routeFilter" type="text" placeholder="Pretraži po nazivu ili putanji" class="border rounded px-3 py-2 w-full mb-2" />
          <select v-model="selectedRouteName" class="border rounded px-3 py-2 w-full">
            <option value="">-- Odaberi route name --</option>
            <option v-for="opt in routeOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }} {{ opt.exists ? '(već dodana)' : '' }}
            </option>
          </select>
          <p v-if="selectedRouteName" class="text-xs text-gray-500 mt-1">URI: {{ routeOptions.find(o=>o.value===selectedRouteName)?.uri || '-' }}</p>
          <p v-if="selectedRouteName && routeOptions.find(o=>o.value===selectedRouteName)?.exists" class="text-xs text-amber-600 mt-1">Napomena: ova ruta je već dodana; promijeni naziv/opis samo ako treba.</p>
        </div>
        <div class="md:col-span-3">
          <label class="text-sm text-gray-600 block mb-1">Naziv</label>
          <input v-model="form.name" type="text" placeholder="Naziv (npr. Prodaja)" class="border rounded px-3 py-2 w-full" />
        </div>
        <div class="md:col-span-4">
          <label class="text-sm text-gray-600 block mb-1">Opis (opcionalno)</label>
          <input v-model="form.description" type="text" placeholder="Opis (opcionalno)" class="border rounded px-3 py-2 w-full" />
        </div>
      </div>
      <div class="mt-3">
        <button :disabled="saving" @click="savePage" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Sačuvaj stranicu</button>
      </div>
      <p class="text-sm text-gray-500 mt-2">Napomena: Route name se automatski postavlja izborom rute iz liste.</p>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded shadow p-4">
      <h2 class="text-lg font-medium mb-3">Prava pristupa</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left border-b">
              <th class="p-2">Stranica</th>
              <th class="p-2">Route</th>
              <th class="p-2">Opis</th>
              <th class="p-2">Funkcije sa pristupom</th>
              <th class="p-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in props.pages" :key="p.id" class="border-b">
              <td class="p-2 font-medium">{{ p.name }}</td>
              <td class="p-2">{{ p.route_name }}</td>
              <td class="p-2">{{ p.description || '-' }}</td>
              <td class="p-2">
                <div class="flex flex-wrap gap-3">
                  <label v-for="f in props.funkcije" :key="f" class="inline-flex items-center gap-2">
                    <input type="checkbox" :value="f" v-model="p.allowed_funkcije" />
                    <span>{{ f }}</span>
                  </label>
                </div>
              </td>
              <td class="p-2">
                <button :disabled="assigning[p.id]" @click="saveAssign(p)" class="px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700">Sačuvaj</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</template>
