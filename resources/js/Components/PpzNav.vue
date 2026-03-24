<script setup>
import { Link } from '@inertiajs/vue3'

const links = [
  { label: 'Uposlenici', href: route('pregledi.index'), route: 'pregledi.index' },
  { label: 'Ljekarski pregledi', href: route('pregledi.upcoming'), route: 'pregledi.upcoming' },
  { label: 'Kontrolni pregledi', href: route('pregledi.kontrolni'), route: 'pregledi.kontrolni' },
  { label: 'Pregledi za idući mjesec', href: route('pregledi.nextMonth'), route: 'pregledi.nextMonth' },
  { label: 'Izvještaj', href: route('ppz.izvjestajPregledi'), route: 'ppz.izvjestajPregledi' },
]

function isActive(r) {
  try { return route().current(r) } catch { return false }
}
</script>

<template>
  <nav class="bg-gradient-to-r from-slate-50 to-gray-50 border-b border-gray-200/60 mb-6 shadow-sm">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-center gap-2 overflow-x-auto py-3 scrollbar-hide">
        <!-- PPZ home -->
        <Link
          :href="route('ppz.dashboard')"
          class="shrink-0 px-4 py-2 rounded-full text-sm font-semibold tracking-wide transition-all duration-200"
          :class="route().current('ppz.dashboard')
            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-200'
            : 'text-gray-600 hover:text-indigo-700 hover:bg-indigo-50'"
        >
          PPZ
        </Link>

        <span class="shrink-0 w-px h-5 bg-gray-300/60 mx-1"></span>

        <!-- Module links -->
        <Link
          v-for="link in links"
          :key="link.route"
          :href="link.href"
          class="shrink-0 px-4 py-2 rounded-full text-sm font-medium tracking-wide transition-all duration-200"
          :class="isActive(link.route)
            ? 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200'
            : 'text-gray-500 hover:text-indigo-700 hover:bg-indigo-50/60'"
        >
          {{ link.label }}
        </Link>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
