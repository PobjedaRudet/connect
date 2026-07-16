<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

const items = [
  {
    title: 'Šihterica – audit log',
    desc: 'Pregled ručnih dodavanja, izmjena i brisanja prijave/odjave',
    href: route('admin.sihterica-audit'),
    icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
  },
]
</script>

<template>
  <AppLayout title="Admin Panel">
    <Head title="Admin Panel" />

    <div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-800">Admin Panel</h1>
        <p class="text-sm text-gray-500 mt-1">
          Administratorski alati. Samo admin korisnici imaju pristup ovim stranicama.
        </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <Link
          v-for="item in items"
          :key="item.title"
          :href="item.href"
          class="group bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:border-indigo-300 hover:shadow-md hover:bg-indigo-50/40 transition"
        >
          <div class="flex items-start gap-4">
            <div class="shrink-0 w-12 h-12 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center" v-html="item.icon" />
            <div>
              <h2 class="text-base font-semibold text-gray-800 group-hover:text-indigo-700">{{ item.title }}</h2>
              <p class="text-sm text-gray-500 mt-1">{{ item.desc }}</p>
            </div>
          </div>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
