<script setup>
import { computed, onMounted, ref, useAttrs } from 'vue'
import DateInput from '@/Components/DateInput.vue'

defineOptions({ inheritAttrs: false })

const model = defineModel({
    type: String,
    required: true,
})

const attrs = useAttrs()
const input = ref(null)
const isDate = computed(() => attrs.type === 'date')
const forwarded = computed(() => {
    if (attrs.type !== 'date') return attrs
    const { type, ...rest } = attrs
    return rest
})

onMounted(() => {
    const el = input.value?.$el ?? input.value
    if (typeof el?.hasAttribute === 'function' && el.hasAttribute('autofocus')) {
        el.focus()
    }
})

defineExpose({ focus: () => input.value?.focus?.() })
</script>

<template>
    <DateInput
        v-if="isDate"
        ref="input"
        v-model="model"
        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        v-bind="forwarded"
    />
    <input
        v-else
        ref="input"
        v-model="model"
        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        v-bind="forwarded"
    />
</template>
