<template>
  <div class="p-6">
    <h2 class="text-lg font-semibold mb-4">📅 Nadolazeći pregledi (u narednih 7 dana)</h2>
    <table class="table-auto w-full border mb-8">
      <thead>
        <tr class="bg-gray-200">
          <th class="border px-4 py-2">Zaposleni</th>
          <th class="border px-4 py-2">Pozicija</th>
          <th class="border px-4 py-2">Datum pregleda</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, index) in upcoming" :key="'upcoming-' + index">
          <td class="border px-4 py-2">{{ item.employee.firstName }} {{ item.employee.lastName }}</td>
               <td class="border px-4 py-2">{{ item.employee.radno_mjesto}} </td>
          <td class="border px-4 py-2 text-red-600 text-center">{{ formatDate(item.next_due) }}</td>
        </tr>
      </tbody>
    </table>

    <h2 class="text-lg font-semibold text-red-700 mb-4">⛔ Istekli pregledi (propušteni rokovi)</h2>
    <table class="table-auto w-full border">
      <thead>
        <tr class="bg-red-100">
          <th class="border px-4 py-2">Zaposleni</th>
          <th class="border px-4 py-2">Pozicija</th>
          <th class="border px-4 py-2">Pregled je trebao biti do</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(item, index) in expired" :key="'expired-' + index">
          <td class="border px-4 py-2">{{ item.employee.firstName }} {{ item.employee.lastName }}</td>
          <td class="border px-4 py-2">{{ item.employee.radno_mjesto}} </td>
          <td class="border px-4 py-2 text-red-600 text-center">{{ formatDate(item.next_due) }}</td>
        </tr>
      </tbody>
    </table>
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
