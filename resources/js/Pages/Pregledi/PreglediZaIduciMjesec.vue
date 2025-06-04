<template>
    <AppLayout title="Nadolazeći pregledi">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Nadolazeći pregledi
            </h2>
        </template>
        <div class="p-6 flex justify-center">
            <div class="w-full max-w-4xl">
                <h2 class="text-lg font-semibold mb-4">📅 Nadolazeći pregledi za idući mjesec</h2>
                <div class="overflow-x-auto rounded-lg shadow mb-8 bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-100">

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
                            <tr v-for="(item, index) in upcoming" :key="'upcoming-' + index"
                                :class="{ 'bg-blue-100': selectedUpcoming.includes(index), 'hover:bg-gray-50': !selectedUpcoming.includes(index) }"
                                class="transition">

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

                    </div>
                </div>

                <h2 class="text-lg font-semibold text-red-700 mb-4">⛔ Istekli pregledi (propušteni rokovi)</h2>
                <div class="overflow-x-auto rounded-lg shadow bg-white">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-red-50">

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
                            <tr v-for="(item, index) in expired" :key="'expired-' + index"
                                :class="{ 'bg-red-100': selectedExpired.includes(index), 'hover:bg-red-50': !selectedExpired.includes(index) }"
                                class="transition">

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
                            <input type="text" v-model="form.ustanova" class="form-input w-full" required />
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
import { ref } from 'vue';
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

const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}.${month}.${year}`;
};

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
</script>
