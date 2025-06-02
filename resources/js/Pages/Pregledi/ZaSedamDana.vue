<template>
    <div class="p-6 flex justify-center">
        <div class="w-full max-w-4xl">
            <h2 class="text-lg font-semibold mb-4">📅 Nadolazeći pregledi (u narednih 7 dana)</h2>
            <div class="overflow-x-auto rounded-lg shadow mb-8 bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Zaposleni</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Pozicija</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Datum pregleda</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr v-for="(item, index) in upcoming" :key="'upcoming-' + index" class="hover:bg-gray-50 transition">
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Zaposleni</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Pozicija</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Pregled je trebao biti do</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr v-for="(item, index) in expired" :key="'expired-' + index" class="hover:bg-red-50 transition">
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
defineProps({
  upcoming: Array,
  expired: Array
});

const formatDate = (dateStr) => {
  const date = new Date(dateStr);
  return date.toLocaleDateString('bs-BA');
};
</script>
