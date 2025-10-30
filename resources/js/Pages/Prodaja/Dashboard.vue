<script setup>
import { computed } from 'vue';
import { Link, Head, usePage } from '@inertiajs/vue3';

const page = usePage();
const userFunkcija = computed(() => page?.props?.auth?.user?.funkcija ?? null);
const ordersLink = computed(() => {
    if (!userFunkcija.value) return '/nalozi/nalozi-za-proizvodnju';
    if (userFunkcija.value === 'Direktor Komercijale') return '/odobrenja/direktor-komercijale';
    if (userFunkcija.value === 'Direktor Proizvodnje') return '/odobrenja/direktor-proizvodnje';
    if (userFunkcija.value !== 'Radnik') return '/odobrenja/moja';
    return '/nalozi/nalozi-za-proizvodnju';
});

const options = computed(() => {
    const items = [];
    // Nalozi - glavna kartica vodi na odobrenja ili formu za kreiranje ovisno o ulozi
    items.push({
        name: 'Nalozi',
        description: 'Pregled i rad sa nalozima za proizvodnju.',
        link: ordersLink.value,
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" /></svg>`
    });

    // Dodatne kratice za kreiranje i listu kreiranih ako je Radnik ili Šef Komercijale
    if (userFunkcija.value === 'Radnik' || userFunkcija.value === 'Šef Komercijale') {
        items.push({
            name: 'Kreiraj nalog',
            description: 'Unos novog naloga za proizvodnju.',
            link: '/nalozi/nalozi-za-proizvodnju',
            icon: `<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-10 w-10 text-emerald-600\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M12 4v16m8-8H4\" /></svg>`
        });
        items.push({
            name: 'Kreirani nalozi',
            description: 'Lista naloga koje ste kreirali.',
            link: '/nalozi/kreirani',
            icon: `<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-10 w-10 text-indigo-600\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M3 7h18M3 12h18M3 17h18\" /></svg>`
        });
    }

    // Ostale postojeće PPZ kartice (ostavljene radi kontinuiteta)
    items.push({
        name: 'Godišnji odmori',
        description: 'Upravljanje i pregled godišnjih odmora radnika.',
        link: '/ppz/godisnji-odmori',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>`
    });
    items.push({
        name: 'Obuka radnika',
        description: 'Evidencija i planiranje obuka za radnike.',
        link: '/ppz/obuka-radnika',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m0 0H6m6 0h6" /></svg>`
    });
    items.push({
        name: 'Proizvodi',
        description: 'Pregled i upravljanje radnicima na rizičnim radnim mjestima.',
        link: '/ppz/radnici-rizik',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" /></svg>`
    });

    return items;
});
</script>

<template>
    <Head title="Prodaja Dashboard" />
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 dark:bg-black">
        <h1 class="text-2xl font-bold mb-8 text-center text-black dark:text-white">Prodaja Dashboard</h1>
        <div class="grid gap-8 lg:grid-cols-2 md:grid-cols-2 sm:grid-cols-1">
            <div
                v-for="option in options"
                :key="option.name"
                class="bg-white dark:bg-gray-900 rounded shadow p-8 flex flex-col items-center hover:shadow-lg transition"
            >
                <Link :href="option.link" class="flex flex-col items-center group w-full">
                    <span v-html="option.icon"></span>
                    <span class="mt-4 text-lg font-semibold group-hover:text-[#FF2D20] transition text-center">{{ option.name }}</span>
                    <span class="mt-2 text-sm text-gray-500 dark:text-gray-400 text-center">{{ option.description }}</span>
                </Link>
            </div>
        </div>
    </div>
</template>
