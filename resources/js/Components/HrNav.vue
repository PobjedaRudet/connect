<script setup>
import { Link } from '@inertiajs/vue3'

const groups = [
  {
    label: 'Izlaznice',
    color: 'sky',
    links: [
      { label: 'Aktivne', href: route('passes.active'), route: 'passes.active' },
      { label: 'Sumarno', href: route('hr.izlaznice.sumarno'), route: 'hr.izlaznice.sumarno' },
    ],
  },
  {
    label: 'Uposlenici',
    color: 'violet',
    links: [
      { label: 'Pregled', href: route('hr.uposlenici.pregled'), route: 'hr.uposlenici.pregled' },
    ],
  },
  {
    label: 'Evidencija rada',
    color: 'emerald',
    links: [
      { label: 'Šihterica', href: route('hr.sihterica'), route: 'hr.sihterica' },
      { label: 'Statusi', href: route('hr.statusi.masovno'), route: 'hr.statusi.masovno' },
      { label: 'Prekovremeni', href: route('hr.prekovremeni-sati'), route: 'hr.prekovremeni-sati' },
      { label: 'Iskorištenje PK', href: route('hr.prekovremeni.iskoristenje'), route: 'hr.prekovremeni.iskoristenje' },
      { label: 'Smjene', href: route('hr.smjene.dodjela'), route: 'hr.smjene.dodjela' },
    ],
  },
  {
    label: 'Godišnji odmor',
    color: 'amber',
    links: [
      { label: 'Saldo', href: route('hr.godisnji.saldo'), route: 'hr.godisnji.saldo' },
      { label: 'Rješenja', href: route('hr.godisnji.rjesenja'), route: 'hr.godisnji.rjesenja' },
      { label: 'Iskorišteni', href: route('hr.godisnji.iskoristenje'), route: 'hr.godisnji.iskoristenje' },
    ],
  },
  {
    label: 'Bolovanja',
    color: 'rose',
    links: [
      { label: 'Evidencija', href: route('hr.bolovanja'), route: 'hr.bolovanja' },
    ],
  },
]

const colorClasses = {
  sky:     { label: 'text-sky-600',     active: 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',     hover: 'hover:bg-sky-50/60 hover:text-sky-700',     dot: 'bg-sky-400' },
  violet:  { label: 'text-violet-600',  active: 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',  hover: 'hover:bg-violet-50/60 hover:text-violet-700',  dot: 'bg-violet-400' },
  emerald: { label: 'text-emerald-600', active: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200', hover: 'hover:bg-emerald-50/60 hover:text-emerald-700', dot: 'bg-emerald-400' },
  amber:   { label: 'text-amber-600',   active: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',   hover: 'hover:bg-amber-50/60 hover:text-amber-700',   dot: 'bg-amber-400' },
  rose:    { label: 'text-rose-600',    active: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',    hover: 'hover:bg-rose-50/60 hover:text-rose-700',    dot: 'bg-rose-400' },
}

function isActive(r) {
  try { return route().current(r) } catch { return false }
}

function isGroupActive(group) {
  return group.links.some(l => isActive(l.route))
}
</script>

<template>
  <nav class="bg-white border-b border-gray-200/80 mb-6 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
      <!-- Row 1: HR home + first row groups (Izlaznice, Uposlenici, Evidencija rada) -->
      <div class="flex items-center gap-2 flex-wrap">
        <!-- HR home button -->
        <Link
          :href="route('sector.hr')"
          class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-sm font-semibold tracking-wide transition-all duration-200"
          :class="route().current('sector.hr')
            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200'
            : 'text-indigo-600 hover:bg-indigo-50'"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/>
          </svg>
          HR
        </Link>

        <span class="shrink-0 w-px h-5 bg-gray-200 mx-0.5"></span>

        <!-- First row groups -->
        <template v-for="(group, gi) in groups.slice(0, 3)" :key="group.label">
          <div class="shrink-0 flex items-center gap-1">
            <span class="inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider pl-1 pr-0.5" :class="colorClasses[group.color].label">
              <span class="w-1.5 h-1.5 rounded-full" :class="colorClasses[group.color].dot"></span>
              {{ group.label }}
            </span>
            <Link
              v-for="link in group.links"
              :key="link.route"
              :href="link.href"
              class="shrink-0 px-2.5 py-1.5 rounded-lg text-[13px] font-medium transition-all duration-200"
              :class="isActive(link.route)
                ? colorClasses[group.color].active
                : ['text-gray-500', colorClasses[group.color].hover]"
            >
              {{ link.label }}
            </Link>
          </div>
          <span v-if="gi < 2" class="shrink-0 w-px h-5 bg-gray-200/70 mx-0.5"></span>
        </template>
      </div>

      <!-- Row 2: remaining groups (Godišnji odmor, Bolovanja) -->
      <div class="flex items-center gap-2 flex-wrap mt-2 pl-[72px]">
        <template v-for="(group, gi) in groups.slice(3)" :key="group.label">
          <div class="shrink-0 flex items-center gap-1">
            <span class="inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider pl-1 pr-0.5" :class="colorClasses[group.color].label">
              <span class="w-1.5 h-1.5 rounded-full" :class="colorClasses[group.color].dot"></span>
              {{ group.label }}
            </span>
            <Link
              v-for="link in group.links"
              :key="link.route"
              :href="link.href"
              class="shrink-0 px-2.5 py-1.5 rounded-lg text-[13px] font-medium transition-all duration-200"
              :class="isActive(link.route)
                ? colorClasses[group.color].active
                : ['text-gray-500', colorClasses[group.color].hover]"
            >
              {{ link.label }}
            </Link>
          </div>
          <span v-if="gi < groups.slice(3).length - 1" class="shrink-0 w-px h-5 bg-gray-200/70 mx-0.5"></span>
        </template>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
