<script setup>
import { computed, ref, useAttrs, watch } from 'vue'

defineOptions({ inheritAttrs: false })

const model = defineModel({
  default: '',
})

const attrs = useAttrs()
const display = ref('')
const focused = ref(false)
const input = ref(null)
const pickerValue = computed(() => String(model.value ?? '').slice(0, 10))

const isoToDisplay = (value) => {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value ?? '').trim())
  return match ? `${match[3]}/${match[2]}/${match[1]}` : ''
}

const displayToIso = (value) => {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(value ?? '').trim())
  if (!match) return ''
  const day = Number(match[1])
  const month = Number(match[2])
  const year = Number(match[3])
  const parsed = new Date(year, month - 1, day)
  if (parsed.getFullYear() !== year || parsed.getMonth() !== month - 1 || parsed.getDate() !== day) return ''
  return `${match[3]}-${match[2]}-${match[1]}`
}

const maskDate = (raw) => {
  const digits = String(raw ?? '').replace(/\D/g, '').slice(0, 8)
  if (digits.length <= 2) return digits
  if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`
  return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
}

watch(() => model.value, (value) => {
  const shown = isoToDisplay(value)
  if (focused.value && display.value && displayToIso(display.value) !== String(value ?? '').slice(0, 10)) return
  display.value = shown
}, { immediate: true })

const notify = () => {
  input.value?.dispatchEvent(new Event('input', { bubbles: true }))
  input.value?.dispatchEvent(new Event('change', { bubbles: true }))
}

const onInput = (event) => {
  display.value = maskDate(event.target.value)
  const iso = displayToIso(display.value)
  if (iso) {
    model.value = iso
    return
  }
  if (display.value === '') model.value = ''
}

const onBlur = () => {
  focused.value = false
  const iso = displayToIso(display.value)
  if (iso) {
    model.value = iso
    display.value = isoToDisplay(iso)
    return
  }
  display.value = isoToDisplay(model.value)
}

const onPicker = (event) => {
  const iso = String(event.target.value || '')
  if (!iso || iso === pickerValue.value) {
    if (iso && display.value !== isoToDisplay(iso)) {
      focused.value = false
      display.value = isoToDisplay(iso)
    }
    return
  }
  focused.value = false
  display.value = isoToDisplay(iso)
  model.value = iso
  if (input.value) input.value.value = display.value
  notify()
}

defineExpose({ focus: () => input.value?.focus() })
</script>

<template>
  <div class="relative w-full">
    <input
      ref="input"
      v-bind="attrs"
      :value="display"
      type="text"
      inputmode="numeric"
      maxlength="10"
      placeholder="dd/mm/yyyy"
      autocomplete="off"
      class="pr-9"
      @focus="focused = true"
      @blur="onBlur"
      @input="onInput"
    />
    <span class="absolute inset-y-0 right-0 z-10 flex w-9 items-center justify-center text-gray-500">
      <svg class="pointer-events-none h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <input
        v-if="!attrs.disabled"
        type="date"
        tabindex="-1"
        aria-label="Otvori kalendar"
        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
        :value="pickerValue"
        @input="onPicker"
        @change="onPicker"
      />
    </span>
  </div>
</template>
