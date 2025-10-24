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

const options = computed(() => [
    {
        name: 'Nalozi',
        description: 'Kreiranje i pregled naloga za proizvodnju.',
        link: ordersLink.value,
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" /></svg>`
    },
    {
        name: 'Kupci',
        description: 'Kreiranje, pregled i uređivanje kupaca.',
        link: '/kupci',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-3m-4 6H2v-2a4 4 0 014-4h3m6-4a4 4 0 11-8 0 4 4 0 018 0z" /></svg>`
    },
    {
        name: 'Proizvodi',
        description: 'Pregled i upravljanje proizvodima.',
        link: '/proizvodi',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7l9-4 9 4-9 4-9-4zm0 8l9 4 9-4M3 7v8" /></svg>`
    },
]);
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
