<template>
    <AppLayout title="Nadolazeći pregledi">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Nadolazeći pregledi
            </h2>
        </template>
        <div class="p-6 flex justify-center">
            <div class="w-full max-w-4xl">
                <h2 class="text-lg font-semibold mb-4">📅 Nadolazeći pregledi za tekući mjesec</h2>
                <div class="flex justify-between items-center mb-4">
                    <input v-model="search" type="text" placeholder="Pretraži po imenu ili prezimenu..."
                        class="border rounded px-3 py-2 w-72" />
                </div>
                <div class="overflow-x-auto rounded-lg shadow mb-8 bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-4 py-3"></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    Zaposleni</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    SAP Id</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    Pozicija</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    Datum pregleda</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr v-for="(item, index) in sortedUpcoming" :key="'upcoming-' + index"
                                :class="{ 'bg-blue-100': selectedUpcoming.includes(index), 'hover:bg-gray-50': !selectedUpcoming.includes(index) }"
                                class="transition">
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" :checked="selectedUpcoming.includes(index)"
                                        @change="onCheckboxChange(item.employee.empID, index)"
                                        class="form-checkbox h-4 w-4 text-blue-600" />
                                </td>
                                <td class="px-6 py-4 text-center">{{ index + 1 }}</td>
                                <td class="px-6 py-4">{{ item.employee.firstName }} {{ item.employee.lastName }}</td>
                                <td class="px-6 py-4">{{ item.employee.empID }}</td>
                                <td class="px-6 py-4">{{ item.employee.radno_mjesto }}</td>
                                <td class="px-6 py-4 text-red-600 text-center font-semibold">{{ formatDate(item.next_due) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex justify-end p-4">
                        <button
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded shadow transition"
                            @click="showPopup = true">
                            Ažuriraj preglede
                        </button>
                    </div>
                </div>

                <h2 class="text-lg font-semibold text-red-700 mb-4">⛔ Istekli pregledi (propušteni rokovi)</h2>
                <div class="overflow-x-auto rounded-lg shadow bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-red-50">
                                <th class="px-4 py-3"></th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    Zaposleni</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    SAP Id</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    Pozicija</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    Pregled je trebao biti do</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <tr v-for="(item, index) in sortedExpired" :key="'expired-' + index"
                                :class="{ 'bg-red-100': selectedExpired.includes(index), 'hover:bg-red-50': !selectedExpired.includes(index) }"
                                class="transition">
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" :checked="selectedUpcoming.includes(index)"
                                        @change="onCheckboxChange(item.employee.empID, index)"
                                        class="form-checkbox h-4 w-4 text-blue-600" />
                                </td>
                                <td class="px-6 py-4 text-center">{{ index + 1 }}</td>
                                <td class="px-6 py-4">{{ item.employee.firstName }} {{ item.employee.lastName }}</td>
                                <td class="px-6 py-4">{{ item.employee.empID }}</td>
                                <td class="px-6 py-4">{{ item.employee.radno_mjesto }}</td>
                                <td class="px-6 py-4 text-red-600 text-center font-semibold">{{ formatDate(item.next_due) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Popup -->
            <div v-if="showPopup" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50">
                <div class="bg-white rounded-lg shadow-lg p-8 max-w-sm w-full">
                    <h3 class="text-lg font-semibold mb-4">Ažuriraj preglede</h3>
                    <p class="mb-4">Jeste li sigurni da želite ažurirati odabrane preglede?</p>
                    <form @submit.prevent="azurirajPreglede">
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Datum kontrolnog pregleda</label>
                            <input type="date" v-model="form.datum" class="form-input w-full" required />
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">TIP</label>
                            <select v-model="form.tip" class="form-select w-full" required>
                                <option value="Sposoban">Sposoban</option>
                                <option value="Nesposoban">Nesposoban</option>
                                <option value="Ograničen">Ograničen</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Kontrolni pregled</label>
                            <select v-model="form.kontrolni" class="form-select w-full" required>
                                <option value="1">Da</option>
                                <option value="0">Ne</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Komentar</label>
                            <textarea v-model="form.komentar" class="form-textarea w-full" rows="2"></textarea>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-1">Ustanova</label>
                            <select v-model="form.ustanova" @change="onUstanovaChange" class="form-select w-full" required>
                                <option value="J.U. Dom zdravlja 'Dr.Isak Samokovlija' Goražde">J.U. Dom zdravlja "Dr.Isak Samokovlija" Goražde</option>
                                  <option value="J.U. Dom zdravlja 'Dr.Isak Samokovlija' Goražde">PZU "Eurofarm-Centar Poliklinika" PJ Goražde</option>
                                  <option value="custom">Drugo (upišite ručno)</option>
                            </select>
                            <input v-if="showCustomUstanova" type="text" v-model="form.ustanova" placeholder="Unesite naziv ustanove" class="form-input w-full mt-2" required />
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded"
                                @click="showPopup = false">
                                Odustani
                            </button>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                Potvrdi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    upcoming: Array,
    expired: Array
});

const selectedUpcoming = ref([]);
const selectedExpired = ref([]);
const showPopup = ref(false);

const form = ref({
    datum: '',
    tip: '',
    kontrolni: '',
    komentar: '',
    ustanova: ''
});

const search = ref('');

const filteredUpcoming = computed(() => {
    if (!search.value) return props.upcoming;
    return props.upcoming.filter(item => {
        const imePrezime = `${item.employee.firstName} ${item.employee.lastName}`.toLowerCase();
        return imePrezime.includes(search.value.toLowerCase());
    });
});
const filteredExpired = computed(() => {
    if (!search.value) return props.expired;
    return props.expired.filter(item => {
        const imePrezime = `${item.employee.firstName} ${item.employee.lastName}`.toLowerCase();
        return imePrezime.includes(search.value.toLowerCase());
    });
});

// Dodaj redni_broj u computed sortirane liste
const sortedUpcoming = computed(() => {
    return [...filteredUpcoming.value].sort((a, b) => {
        const aBroj = a.redni_broj ?? 99999;
        const bBroj = b.redni_broj ?? 99999;
        return aBroj - bBroj;
    });
});
const sortedExpired = computed(() => {
    return [...filteredExpired.value].sort((a, b) => {
        const aBroj = a.redni_broj ?? 99999;
        const bBroj = b.redni_broj ?? 99999;
        return aBroj - bBroj;
    });
});

const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('bs-BA');
};

const azurirajPreglede = async () => {
    try {
        await axios.post('/api/pregledi/azuriraj', {
            ids: selectedUpcoming.value,
            ...form.value
        });
        showPopup.value = false;
        alert('Pregledi su ažurirani!');
        await fetchPregledi(); // Osvježi podatke
    } catch (e) {
        alert('Greška pri ažuriranju!');
    }
};

/* const onCheckboxChange = (empID, index) => {
    alert('empID: ' + empID);
    toggleUpcoming(index); // pozovi postojeću funkciju za selektovanje
}; */
const onCheckboxChange = (empID) => {
    if (selectedUpcoming.value.includes(empID)) {
        selectedUpcoming.value = selectedUpcoming.value.filter(id => id !== empID);
    } else {
        selectedUpcoming.value.push(empID);
    }
};
const toggleUpcoming = (index) => {
    if (selectedUpcoming.value.includes(index)) {
        selectedUpcoming.value = selectedUpcoming.value.filter(i => i !== index);
    } else {
        selectedUpcoming.value.push(index);
    }
};

const fetchPregledi = async () => {
    const { data } = await axios.get('/api/pregledi');
    // Pretpostavljamo da API vraća { upcoming: [...], expired: [...] }
    props.upcoming.splice(0, props.upcoming.length, ...data.upcoming);
    props.expired.splice(0, props.expired.length, ...data.expired);
};

const showCustomUstanova = ref(false);

function onUstanovaChange(e) {
    if (e.target.value === 'custom') {
        showCustomUstanova.value = true;
        form.value.ustanova = '';
    } else {
        showCustomUstanova.value = false;
        form.value.ustanova = e.target.value;
    }
}
</script>
