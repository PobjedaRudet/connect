<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const sections = [
  {
    label: 'Izlaznice',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/></svg>`,
    color: 'sky',
    items: [
      { title: 'Aktivne izlaznice', desc: 'Trenutno otvorene izlaznice', href: route('passes.active') },
      { title: 'Sumarno', desc: 'Pregled po mjesecima', href: route('hr.izlaznice.sumarno') },
    ],
  },
  {
    label: 'Uposlenici',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m9.12-1.26a4 4 0 10-5.24 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
    color: 'violet',
    items: [
      { title: 'Pregled', desc: 'Lista svih uposlenika', href: route('hr.uposlenici.pregled') },
      { title: 'Novi uposlenik', desc: 'Dodaj novog uposlenika', href: route('hr.uposlenici.forma') },
    ],
  },
  {
    label: 'Evidencija rada',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
    color: 'emerald',
    items: [
      { title: 'Šihterica', desc: 'Dnevna evidencija prisustva', href: route('hr.sihterica') },
      { title: 'Masovna dodjela statusa', desc: 'Grupno postavljanje statusa', href: route('hr.statusi.masovno') },
      { title: 'Prekovremeni sati', desc: 'Evidencija prekovremenog rada', href: route('hr.prekovremeni-sati') },
      { title: 'Iskorištenje prekovremenih', desc: 'Stanje iskorištenja sati', href: route('hr.prekovremeni.iskoristenje') },
      { title: 'Odjeli i smjene', desc: 'Dodjela smjena odjelima', href: route('hr.smjene.dodjela') },
    ],
  },
  {
    label: 'Godišnji odmor',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 4h10a2 2 0 012 2v11a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2z"/></svg>`,
    color: 'amber',
    items: [
      { title: 'Saldo', desc: 'Pregled stanja godišnjih odmora', href: route('hr.godisnji.saldo') },
      { title: 'Unos rješenja', desc: 'Kreiranje novih rješenja', href: route('hr.godisnji.rjesenja') },
      { title: 'Iskorišteni', desc: 'Evidencija korištenja', href: route('hr.godisnji.iskoristenje') },
    ],
  },
  {
    label: 'Bolovanja',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
    color: 'rose',
    items: [
      { title: 'Evidencija', desc: 'Pregled i unos bolovanja', href: route('hr.bolovanja') },
    ],
  },
]

const colorMap = {
  sky:     { bg: 'bg-sky-50',     border: 'border-sky-200',     icon: 'text-sky-600     bg-sky-100',     iconLg: 'text-sky-500 bg-sky-100',     hoverCard: 'hover:border-sky-300 hover:shadow-lg hover:shadow-sky-100/60 hover:bg-sky-50/50',     accent: 'bg-sky-500' },
  violet:  { bg: 'bg-violet-50',  border: 'border-violet-200',  icon: 'text-violet-600  bg-violet-100',  iconLg: 'text-violet-500 bg-violet-100',  hoverCard: 'hover:border-violet-300 hover:shadow-lg hover:shadow-violet-100/60 hover:bg-violet-50/50',  accent: 'bg-violet-500' },
  emerald: { bg: 'bg-emerald-50', border: 'border-emerald-200', icon: 'text-emerald-600 bg-emerald-100', iconLg: 'text-emerald-500 bg-emerald-100', hoverCard: 'hover:border-emerald-300 hover:shadow-lg hover:shadow-emerald-100/60 hover:bg-emerald-50/50', accent: 'bg-emerald-500' },
  amber:   { bg: 'bg-amber-50',   border: 'border-amber-200',   icon: 'text-amber-600   bg-amber-100',   iconLg: 'text-amber-500 bg-amber-100',   hoverCard: 'hover:border-amber-300 hover:shadow-lg hover:shadow-amber-100/60 hover:bg-amber-50/50',   accent: 'bg-amber-500' },
  rose:    { bg: 'bg-rose-50',    border: 'border-rose-200',    icon: 'text-rose-600    bg-rose-100',    iconLg: 'text-rose-500 bg-rose-100',    hoverCard: 'hover:border-rose-300 hover:shadow-lg hover:shadow-rose-100/60 hover:bg-rose-50/50',    accent: 'bg-rose-500' },
}

// Individual item icons for dashboard tiles
const itemIcons = {
  'Aktivne izlaznice': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/></svg>`,
  'Sumarno': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
  'Pregled': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m9.12-1.26a4 4 0 10-5.24 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
  'Novi uposlenik': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>`,
  'Šihterica': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
  'Masovna dodjela statusa': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>`,
  'Prekovremeni sati': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
  'Iskorištenje prekovremenih': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>`,
  'Odjeli i smjene': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>`,
  'Saldo': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>`,
  'Unos rješenja': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`,
  'Iskorišteni': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>`,
  'Evidencija': `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
}
</script>

<template>
  <AppLayout title="HR sektor">
    <Head title="HR sektor" />

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-10">
        <div class="flex items-center gap-3 mb-1">
          <div class="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Pravna služba</h1>
            <p class="text-sm text-gray-500">Upravljanje kadrovima, evidencija rada i odmora</p>
          </div>
        </div>
      </div>

      <!-- Dashboard Column Layout -->
      <div class="columns-1 sm:columns-2 lg:columns-3 gap-5 space-y-5">
        <div v-for="section in sections" :key="section.label" class="break-inside-avoid">
          <!-- Section panel -->
          <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-sm">
            <!-- Panel header -->
            <div class="flex items-center gap-2.5 px-5 py-3.5 border-b" :class="[colorMap[section.color].bg, colorMap[section.color].border]">
              <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg" :class="colorMap[section.color].icon" v-html="section.icon"></span>
              <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">{{ section.label }}</h2>
            </div>

            <!-- Panel tiles -->
            <div class="grid grid-cols-2 gap-3 p-4">
              <Link
                v-for="item in section.items"
                :key="item.title"
                :href="item.href"
                class="group relative flex flex-col items-center justify-center rounded-2xl border bg-white p-4 min-h-[130px] text-center transition-all duration-200 hover:-translate-y-1 hover:shadow-lg"
                :class="[colorMap[section.color].border, colorMap[section.color].hoverCard]"
              >
                <!-- Top accent bar -->
                <div class="absolute top-0 left-4 right-4 h-1 rounded-b-full opacity-0 group-hover:opacity-100 transition-opacity" :class="colorMap[section.color].accent"></div>

                <!-- Icon -->
                <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-2.5 transition-transform duration-200 group-hover:scale-110" :class="colorMap[section.color].iconLg" v-html="itemIcons[item.title] || section.icon"></div>

                <!-- Title -->
                <div class="text-[13px] font-semibold text-gray-800 group-hover:text-gray-900 leading-tight">{{ item.title }}</div>

                <!-- Description -->
                <div class="mt-1 text-[11px] text-gray-400 leading-tight">{{ item.desc }}</div>

                <!-- Arrow indicator -->
                <svg class="absolute top-2.5 right-2.5 w-3.5 h-3.5 text-gray-200 group-hover:text-gray-400 transition-all duration-200 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
