<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const groups = [
  {
    label: 'Izlaznice',
    links: [
      { label: 'Aktivne', href: route('passes.active'), route: 'passes.active' },
      { label: 'Sumarno', href: route('hr.izlaznice.sumarno'), route: 'hr.izlaznice.sumarno' },
    ],
  },
  {
    label: 'Uposlenici',
    links: [
      { label: 'Pregled', href: route('hr.uposlenici.pregled'), route: 'hr.uposlenici.pregled' },
    ],
  },
  {
    label: 'Evidencija',
    links: [
      { label: 'Šihterica', href: route('hr.sihterica'), route: 'hr.sihterica' },
      { label: 'Statusi', href: route('hr.statusi.masovno'), route: 'hr.statusi.masovno' },
      { label: 'Prekovremeni', href: route('hr.prekovremeni-sati'), route: 'hr.prekovremeni-sati' },
      { label: 'Iskorištenje PK', href: route('hr.prekovremeni.iskoristenje'), route: 'hr.prekovremeni.iskoristenje' },
      { label: 'Smjene', href: route('hr.smjene.dodjela'), route: 'hr.smjene.dodjela' },
    ],
  },
  {
    label: 'Godišnji',
    links: [
      { label: 'Saldo', href: route('hr.godisnji.saldo'), route: 'hr.godisnji.saldo' },
      { label: 'Unos', href: route('hr.godisnji.rjesenja'), route: 'hr.godisnji.rjesenja' },
      { label: 'Lista', href: route('hr.godisnji.rjesenja.lista'), route: 'hr.godisnji.rjesenja.lista' },
      { label: 'Iskorišteni', href: route('hr.godisnji.iskoristenje'), route: 'hr.godisnji.iskoristenje' },
    ],
  },
  {
    label: 'Bolovanja',
    links: [
      { label: 'Evidencija', href: route('hr.bolovanja'), route: 'hr.bolovanja' },
    ],
  },
]

function isActive(r) {
  try {
    return route().current(r)
  } catch {
    return false
  }
}

function isGroupActive(group) {
  return group.links.some((l) => isActive(l.route))
}

const isHrHome = computed(() => {
  try {
    return route().current('sector.hr')
  } catch {
    return false
  }
})
</script>

<template>
  <nav class="hr-nav sticky top-0 z-20 mb-4 border-b border-slate-200/80 bg-white/95 backdrop-blur-sm">
    <div class="mx-auto flex max-w-[110rem] justify-center px-3 py-2 sm:px-4 lg:px-6">
      <div class="hr-nav-scroll flex items-stretch gap-2 overflow-x-auto">
        <Link
          :href="route('sector.hr')"
          class="inline-flex shrink-0 items-center self-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-semibold tracking-wide transition"
          :class="isHrHome
            ? 'bg-slate-900 text-white'
            : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
          title="HR komandni centar"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z" />
          </svg>
          HR
        </Link>

        <span class="mx-1 w-px shrink-0 self-stretch bg-slate-200" aria-hidden="true" />

        <div class="flex items-stretch gap-0">
          <div
            v-for="(group, gi) in groups"
            :key="group.label"
            class="flex shrink-0 items-stretch"
          >
            <span
              v-if="gi > 0"
              class="mx-2 w-px shrink-0 self-stretch bg-slate-200"
              aria-hidden="true"
            />

            <div class="flex min-w-0 flex-col items-center gap-0.5 px-1">
              <span
                class="w-full whitespace-nowrap text-center text-[10px] font-semibold uppercase tracking-[0.14em]"
                :class="isGroupActive(group) ? 'text-teal-700' : 'text-slate-400'"
              >
                {{ group.label }}
              </span>

              <div class="flex flex-wrap items-center justify-center gap-0.5">
                <Link
                  v-for="link in group.links"
                  :key="link.route"
                  :href="link.href"
                  class="shrink-0 rounded px-1.5 py-0.5 text-[12px] font-medium leading-5 transition"
                  :class="isActive(link.route)
                    ? 'bg-teal-600 text-white'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                >
                  {{ link.label }}
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.hr-nav-scroll {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.hr-nav-scroll::-webkit-scrollbar {
  display: none;
}
</style>
