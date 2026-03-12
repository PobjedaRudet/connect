<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const links = [
  {
    title: 'Izlaznice',
    description: 'Aktivne izlaznice (boss/admin).',
    href: route('passes.active'),
  },
  {
    title: 'Uposlenici',
    description: 'Pregled uposlenika.',
    href: route('hr.uposlenici.pregled'),
  },
  {
    title: 'Novi uposlenik',
    description: 'Forma za unos novog uposlenika.',
    href: route('hr.uposlenici.forma'),
  },
  {
    title: 'Šihterica',
    description: 'Dolazak po mjesecu (attendance_records).',
    href: route('hr.sihterica'),
  },
  {
    title: 'Prekovremeni sati',
    description: 'Pregled prekovremenih po mjesecu (attendance_overtimes).',
    href: route('hr.prekovremeni-sati'),
  },
  {
    title: 'Iskorištenje prekovremenih',
    description: 'Unos i FIFO raspodjela iskorištenih prekovremenih sati.',
    href: route('hr.prekovremeni.iskoristenje'),
  },
  {
    title: 'Odjeli i smjene',
    description: 'Unos i izmjena odjela uz dodjelu jedne ili više smjena.',
    href: route('hr.smjene.dodjela'),
  },
  {
    title: 'Saldo godišnjeg',
    description: 'Pregled salda godišnjeg odmora po radniku.',
    href: route('hr.godisnji.saldo'),
  },
  {
    title: 'Unos rješenja godišnjeg',
    description: 'Forma za unos rješenja (ljetni/zimski/jednodnevni).',
    href: route('hr.godisnji.rjesenja'),
  },
  {
    title: 'Iskorišteni godišnji',
    description: 'Unos iskorištenog godišnjeg na osnovu rješenja.',
    href: route('hr.godisnji.iskoristenje'),
  },
  {
    title: 'Bolovanja',
    description: 'Evidencija bolovanja po radniku.',
    href: route('hr.bolovanja'),
  },
]
</script>

<template>
  <AppLayout title="HR sektor">
    <Head title="HR sektor" />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-800">HR sektor</h1>
        <p class="text-sm text-gray-500">Brzi linkovi za HR funkcije.</p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
          v-for="item in links"
          :key="item.title"
          :href="item.href"
          class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow transition p-5 flex flex-col gap-2"
        >
          <div class="text-lg font-semibold text-gray-800">{{ item.title }}</div>
          <div class="text-sm text-gray-500">{{ item.description }}</div>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
