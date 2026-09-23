<script setup lang="ts">
import { PhCheckCircle, PhXCircle } from '@phosphor-icons/vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import { STATUS_TONE, type Outcome, type QuoteStatus } from '@/lib/quote'

/** Status, and next to it how it ended when the tradesperson said so. */
withDefaults(defineProps<{ status: QuoteStatus; outcome?: Outcome | null; size?: 'sm' | 'md' }>(), { outcome: null, size: 'md' })
</script>

<template>
  <span class="status">
    <UiBadge :tone="STATUS_TONE[status]" :size="size" dot>{{ $t(`status.${status}`) }}</UiBadge>
    <UiBadge v-if="outcome === 'WON'" tone="success" :size="size" :icon="PhCheckCircle">{{ $t('outcome.WON') }}</UiBadge>
    <UiBadge v-else-if="outcome === 'LOST'" tone="danger" :size="size" :icon="PhXCircle">{{ $t('outcome.LOST') }}</UiBadge>
  </span>
</template>

<style scoped>
.status {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 6px;
}
</style>
