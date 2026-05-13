<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PpzNav from '@/Components/PpzNav.vue'
import { Link, Head } from '@inertiajs/vue3'

const options = [
  {
    name: 'Lista uposlenika',
    description: 'Spisak radnika i osnovnih podataka o zdravstvenim statusima.',
    link: route('pregledi.index'),
    tone: 'from-cyan-500 to-blue-600',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m9.12-1.26a4 4 0 10-5.24 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`,
  },
  {
    name: 'Ljekarski pregledi',
    description: 'Pregled rokova i planiranih zdravstvenih pregleda po periodima.',
    link: route('pregledi.upcoming'),
    tone: 'from-emerald-500 to-teal-600',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M4.93 19.07A10 10 0 1119.07 4.93 10 10 0 014.93 19.07z" /></svg>`,
  },
  {
    name: 'Kontrolni pregledi',
    description: 'Vođenje kontrolnih termina i ažuriranje statusa po nalazu.',
    link: route('pregledi.kontrolni'),
    tone: 'from-violet-500 to-indigo-600',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-3-3v6m9-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
  },
  {
    name: 'Izvještaj pregleda',
    description: 'Tabela svih ljekarskih pregleda sa izvozom u Word dokument.',
    link: route().has('ppz.izvjestajPregledi') ? route('ppz.izvjestajPregledi') : '/ppz/izvjestaj-pregledi',
    tone: 'from-amber-500 to-orange-600',
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2a4 4 0 014-4h6M7 7h10M7 11h8m-8 4h5m-3 6H7a4 4 0 01-4-4V7a4 4 0 014-4h10a4 4 0 014 4v2" /></svg>`,
  },
]
</script>

<template>
  <AppLayout title="PPZ centar">
    <Head title="PPZ centar" />
    <PpzNav />

    <div class="mx-auto max-w-6xl px-4 pb-12 sm:px-6 lg:px-8">
      <section class="relative mb-8 overflow-hidden rounded-3xl border border-slate-200 bg-slate-900 px-6 py-10 text-white shadow-xl sm:px-10">
        <div class="pointer-events-none absolute -right-12 -top-12 h-48 w-48 rounded-full bg-cyan-400/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-14 -bottom-14 h-56 w-56 rounded-full bg-indigo-400/25 blur-3xl"></div>

        <p class="text-xs uppercase tracking-[0.28em] text-cyan-200">Služba zaštite i prevencije</p>
        <h1 class="mt-3 max-w-2xl text-3xl font-bold leading-tight sm:text-4xl">PPZ komandni centar za evidenciju pregleda</h1>
        <p class="mt-4 max-w-3xl text-sm text-slate-200 sm:text-base">
          Brz pristup ključnim PPZ modulima, evidenciji zdravstvenih pregleda i izvještajima za operativni rad.
        </p>
      </section>

      <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <Link
          v-for="option in options"
          :key="option.name"
          :href="option.link"
          class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
        >
          <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r" :class="option.tone"></div>

          <div class="inline-flex rounded-xl bg-slate-100 p-3 text-slate-700 transition group-hover:bg-slate-900 group-hover:text-white" v-html="option.icon"></div>

          <h2 class="mt-4 text-lg font-semibold text-slate-900">{{ option.name }}</h2>
          <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ option.description }}</p>

          <div class="mt-4 inline-flex items-center text-sm font-semibold text-cyan-700 transition group-hover:text-cyan-500">
            Otvori modul
            <svg class="ml-1 h-4 w-4 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </div>
        </Link>
      </section>
    </div>
  </AppLayout>
</template>
