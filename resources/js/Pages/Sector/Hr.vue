<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const sections = [
  {
    label: 'Izlaznice',
    tone: 'sky',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1"/></svg>`,
    items: [
      { title: 'Aktivne izlaznice', href: route('passes.active') },
      { title: 'Sumarno', href: route('hr.izlaznice.sumarno') },
    ],
  },
  {
    label: 'Uposlenici',
    tone: 'teal',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m9.12-1.26a4 4 0 10-5.24 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
    items: [
      { title: 'Pregled', href: route('hr.uposlenici.pregled') },
      { title: 'Novi uposlenik', href: route('hr.uposlenici.forma') },
    ],
  },
  {
    label: 'Evidencija rada',
    tone: 'emerald',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
    items: [
      { title: 'Šihterica', href: route('hr.sihterica') },
      { title: 'Masovna dodjela statusa', href: route('hr.statusi.masovno') },
      { title: 'Prekovremeni sati', href: route('hr.prekovremeni-sati') },
      { title: 'Iskorištenje prekovremenih', href: route('hr.prekovremeni.iskoristenje') },
      { title: 'Odjeli i smjene', href: route('hr.smjene.dodjela') },
    ],
  },
  {
    label: 'Godišnji odmor',
    tone: 'amber',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 4h10a2 2 0 012 2v11a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2z"/></svg>`,
    items: [
      { title: 'Saldo', href: route('hr.godisnji.saldo') },
      { title: 'Unos rješenja', href: route('hr.godisnji.rjesenja') },
      { title: 'Lista rješenja', href: route('hr.godisnji.rjesenja.lista') },
      { title: 'Iskorišteni', href: route('hr.godisnji.iskoristenje') },
    ],
  },
  {
    label: 'Bolovanja',
    tone: 'rose',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
    items: [
      { title: 'Evidencija', href: route('hr.bolovanja') },
    ],
  },
]

const toneClasses = {
  sky: { soft: 'bg-sky-50 text-sky-700', accent: 'bg-sky-500', hover: 'hover:border-sky-300 hover:bg-sky-50' },
  teal: { soft: 'bg-teal-50 text-teal-700', accent: 'bg-teal-500', hover: 'hover:border-teal-300 hover:bg-teal-50' },
  emerald: { soft: 'bg-emerald-50 text-emerald-700', accent: 'bg-emerald-500', hover: 'hover:border-emerald-300 hover:bg-emerald-50' },
  amber: { soft: 'bg-amber-50 text-amber-700', accent: 'bg-amber-500', hover: 'hover:border-amber-300 hover:bg-amber-50' },
  rose: { soft: 'bg-rose-50 text-rose-700', accent: 'bg-rose-500', hover: 'hover:border-rose-300 hover:bg-rose-50' },
}
</script>

<template>
  <AppLayout title="HR sektor">
    <Head title="HR sektor" />

    <div class="hr-dash relative">
      <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -left-20 top-0 h-56 w-56 rounded-full bg-teal-200/25 blur-3xl"></div>
        <div class="absolute right-0 top-16 h-64 w-64 rounded-full bg-sky-200/20 blur-3xl"></div>
      </div>

      <div class="relative mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8 lg:py-5">
        <!-- All modules in one viewport grid -->
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
          <section
            v-for="section in sections"
            :key="section.label"
            class="flex flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white/95 shadow-sm"
            :class="section.label === 'Evidencija rada' ? 'md:col-span-2 xl:col-span-1' : ''"
          >
            <div class="flex items-center gap-2 border-b border-slate-100 px-3.5 py-2.5">
              <div
                class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                :class="toneClasses[section.tone].soft"
                v-html="section.icon"
              ></div>
              <h2 class="text-sm font-semibold text-slate-800">{{ section.label }}</h2>
              <div class="ml-auto h-1 w-8 rounded-full" :class="toneClasses[section.tone].accent"></div>
            </div>

            <div
              class="flex flex-1 gap-1 p-2"
              :class="section.items.length > 3 ? 'grid grid-cols-1 sm:grid-cols-2' : 'flex-col'"
            >
              <Link
                v-for="item in section.items"
                :key="item.title"
                :href="item.href"
                class="group flex items-center justify-between gap-2 rounded-lg border border-transparent px-2.5 py-1.5 text-sm text-slate-700 transition"
                :class="toneClasses[section.tone].hover"
              >
                <span class="font-medium leading-snug">{{ item.title }}</span>
                <svg
                  class="h-3.5 w-3.5 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </Link>
            </div>
          </section>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.hr-dash :deep(svg) {
  width: 0.95rem;
  height: 0.95rem;
}
</style>
