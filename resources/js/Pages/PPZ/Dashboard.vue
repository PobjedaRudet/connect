<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import PpzNav from '@/Components/PpzNav.vue'
import { Link, Head } from '@inertiajs/vue3';

const options = [
    {
        name: 'Lista uposlenika',
        description: 'Spisak uposlenika i evidencija ljekarskih pregleda radnika.',
        link: route('pregledi.index'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" /></svg>`
    },
     {
        name: 'Ljekarski pregledi',
        description: 'Pregled i evidencija ljekarskih pregleda radnika.',
        link: route('pregledi.upcoming'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" /></svg>`
    },
      {
        name: 'Kontrolni pregledi',
        description: 'Evidencija i unos kontrolnih ljekarskih pregleda radnika.',
        link: route('pregledi.kontrolni'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m9-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
    },
    {
        name: 'Izvještaj ljekarskih pregleda',
        description: 'Pregled svih ljekarskih pregleda sa detaljima za PPZ.',
        link: route().has('ppz.izvjestajPregledi') ? route('ppz.izvjestajPregledi') : '/ppz/izvjestaj-pregledi',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h3m4 4v2a4 4 0 01-4 4H7a4 4 0 01-4-4v-2a4 4 0 014-4h3m4-4V7a4 4 0 00-4-4H7a4 4 0 00-4 4v2a4 4 0 004 4h3" /></svg>`
    }
    /* {
        name: 'Godišnji odmori',
        description: 'Upravljanje i pregled godišnjih odmora radnika.',
        link: '/ppz/godisnji-odmori',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>`
    },
    {
        name: 'Obuka radnika',
        description: 'Evidencija i planiranje obuka za radnike.',
        link: '/ppz/obuka-radnika',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m0 0H6m6 0h6" /></svg>`
    },
    {
        name: 'Evidencija o radnicima na radnim mjestima sa povećanim rizikom',
        description: 'Pregled i upravljanje radnicima na rizičnim radnim mjestima.',
        link: '/ppz/radnici-rizik',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" /></svg>`
    },
    {
        name: 'Kontrolni pregledi',
        description: 'Evidencija i unos kontrolnih ljekarskih pregleda radnika.',
        link: route('pregledi.kontrolni'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m9-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
    },
    {
        name: 'Pregledi za idući mjesec',
        description: 'Evidencija i unos kontrolnih ljekarskih pregleda radnika.',
        link: route('pregledi.nextMonth'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-3-3v6m9-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
    }, */
];
</script>

<template>
  <AppLayout title="ZNR I PPZ Dashboard">
    <Head title="ZNR I PPZ Dashboard" />
    <PpzNav />

    <div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold mb-8 text-center text-gray-800">ZNR I PPZ Dashboard</h1>
        <div class="grid gap-8 lg:grid-cols-2 md:grid-cols-2 sm:grid-cols-1">
            <div
                v-for="option in options"
                :key="option.name"
                class="bg-white rounded shadow p-8 flex flex-col items-center hover:shadow-lg transition"
            >
                <Link :href="option.link" class="flex flex-col items-center group w-full">
                    <span v-html="option.icon"></span>
                    <span class="mt-4 text-lg font-semibold group-hover:text-[#FF2D20] transition text-center">{{ option.name }}</span>
                    <span class="mt-2 text-sm text-gray-500 text-center">{{ option.description }}</span>
                </Link>
            </div>
        </div>
    </div>
  </AppLayout>
</template>
