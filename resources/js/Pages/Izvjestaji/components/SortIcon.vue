<template>
  <span class="inline-block align-middle ml-1 text-gray-400" v-if="active">
    <svg v-if="dir==='asc'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
      <path fill-rule="evenodd" d="M10 3a1 1 0 01.832.445l4.5 6.5A1 1 0 0114.5 12h-9a1 1 0 01-.832-1.555l4.5-6.5A1 1 0 0110 3z" clip-rule="evenodd" />
    </svg>
    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
      <path fill-rule="evenodd" d="M10 17a1 1 0 01-.832-.445l-4.5-6.5A1 1 0 015.5 8h9a1 1 0 01.832 1.555l-4.5 6.5A1 1 0 0110 17z" clip-rule="evenodd" />
    </svg>
  </span>
  <span v-else class="inline-block align-middle ml-1 text-gray-300">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 opacity-60">
      <path d="M10 3a1 1 0 01.832.445l4.5 6.5A1 1 0 0114.5 12h-9a1 1 0 01-.832-1.555l4.5-6.5A1 1 0 0110 3z" />
    </svg>
  </span>
</template>

<script setup>
const props = defineProps({
  active: { type: Boolean, default: false },
  dir: { type: String, default: 'asc' },
});
</script>
