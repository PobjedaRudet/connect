<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const now = ref(new Date());
const stats = ref({ totalEmployees: 0, currentlyWorking: 0, checkedOutToday: 0, totalCheckedInToday: 0, activePasses: 0, percentage: 0 });
const recentRecords = ref([]);
const passes = ref([]);

let clockInterval = null;
let dataInterval = null;

function updateClock() { now.value = new Date(); }

async function fetchData() {
    try {
        const { data } = await axios.get('/kapija/data');
        stats.value = data.stats;
        recentRecords.value = data.recentRecords;
        passes.value = data.passes;
    } catch (e) { console.error('Kapija fetch error', e); }
}

onMounted(() => {
    fetchData();
    clockInterval = setInterval(updateClock, 1000);
    dataInterval = setInterval(fetchData, 3000);
});
onUnmounted(() => { clearInterval(clockInterval); clearInterval(dataInterval); });

const formattedDate = computed(() => {
    const d = now.value;
    const days = ['Nedjelja','Ponedjeljak','Utorak','Srijeda','Četvrtak','Petak','Subota'];
    const months = ['januar','februar','mart','april','maj','juni','juli','august','septembar','oktobar','novembar','decembar'];
    return `${days[d.getDay()]}, ${d.getDate()}. ${months[d.getMonth()]} ${d.getFullYear()}.`;
});
const formattedTime = computed(() => now.value.toLocaleTimeString('hr-HR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));

// SVG circular progress
const radius = 54;
const circumference = 2 * Math.PI * radius;
const strokeDashoffset = computed(() => circumference - (stats.value.percentage / 100) * circumference);
const progressColor = computed(() => {
    const p = stats.value.percentage;
    if (p >= 75) return '#22c55e';
    if (p >= 40) return '#eab308';
    return '#ef4444';
});
</script>

<template>
    <Head title="Kapija" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-950 p-4 md:p-6">
        <!-- Header -->
        <div class="max-w-7xl mx-auto mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Kapija — Pregled</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Praćenje prijava, odjava i izlaznica u realnom vremenu</p>
            </div>
            <a href="/" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline dark:text-blue-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Početna
            </a>
        </div>

        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Row 1: Stats cards -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Sat i Datum -->
                <div class="col-span-2 lg:col-span-1 bg-white dark:bg-gray-800 rounded-xl shadow p-5 flex flex-col items-center justify-center">
                    <div class="text-3xl font-bold text-gray-800 dark:text-white tabular-nums tracking-tight">{{ formattedTime }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-center">{{ formattedDate }}</div>
                </div>

                <!-- Circular gauge: Procenat prisutnosti -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5 flex flex-col items-center justify-center">
                    <svg width="120" height="120" viewBox="0 0 120 120" class="mb-1">
                        <circle cx="60" cy="60" :r="radius" fill="none" stroke="#e5e7eb" stroke-width="10" class="dark:stroke-gray-700" />
                        <circle cx="60" cy="60" :r="radius" fill="none" :stroke="progressColor" stroke-width="10"
                            stroke-linecap="round" :stroke-dasharray="circumference" :stroke-dashoffset="strokeDashoffset"
                            transform="rotate(-90 60 60)" class="transition-all duration-700" />
                        <text x="60" y="56" text-anchor="middle" class="fill-gray-800 dark:fill-white text-2xl font-bold" dominant-baseline="middle">
                            {{ stats.percentage }}%
                        </text>
                        <text x="60" y="75" text-anchor="middle" class="fill-gray-400 text-[10px]">prisutnost</text>
                    </svg>
                </div>

                <!-- Trenutno prijavljeno -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5 flex flex-col items-center justify-center">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trenutno radi</span>
                    </div>
                    <div class="text-4xl font-bold text-green-600 dark:text-green-400">{{ stats.currentlyWorking }}</div>
                    <div class="text-xs text-gray-400 mt-1">od {{ stats.totalEmployees }} uposlenika</div>
                </div>

                <!-- Odjavljeno danas -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5 flex flex-col items-center justify-center">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-3 h-3 rounded-full bg-red-400"></span>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Odjavljeno</span>
                    </div>
                    <div class="text-4xl font-bold text-red-500 dark:text-red-400">{{ stats.checkedOutToday }}</div>
                    <div class="text-xs text-gray-400 mt-1">danas ukupno</div>
                </div>

                <!-- Aktivne izlaznice -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-5 flex flex-col items-center justify-center">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-3 h-3 rounded-full bg-yellow-400"></span>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Izlaznice</span>
                    </div>
                    <div class="text-4xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.activePasses }}</div>
                    <div class="text-xs text-gray-400 mt-1">aktivne sada</div>
                </div>
            </div>

            <!-- Row 2: Horizontal bar -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow px-5 py-3">
                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                    <span>Prijavljeni / Ukupno uposlenika</span>
                    <span class="font-medium text-gray-700 dark:text-gray-200">{{ stats.currentlyWorking }} / {{ stats.totalEmployees }}</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                    <div class="h-3 rounded-full transition-all duration-700"
                        :style="{ width: stats.percentage + '%', backgroundColor: progressColor }"></div>
                </div>
            </div>

            <!-- Row 3: Tables -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Panel: Zadnjih 10 prijava/odjava -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow">
                    <div class="px-5 py-4 border-b dark:border-gray-700">
                        <h2 class="text-base font-semibold text-gray-800 dark:text-white">Zadnjih 10 prijava / odjava</h2>
                    </div>
                    <div class="p-5">
                        <div v-if="recentRecords.length === 0" class="text-gray-400 text-sm text-center py-4">Nema podataka za danas.</div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <th class="pb-3">Uposlenik</th>
                                    <th class="pb-3">Vrijeme</th>
                                    <th class="pb-3 text-right">Tip</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in recentRecords" :key="r.id" class="border-t dark:border-gray-700">
                                    <td class="py-2.5 text-gray-800 dark:text-gray-200 font-medium">{{ r.employee }}</td>
                                    <td class="py-2.5 text-gray-500 dark:text-gray-400 tabular-nums">{{ r.time }}</td>
                                    <td class="py-2.5 text-right">
                                        <span :class="r.type === 'prijava'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'"
                                            class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                            {{ r.type }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Panel: Izlaznice danas -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow">
                    <div class="px-5 py-4 border-b dark:border-gray-700">
                        <h2 class="text-base font-semibold text-gray-800 dark:text-white">Izlaznice danas</h2>
                    </div>
                    <div class="p-5">
                        <div v-if="passes.length === 0" class="text-gray-400 text-sm text-center py-4">Nema izlaznica za danas.</div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <th class="pb-3">Uposlenik</th>
                                    <th class="pb-3">Izlaz</th>
                                    <th class="pb-3">Povratak</th>
                                    <th class="pb-3 text-right">Tip</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in passes" :key="p.id" class="border-t dark:border-gray-700">
                                    <td class="py-2.5 text-gray-800 dark:text-gray-200 font-medium">{{ p.employee }}</td>
                                    <td class="py-2.5 text-gray-500 dark:text-gray-400 tabular-nums">{{ p.start_time }}</td>
                                    <td class="py-2.5 text-gray-500 dark:text-gray-400 tabular-nums">{{ p.end_time || '—' }}</td>
                                    <td class="py-2.5 text-right">
                                        <span :class="p.type === 'privatni'
                                            ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300'
                                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300'"
                                            class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                            {{ p.type }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
