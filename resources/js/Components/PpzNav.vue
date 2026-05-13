<script setup>
import { Link } from '@inertiajs/vue3'

const links = [
  { label: 'Uposlenici', href: route('pregledi.index'), route: 'pregledi.index' },
  { label: 'Ljekarski pregledi', href: route('pregledi.upcoming'), route: 'pregledi.upcoming' },
  { label: 'Kontrolni pregledi', href: route('pregledi.kontrolni'), route: 'pregledi.kontrolni' },
  { label: 'Idući mjesec', href: route('pregledi.nextMonth'), route: 'pregledi.nextMonth' },
  { label: 'Izvještaj', href: route('ppz.izvjestajPregledi'), route: 'ppz.izvjestajPregledi' },
]

function isActive(r) {
  try {
    return route().current(r)
  } catch {
    return false
  }
}
</script>

<template>
  <nav class="relative mb-8 overflow-hidden rounded-2xl border border-slate-200/70 bg-gradient-to-r from-white via-slate-50 to-cyan-50 shadow-sm">
    <div class="pointer-events-none absolute -left-8 -top-8 h-28 w-28 rounded-full bg-cyan-200/35 blur-2xl"></div>
    <div class="pointer-events-none absolute -right-10 -bottom-10 h-32 w-32 rounded-full bg-sky-300/30 blur-2xl"></div>

    <div class="relative mx-auto max-w-6xl px-4 py-3 sm:px-6 lg:px-8">
      <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
        <Link
          :href="route('ppz.dashboard')"
          class="shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold tracking-wide transition-all duration-200"
          :class="route().current('ppz.dashboard')
            ? 'bg-slate-900 text-white shadow-lg shadow-slate-900/20'
            : 'bg-white/80 text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100'"
        >
          PPZ centar
        </Link>

        <Link
          v-for="link in links"
          :key="link.route"
          :href="link.href"
          class="shrink-0 rounded-xl px-4 py-2.5 text-sm font-medium tracking-wide transition-all duration-200"
          :class="isActive(link.route)
            ? 'bg-cyan-600 text-white shadow-lg shadow-cyan-500/20'
            : 'bg-white/80 text-slate-600 ring-1 ring-slate-200 hover:bg-cyan-50 hover:text-cyan-700'"
        >
          {{ link.label }}
        </Link>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
