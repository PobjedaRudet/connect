<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PpzNav from '@/Components/PpzNav.vue'
import { ref, computed } from 'vue'
import axios from 'axios'

const pregledi = ref([])
const loading = ref(true)
const exportLoading = ref(false)
const search = ref('')

async function fetchPregledi() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/ppz-izvjestaj-pregledi')
    pregledi.value = data
  } finally {
    loading.value = false
  }
}

fetchPregledi()

const filteredPregledi = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) {
    return pregledi.value
  }

  return pregledi.value.filter((p) => {
    const fullName = `${p.lastName ?? ''} ${p.middleName ?? ''} ${p.firstName ?? ''}`.toLowerCase()
    return fullName.includes(q)
      || (p.radno_mjesto ?? '').toLowerCase().includes(q)
      || (p.organizacija ?? '').toLowerCase().includes(q)
      || (p.type ?? '').toLowerCase().includes(q)
  })
})

function formatDatum(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  const dan = String(d.getDate()).padStart(2, '0')
  const mjesec = String(d.getMonth() + 1).padStart(2, '0')
  const godina = d.getFullYear()
  return `${dan}.${mjesec}.${godina}`
}

async function exportToWord() {
  exportLoading.value = true
  try {
    const response = await axios.post('/api/ppz-izvjestaj-pregledi-word', filteredPregledi.value, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'izvjestaj_pregledi.docx')
    document.body.appendChild(link)
    link.click()
    link.remove()
  } finally {
    exportLoading.value = false
  }
}
</script>

<template>
  <AppLayout title="PPZ izvještaj pregleda">
    <PpzNav />

    <div class="mx-auto max-w-[96rem] px-4 pb-10 sm:px-6 lg:px-8">
      <section class="mb-6 rounded-3xl border border-slate-200 bg-gradient-to-r from-slate-900 via-slate-800 to-cyan-800 px-6 py-8 text-white shadow-xl sm:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.24em] text-cyan-200">Analitika pregleda</p>
            <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Izvještaj ljekarskih pregleda</h1>
            <p class="mt-2 text-sm text-slate-200">Centralni pregled svih pregleda sa brzom pretragom i izvozom dokumenta.</p>
          </div>

          <button
            @click="exportToWord"
            :disabled="exportLoading || loading"
            class="inline-flex items-center justify-center rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <span v-if="exportLoading" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-950 border-r-transparent"></span>
            Export u Word
          </button>
        </div>
      </section>

      <section class="mb-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm text-slate-600">
          Prikazano zapisa: <span class="font-semibold text-slate-900">{{ filteredPregledi.length }}</span>
        </div>

        <input
          v-model="search"
          type="text"
          placeholder="Pretraga po imenu, organizaciji, radnom mjestu..."
          class="w-full rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 sm:max-w-md"
        />
      </section>

      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div v-if="loading" class="px-6 py-12 text-center text-slate-500">Učitavanje podataka...</div>

        <div v-else-if="!filteredPregledi.length" class="px-6 py-12 text-center text-slate-500">
          Nema rezultata za odabrani kriterij pretrage.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-100">
              <tr>
                <th class="whitespace-nowrap px-4 py-3 text-center font-semibold text-slate-700">#</th>
                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-slate-700">Organizacija</th>
                <th class="whitespace-nowrap px-4 py-3 text-center font-semibold text-slate-700">Datum pregleda</th>
                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-slate-700">Prezime (Srednje ime) Ime</th>
                <th class="whitespace-nowrap px-4 py-3 text-left font-semibold text-slate-700">Radno mjesto</th>
                <th class="whitespace-nowrap px-4 py-3 text-center font-semibold text-slate-700">Sposobnost</th>
                <th class="whitespace-nowrap px-4 py-3 text-center font-semibold text-slate-700">Profesionalno oboljenje</th>
                <th class="whitespace-nowrap px-4 py-3 text-center font-semibold text-slate-700">Invalidnost</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
              <tr v-for="(p, idx) in filteredPregledi" :key="`${p.employee_id ?? idx}-${idx}`" class="odd:bg-white even:bg-slate-50/60 hover:bg-cyan-50/60">
                <td class="px-4 py-3 text-center text-slate-600">{{ idx + 1 }}</td>
                <td class="px-4 py-3 text-slate-800">{{ p.organizacija }}</td>
                <td class="px-4 py-3 text-center text-slate-700">{{ formatDatum(p.datum_pregleda) }}</td>
                <td class="px-4 py-3 text-slate-800">
                  {{ p.lastName }}<span v-if="p.middleName"> ({{ p.middleName }})</span> {{ p.firstName }}
                </td>
                <td class="px-4 py-3 text-slate-700">{{ p.radno_mjesto }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ p.type }}</span>
                </td>
                <td class="px-4 py-3 text-center text-slate-700">{{ p.profesionalno_oboljenje || '-' }}</td>
                <td class="px-4 py-3 text-center text-slate-700">{{ p.invalidnost_radnika || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
