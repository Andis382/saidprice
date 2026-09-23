<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhCheckCircle, PhTrendDown } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import { api, ApiError } from '@/lib/api'
import type { Outcome, QuoteDetail, QuoteSummary } from '@/lib/quote'
import { useToasts } from '@/stores/toasts'

/** "How did it end?" Won by phone, or lost with a short reason the stats can learn from. */
const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{ quote: Pick<QuoteSummary, 'id' | 'number' | 'status' | 'customer'> | null; outcome: Outcome }>()
const emit = defineEmits<{ done: [quote: QuoteDetail] }>()

const { t } = useI18n()
const toasts = useToasts()
const reason = ref('')
const busy = ref(false)
const presets = computed(() => ['tooExpensive', 'someoneElse', 'cancelled', 'noAnswer'].map((k) => t(`outcomeDialog.reasons.${k}`)))
const isOpen = computed(() => props.quote !== null && ['SENT', 'VIEWED'].includes(props.quote.status))

watch(open, (value) => {
  if (value) reason.value = ''
})

async function confirm() {
  if (!props.quote) return
  busy.value = true
  try {
    const updated = await api.post<QuoteDetail>(`/quotes/${props.quote.id}/outcome`, { outcome: props.outcome, reason: reason.value || null })
    toasts.success(t(props.outcome === 'WON' ? 'outcomeDialog.wonToast' : 'outcomeDialog.lostToast', { number: props.quote.number }))
    open.value = false
    emit('done', updated)
  } catch (e) {
    toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <UiDialog
    v-model:open="open"
    size="sm"
    :title="$t(outcome === 'WON' ? 'outcomeDialog.wonTitle' : 'outcomeDialog.lostTitle', { number: quote?.number ?? '' })"
    :description="$t(outcome === 'WON' ? (isOpen ? 'outcomeDialog.wonOpenText' : 'outcomeDialog.wonText') : isOpen ? 'outcomeDialog.lostOpenText' : 'outcomeDialog.lostText', { name: quote?.customer?.name ?? '' })"
  >
    <div v-if="outcome === 'LOST'" class="stack stack-sm">
      <UiField id="f-lost-reason" :label="$t('outcomeDialog.reason')" optional>
        <template #default="{ id }">
          <UiInput :id="id" v-model="reason" maxlength="200" />
        </template>
      </UiField>
      <div class="chips" role="group" :aria-label="$t('outcomeDialog.reason')">
        <button v-for="p in presets" :key="p" type="button" class="chip" :class="{ 'is-on': reason === p }" @click="reason = p">{{ p }}</button>
      </div>
    </div>
    <template #footer>
      <UiButton variant="ghost" @click="open = false">{{ $t('common.cancel') }}</UiButton>
      <UiButton :variant="outcome === 'WON' ? 'primary' : 'secondary'" :icon="outcome === 'WON' ? PhCheckCircle : PhTrendDown" :loading="busy" @click="confirm">
        {{ $t(outcome === 'WON' ? 'outcomeDialog.confirmWon' : 'outcomeDialog.confirmLost') }}
      </UiButton>
    </template>
  </UiDialog>
</template>

<style scoped>
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.chip {
  min-height: 36px;
  padding: 0 12px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-pill);
  background: var(--surface);
  color: var(--text-muted);
  font-size: var(--text-sm);
  font-weight: 600;
  transition:
    background-color var(--duration) var(--ease),
    border-color var(--duration) var(--ease);
}
.chip:hover {
  border-color: var(--gray-400);
  color: var(--text);
}
.chip.is-on {
  border-color: var(--primary-soft-border);
  background: var(--primary-soft);
  color: var(--primary-soft-text);
}
.chip:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--focus-ring);
}
</style>
