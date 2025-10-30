<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};

const page = usePage();
const userFunkcija = computed(() => (page?.props?.auth?.user?.funkcija ?? null));

const pendingApprovalsCount = ref(0);
async function refreshPending() {
    if (userFunkcija.value && userFunkcija.value !== 'Radnik') {
        try {
            const { data } = await axios.get('/approvals/pending?summary=1');
            pendingApprovalsCount.value = typeof data?.count === 'number' ? data.count : 0;
        } catch (e) {
            pendingApprovalsCount.value = 0;
        }
    } else {
        pendingApprovalsCount.value = 0;
    }
}

onMounted(() => {
    refreshPending();
});
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-100">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">


                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex items-center">
                                <!-- Dashboard (always visible) -->
                                <NavLink href="/prodaja/dashboard" :active="$page.url && $page.url.startsWith('/prodaja/dashboard')">
                                    Dashboard
                                </NavLink>
                                <NavLink v-if="userFunkcija==='Radnik' || userFunkcija==='Šef Komercijale'" :href="route('nalozi.za-proizvodnju')" :active="route().current('nalozi.za-proizvodnju')">
                                    Kreiraj nalog
                                </NavLink>
                                <NavLink v-if="userFunkcija==='Radnik' || userFunkcija==='Šef Komercijale'" :href="route('nalozi.kreirani')" :active="route().current('nalozi.kreirani')">
                                    Kreirani nalozi
                                </NavLink>
                                <NavLink v-if="userFunkcija==='Radnik'" :href="route('nalozi.radnik.odobreni')" :active="route().current('nalozi.radnik.odobreni')">
                                    Status naloga
                                </NavLink>
                                <NavLink v-if="userFunkcija && userFunkcija!=='Radnik'"
                                         :href="userFunkcija==='Direktor Komercijale' ? route('approvals.director.sales') : (userFunkcija==='Direktor Proizvodnje' ? route('approvals.director.production') : (userFunkcija==='Šef Operative' ? route('approvals.chief.operations') : route('approvals.mine')))"
                                         :active="route().current('approvals.mine') || route().current('approvals.director.sales') || route().current('approvals.director.production') || route().current('approvals.chief.operations')">
                                    <span>Odobrenja</span>
                                    <span v-if="pendingApprovalsCount>0" class="ml-2 inline-flex items-center justify-center text-xs font-semibold rounded-full bg-red-600 text-white px-2 py-0.5">
                                        {{ pendingApprovalsCount }}
                                    </span>
                                </NavLink>
                                <NavLink v-if="userFunkcija && userFunkcija!=='Radnik'" :href="route('orders.status')" :active="route().current('orders.status')">
                                    Status naloga
                                </NavLink>

                                <NavLink v-if="userFunkcija==='Direktor Proizvodnje'" :href="route('planning.index')" :active="route().current('planning.index')">
                                    Planiranje
                                </NavLink>
                                <NavLink v-if="userFunkcija==='Direktor Proizvodnje'" :href="route('planning.gantt')" :active="route().current('planning.gantt')">
                                    Gantt
                                </NavLink>
                                <NavLink v-if="userFunkcija==='Direktor Proizvodnje'" :href="route('planning.holidays.index')" :active="route().current('planning.holidays.index')">
                                    Praznici
                                </NavLink>

                                <!-- Izvještaji dropdown (samo Direktor Komercijale) -->
                                <Dropdown v-if="userFunkcija==='Direktor Komercijale'" align="left" width="56">
                                    <template #trigger>
                                        <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-600 bg-white hover:text-gray-800 focus:outline-none focus:bg-gray-50 transition">
                                            Izvještaji
                                            <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <DropdownLink :href="route('reports.customers')" :class="{ 'font-semibold': route().current('reports.customers') }">
                                            Po kupcima
                                        </DropdownLink>
                                        <DropdownLink :href="route('reports.products')" :class="{ 'font-semibold': route().current('reports.products') }">
                                            Po proizvodima
                                        </DropdownLink>
                                        <DropdownLink :href="route('reports.monthly')" :class="{ 'font-semibold': route().current('reports.monthly') }">
                                            Mjesečni
                                        </DropdownLink>
                                        <DropdownLink :href="route('reports.yearly')" :class="{ 'font-semibold': route().current('reports.yearly') }">
                                            Godišnji
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <div class="ms-3 relative">
                                <!-- Teams Dropdown -->
                                <Dropdown v-if="$page.props.jetstream.hasTeamFeatures" align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.current_team.name }}

                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-60">
                                            <!-- Team Management -->
                                            <div class="block px-4 py-2 text-xs text-gray-400">
                                                Manage Team
                                            </div>

                                            <!-- Team Settings -->
                                            <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">
                                                Team Settings
                                            </DropdownLink>

                                            <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">
                                                Create New Team
                                            </DropdownLink>

                                            <!-- Team Switcher -->
                                            <template v-if="$page.props.auth.user.all_teams.length > 1">
                                                <div class="border-t border-gray-200" />

                                                <div class="block px-4 py-2 text-xs text-gray-400">
                                                    Switch Teams
                                                </div>

                                                <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                                    <form @submit.prevent="switchToTeam(team)">
                                                        <DropdownLink as="button">
                                                            <div class="flex items-center">
                                                                <svg v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>

                                                                <div>{{ team.name }}</div>
                                                            </div>
                                                        </DropdownLink>
                                                    </form>
                                                </template>
                                            </template>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button v-if="$page.props.auth && $page.props.auth.user && $page.props.jetstream.managesProfilePhotos" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition">
                                            <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else-if="$page.props.auth && $page.props.auth.user" class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </button>
                                        </span>
                                        <span v-else class="inline-flex rounded-md text-gray-400 px-3 py-2">Gost</span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            Manage Account
                                        </div>

                                        <DropdownLink v-if="$page.props.auth?.user?.isadmin" :href="route('admin.page-access')">
                                            Admin: Pristup stranicama
                                        </DropdownLink>

                                        <DropdownLink :href="route('profile.show')">
                                            Profile
                                        </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            API Tokens
                                        </DropdownLink>

                                        <!-- Quick link to Dashboard -->
                                        <DropdownLink href="/prodaja/dashboard">
                                            Dashboard
                                        </DropdownLink>

                                        <div class="border-t border-gray-200" />

                                        <!-- Authentication -->
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <svg
                                    class="size-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <!-- Dashboard at the top on mobile -->
                        <ResponsiveNavLink href="/prodaja/dashboard" :active="$page.url && $page.url.startsWith('/prodaja/dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="userFunkcija==='Radnik' || userFunkcija==='Šef Komercijale'" :href="route('nalozi.za-proizvodnju')" :active="route().current('nalozi.za-proizvodnju')">
                            Kreiraj nalog
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="userFunkcija==='Radnik' || userFunkcija==='Šef Komercijale'" :href="route('nalozi.kreirani')" :active="route().current('nalozi.kreirani')">
                            Kreirani nalozi
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="userFunkcija==='Radnik'" :href="route('nalozi.radnik.odobreni')" :active="route().current('nalozi.radnik.odobreni')">
                            Status naloga
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="userFunkcija && userFunkcija!=='Radnik'"
                                           :href="userFunkcija==='Direktor Komercijale' ? route('approvals.director.sales') : (userFunkcija==='Direktor Proizvodnje' ? route('approvals.director.production') : (userFunkcija==='Šef Operative' ? route('approvals.chief.operations') : route('approvals.mine')))"
                                           :active="route().current('approvals.mine') || route().current('approvals.director.sales') || route().current('approvals.director.production') || route().current('approvals.chief.operations')">
                            <span>Odobrenja</span>
                            <span v-if="pendingApprovalsCount>0" class="ml-2 inline-flex items-center justify-center text-xs font-semibold rounded-full bg-red-600 text-white px-2 py-0.5">
                                {{ pendingApprovalsCount }}
                            </span>
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="userFunkcija && userFunkcija!=='Radnik'" :href="route('orders.status')" :active="route().current('orders.status')">
                            Status naloga
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="userFunkcija==='Direktor Proizvodnje'" :href="route('planning.gantt')" :active="route().current('planning.gantt')">
                            Gantt
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="userFunkcija==='Direktor Proizvodnje'" :href="route('planning.holidays.index')" :active="route().current('planning.holidays.index')">
                            Praznici
                        </ResponsiveNavLink>


                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.auth && $page.props.auth.user && $page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="size-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800">
                                    {{ $page.props.auth && $page.props.auth.user ? $page.props.auth.user.name : 'Gost' }}
                                </div>
                                <div class="font-medium text-sm text-gray-500">
                                    {{ $page.props.auth && $page.props.auth.user ? $page.props.auth.user.email : '' }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink v-if="$page.props.auth?.user?.isadmin" :href="route('admin.page-access')">
                                Admin: Pristup stranicama
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Profile
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                                API Tokens
                            </ResponsiveNavLink>

                            <!-- Quick link to Dashboard -->
                            <ResponsiveNavLink href="/prodaja/dashboard">
                                Dashboard
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                Log Out
                            </ResponsiveNavLink>

                            <!-- Team Management -->
                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                <div class="border-t border-gray-200" />

                                <div class="block px-4 py-2 text-xs text-gray-400">
                                    Manage Team
                                </div>

                                <!-- Team Settings -->
                                <ResponsiveNavLink :href="route('teams.show', $page.props.auth.user.current_team)" :active="route().current('teams.show')">
                                    Team Settings
                                </ResponsiveNavLink>

                                <ResponsiveNavLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" :active="route().current('teams.create')">
                                    Create New Team
                                </ResponsiveNavLink>

                                <!-- Team Switcher -->
                                <template v-if="$page.props.auth.user.all_teams.length > 1">
                                    <div class="border-t border-gray-200" />

                                    <div class="block px-4 py-2 text-xs text-gray-400">
                                        Switch Teams
                                    </div>

                                    <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                        <form @submit.prevent="switchToTeam(team)">
                                            <ResponsiveNavLink as="button">
                                                <div class="flex items-center">
                                                    <svg v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <div>{{ team.name }}</div>
                                                </div>
                                            </ResponsiveNavLink>
                                        </form>
                                    </template>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
