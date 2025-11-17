<script setup>
import { computed } from 'vue';
import { Link, Head, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page?.props?.auth?.user ?? null);
const userName = computed(() => user.value?.name ?? '');
const userFunkcija = computed(() => user.value?.funkcija ?? null);
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

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head title="Prodaja" />
    <div class="min-h-screen bg-gradient-to-b from-gray-50 to-white dark:from-black dark:to-gray-900">
        <!-- Top bar -->
        <div class="w-full border-b border-gray-200 dark:border-gray-800 bg-white/80 dark:bg-gray-900/70 backdrop-blur">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/" class="text-sm px-3 py-1.5 rounded border border-gray-300 text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Početna</Link>
                    <span class="text-sm text-gray-400">/</span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Prodaja</span>
                </div>
                <div class="flex items-center gap-3">
                    <span v-if="userName" class="hidden sm:inline text-sm text-gray-600 dark:text-gray-300">{{ userName }} <span v-if="userFunkcija" class="opacity-70">— {{ userFunkcija }}</span></span>
                    <button @click="logout" class="px-3 py-1.5 rounded bg-gray-900 text-white hover:bg-black dark:bg-gray-700 dark:hover:bg-gray-600">Odjava</button>
                </div>
            </div>
        </div>

        <!-- Heading -->
        <div class="max-w-6xl mx-auto px-4">
            <div class="py-8">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Prodaja — Brzi pristup</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Odaberite jednu od opcija ispod za rad na nalozima, kupcima i proizvodima.</p>
            </div>

            <!-- Cards -->
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="option in options" :key="option.name" :href="option.link"
                      class="group relative rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 shadow-sm hover:shadow-md transition transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 h-12 w-12 rounded-full bg-blue-50 dark:bg-blue-900/20 ring-1 ring-blue-100 dark:ring-blue-800/60 flex items-center justify-center">
                            <span v-html="option.icon"></span>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 truncate">{{ option.name }}</h3>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 group-hover:text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ option.description }}</p>
                        </div>
                    </div>
                </Link>
            </div>

            <div class="h-10"></div>
        </div>
    </div>

</template>
