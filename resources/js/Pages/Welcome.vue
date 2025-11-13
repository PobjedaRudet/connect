<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';

const redirect = ref('');

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    redirect.value = params.get('redirect') || '';
});

// SVG ikone za sektore (dodane allowedRoles za kontrolu pristupa na UI-u)
const sectors = [
    {
        name: 'Pravna služba',
        description: 'Pravna služba upravlja odnosima s zaposlenicima.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
        link: '/sector/hr',
        allowedRoles: ['Pravna služba']
    },
    {
        name: 'IT služba',
        description: 'IT služba održava svu tehničku infrastrukturu.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h3m4 4v2a4 4 0 01-4 4H7a4 4 0 01-4-4v-2a4 4 0 004-4h3m4-4V7a4 4 0 00-4-4H7a4 4 0 00-4 4v2a4 4 0 004 4h3" /></svg>`,
        link: '/sector/it',
        allowedRoles: ['IT služba']
    },
    {
        name: 'Finansije',
        description: 'Finansije se bave budžetiranjem i računovodstvom kompanije.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3s1.343 3 3 3 3-1.343 3-3-1.343-3-3-3zm0 0V4m0 4v4m0 4v4" /></svg>`,
        link: '/sector/finance',
        allowedRoles: ['Finansije']
    },
    {
        name: 'PPZ i ZNR',
        description: 'PPZ i ZNR sektor brine o zaštiti na radu i protivpožarnoj sigurnosti.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3s1.343 3 3 3 3-1.343 3-3-1.343-3-3-3zm0 0V4m0 4v4m0 4v4" /></svg>`,
        link: '/ppz/dashboard',
        allowedRoles: ['PPZ']
    },
    {
        name: 'Prodaja',
        description: 'Prodaja pokreće prihode kompanije i odnose s klijentima.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17v-2a4 4 0 014-4h10a4 4 0 014 4v2M16 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>`,
        link: '/prodaja/dashboard',
        route: '/prodaja/dashboard', // Dodano za eksplicitno rutiranje
        allowedRoles: ['Šef Komercijale','Direktor Komercijale','Direktor Proizvodnje','Šef Operative','Radnik']
    },
    {
        name: 'Proizvodnja',
        description: 'Proizvodnja nadgleda proizvodnju i operacije.',
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h3m4 4v2a4 4 0 01-4 4H7a4 4 0 01-4-4v-2a4 4 0 014-4h3" /></svg>`,
        link: '/sector/production',
        allowedRoles: ['Direktor Proizvodnje','Šef Operative','Radnik']
    },
];

// Preuzmi korisnika i izračunaj vidljive sektore
const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isAdmin = computed(() => !!user.value?.isadmin);
const userRole = computed(() => user.value?.funkcija ?? '');

const visibleSectors = computed(() => {
    if (isAdmin.value) return sectors;
    return sectors.filter(s => !Array.isArray(s.allowedRoles) || s.allowedRoles.includes(userRole.value));
});
</script>

<template>

    <Head title="Welcome" />
    <div class="min-h-screen flex flex-col bg-gray-50 text-black/50 dark:bg-black dark:text-white/50">
        <!-- Header with logo -->
        <header class="w-full flex items-center justify-between px-6 py-4 bg-white dark:bg-gray-900 shadow">
            <div class="flex items-center">
                <img src="https://pobjeda.com/images/logo-blog.png" alt="Logo" class="h-16 w-auto" />
            </div>
            <div class="flex-1 flex justify-center">
                <span class="text-xl font-bold text-black dark:text-white">Pobjeda-Rudet Connect</span>
            </div>
            <div class="flex">
                <!--  <Link href="/login" class="px-4 py-2 mr-2 rounded bg-[#FF2D20] text-white font-semibold hover:bg-[#e52b1e] transition">Log in</Link>
                <Link href="/register" class="px-4 py-2 rounded border border-[#FF2D20] text-[#FF2D20] font-semibold hover:bg-[#FF2D20] hover:text-white transition">Register</Link> -->
            </div>
        </header>
        <!-- Centered grid with sectors -->
       <main class="flex-1 flex items-center justify-center">
    <div class="grid gap-8 lg:grid-cols-3 md:grid-cols-2 sm:grid-cols-1">
        <div v-for="sector in visibleSectors" :key="sector.name"
            class="bg-white dark:bg-gray-900 rounded shadow p-8 flex flex-col items-center hover:shadow-lg transition">
            <Link :href="sector.link" class="flex flex-col items-center group">
                <span v-html="sector.icon"></span>
                <span class="mt-4 text-lg font-semibold group-hover:text-[#FF2D20] transition">{{ sector.name }}</span>
                <span class="mt-2 text-sm text-gray-500 dark:text-gray-400 text-center">{{ sector.description }}</span>
            </Link>
        </div>
    </div>
</main>
    </div>
</template>
