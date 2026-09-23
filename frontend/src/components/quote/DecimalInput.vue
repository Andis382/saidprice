<script setup lang="ts">
import { ref, watch } from 'vue'
import UiInput from '@/components/ui/UiInput.vue'
import { parseDecimal } from '@/lib/format'

/**
 * A number field that accepts "1,5" as well as "1.5". With `cents` the model is integer
 * cents and the field shows euros: people type prices, the app stores cents.
 */
defineOptions({ inheritAttrs: false })

const model = defineModel<number | null>({ required: true })
const props = withDefaults(defineProps<{ id?: string; cents?: boolean; prefix?: string; suffix?: string; invalid?: boolean; describedby?: string }>(), {
  id: undefined,
  prefix: undefined,
  suffix: undefined,
  describedby: undefined,
})

const decimalSeparator = (1.5).toLocaleString(document.documentElement.lang === 'sq' ? 'sq-AL' : 'en-GB').charAt(1)

function show(value: number | null): string {
  if (value === null) return ''
  const units = props.cents ? value / 100 : value
  const text = Number.isInteger(units) ? String(units) : units.toFixed(2).replace(/0$/, '')
  return text.replace('.', decimalSeparator)
}

const text = ref(show(model.value))

watch(model, (value) => {
  if (parseDecimal(text.value) !== (value === null ? null : props.cents ? value / 100 : value)) text.value = show(value)
})

function onInput(value: string | number | null | undefined) {
  text.value = String(value ?? '')
  const n = parseDecimal(text.value)
  if (n === null) {
    if (text.value.trim() === '') model.value = null
    return
  }
  model.value = props.cents ? Math.round(n * 100) : Math.round(n * 100) / 100
}
</script>

<template>
  <UiInput
    :id="id"
    :model-value="text"
    v-bind="$attrs"
    inputmode="decimal"
    autocomplete="off"
    :prefix="prefix"
    :suffix="suffix"
    :invalid="invalid"
    :describedby="describedby"
    @update:model-value="onInput"
    @blur="text = show(model)"
  />
</template>

<style scoped>
:deep(input) {
  font-variant-numeric: tabular-nums;
  text-align: right;
}
</style>
