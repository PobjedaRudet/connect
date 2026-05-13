<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()

const flashError = ref(null)

watch(
  () => page.props?.flash?.error,
  (msg) => {
    if (msg) flashError.value = msg
  },
  { immediate: true },
)

function dismissFlash() {
  flashError.value = null
}

function onAppFlashError(event) {
  const msg = event?.detail?.message
  if (msg) flashError.value = msg
}

onMounted(() => {
  window.addEventListener('app:flash-error', onAppFlashError)
})

onBeforeUnmount(() => {
  window.removeEventListener('app:flash-error', onAppFlashError)
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="flashError" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="dismissFlash">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4 p-6 text-center">
          <div class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-red-100 mb-4">
            <svg class="h-7 w-7 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Pristup odbijen</h3>
          <p class="text-gray-600 mb-6">{{ flashError }}</p>
          <button
            @click="dismissFlash"
            class="w-full px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors duration-150"
          >
            U redu
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
