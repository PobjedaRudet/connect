<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const tiles = [
  {
    label: 'Izlaznice',
    bg: 'bg-sky-100',
    hover: 'hover:bg-sky-200',
    span: '',
    items: [
      { title: 'Aktivne izlaznice', href: route('passes.active') },
      { title: 'Sumarno', href: route('hr.izlaznice.sumarno') },
    ],
  },
  {
    label: 'Uposlenici',
    bg: 'bg-violet-100',
    hover: 'hover:bg-violet-200',
    span: '',
    items: [
      { title: 'Pregled', href: route('hr.uposlenici.pregled') },
      { title: 'Novi uposlenik', href: route('hr.uposlenici.forma') },
    ],
  },
  {
    label: 'Evidencija rada',
    bg: 'bg-emerald-100',
    hover: 'hover:bg-emerald-200',
    span: 'sm:col-span-2',
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
    bg: 'bg-amber-100',
    hover: 'hover:bg-amber-200',
    span: '',
    items: [
      { title: 'Saldo', href: route('hr.godisnji.saldo') },
      { title: 'Unos rješenja', href: route('hr.godisnji.rjesenja') },
      { title: 'Iskorišteni', href: route('hr.godisnji.iskoristenje') },
    ],
  },
  {
    label: 'Bolovanja',
    bg: 'bg-rose-100',
    hover: 'hover:bg-rose-200',
    span: '',
    items: [
      { title: 'Evidencija', href: route('hr.bolovanja') },
    ],
  },
]
</script>

<template>
  <AppLayout title="HR sektor">
    <Head title="HR sektor" />

    <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">HR sektor</h1>

      <!-- Metro tile grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
        <div
          v-for="tile in tiles"
          :key="tile.label"
          class="rounded-2xl p-4 flex flex-col justify-between min-h-[140px]"
          :class="[tile.bg, tile.span]"
        >
          <!-- Tile label -->
          <div class="text-gray-600 text-sm font-bold uppercase tracking-wider mb-3">
            {{ tile.label }}
          </div>

          <!-- Tile buttons -->
          <div class="flex flex-wrap gap-1.5">
            <Link
              v-for="item in tile.items"
              :key="item.title"
              :href="item.href"
              class="inline-block px-4 py-2 text-sm font-medium text-gray-700 bg-white/70 rounded hover:bg-white hover:shadow-sm transition-all duration-150"
            >
              {{ item.title }}
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
