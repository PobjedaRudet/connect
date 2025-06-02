<template>
    <div class="p-6 flex justify-center">
        <div class="w-full max-w-4xl">
            <h2 class="text-lg font-semibold mb-4">📅 Nadolazeći pregledi (u narednih 7 dana)</h2>
            <div class="overflow-x-auto rounded-lg shadow mb-8 bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-3"></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Zaposleni</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Pozicija</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Datum pregleda</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr
                            v-for="(item, index) in upcoming"
                            :key="'upcoming-' + index"
                            :class="{'bg-blue-100': selectedUpcoming.includes(index), 'hover:bg-gray-50': !selectedUpcoming.includes(index)}"
                            class="transition"
                        >
                            <td class="px-4 py-4 text-center">
                                <input
                                    type="checkbox"
                                    :checked="selectedUpcoming.includes(index)"
                                    @change="toggleUpcoming(index)"
                                    class="form-checkbox h-4 w-4 text-blue-600"
                                />
                            </td>
                            <td class="px-6 py-4 text-center">{{ index + 1 }}</td>
                            <td class="px-6 py-4">{{ item.employee.firstName }} {{ item.employee.lastName }}</td>
                            <td class="px-6 py-4">{{ item.employee.radno_mjesto }}</td>
                            <td class="px-6 py-4 text-red-600 text-center font-semibold">{{ formatDate(item.next_due) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2 class="text-lg font-semibold text-red-700 mb-4">⛔ Istekli pregledi (propušteni rokovi)</h2>
            <div class="overflow-x-auto rounded-lg shadow bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-red-50">
                            <th class="px-4 py-3"></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Zaposleni</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Pozicija</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Pregled je trebao biti do</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr
                            v-for="(item, index) in expired"
                            :key="'expired-' + index"
                            :class="{'bg-red-100': selectedExpired.includes(index), 'hover:bg-red-50': !selectedExpired.includes(index)}"
                            class="transition"
                        >
                            <td class="px-4 py-4 text-center">
                                <input
                                    type="checkbox"
                                    :checked="selectedExpired.includes(index)"
                                    @change="toggleExpired(index)"
                                    class="form-checkbox h-4 w-4 text-red-600"
                                />
                            </td>
                            <td class="px-6 py-4 text-center">{{ index + 1 }}</td>
                            <td class="px-6 py-4">{{ item.employee.firstName }} {{ item.employee.lastName }}</td>
                            <td class="px-6 py-4">{{ item.employee.radno_mjesto }}</td>
                            <td class="px-6 py-4 text-red-600 text-center font-semibold">{{ formatDate(item.next_due) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  upcoming: Array,
  expired: Array
});

const selectedUpcoming = ref([]);
const selectedExpired = ref([]);

const toggleUpcoming = (index) => {
  const pos = selectedUpcoming.value.indexOf(index);
  if (pos === -1) {
    selectedUpcoming.value.push(index);
  } else {
    selectedUpcoming.value.splice(pos, 1);
  }
};

const toggleExpired = (index) => {
  const pos = selectedExpired.value.indexOf(index);
  if (pos === -1) {
    selectedExpired.value.push(index);
  } else {
    selectedExpired.value.splice(pos, 1);
  }
};

const formatDate = (dateStr) => {
  const date = new Date(dateStr);
  return date.toLocaleDateString('bs-BA');
};
</script>
