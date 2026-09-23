<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { PhBellRinging, PhChatCircleText, PhCheckCircle, PhEye, PhMicrophone, PhWhatsappLogo, PhXCircle } from '@phosphor-icons/vue'
import { formatAge, formatMoney, formatRelative } from '@/lib/format'
import type { QuoteSummary } from '@/lib/quote'

/** One quote on the board: who, what, how much, and what is going on with it. */
const props = withDefaults(defineProps<{ quote: QuoteSummary; heading?: 'customer' | 'number' }>(), { heading: 'customer' })

const when = computed(() => {
  const q = props.quote
  if (q.status === 'DRAFT') return q.updatedAt
  if (q.status === 'ACCEPTED' || q.status === 'DECLINED') return q.respondedAt ?? q.sentAt
  return q.sentAt
})
</script>

<template>
  <RouterLink :to="{ name: 'quote', params: { id: quote.id } }" class="qcard" :class="[`qcard--${quote.status.toLowerCase()}`, { 'qcard--question': quote.hasQuestion }]">
    <div class="qcard__top">
      <span v-if="heading === 'number'" class="qcard__who num">{{ quote.number }}</span>
      <span v-else class="qcard__who truncate">
        {{ quote.customer?.name ?? (quote.source === 'WHATSAPP' ? $t('quotes.fromWhatsApp') : $t('quotes.noCustomer')) }}
      </span>
      <time class="qcard__when num" :datetime="when ?? undefined" :title="formatRelative(when)">{{ formatAge(when) }}</time>
    </div>
    <p class="qcard__title">{{ quote.title ?? (quote.source === 'WHATSAPP' ? $t('quotes.voiceNoteWaiting') : $t('quotes.untitled')) }}</p>
    <div class="qcard__foot">
      <span class="qcard__total num">{{ formatMoney(quote.totalCents) }}</span>
      <span class="qcard__signals">
        <span v-if="quote.hasQuestion" class="sig sig--question" :title="$t('quotes.signals.question')">
          <PhChatCircleText :size="15" weight="fill" aria-hidden="true" />
          <span class="visually-hidden">{{ $t('quotes.signals.question') }}</span>
        </span>
        <span v-if="quote.pendingNudge" class="sig sig--nudge" :title="$t('quotes.signals.followUpReady')">
          <PhBellRinging :size="15" weight="fill" aria-hidden="true" />
          <span class="visually-hidden">{{ $t('quotes.signals.followUpReady') }}</span>
        </span>
        <span v-else-if="quote.nudgeCount" class="sig" :title="$t('quotes.signals.nudged', { n: quote.nudgeCount })">
          <PhBellRinging :size="15" aria-hidden="true" /><span class="num">{{ quote.nudgeCount }}</span>
        </span>
        <span v-if="quote.viewedAt && (quote.status === 'SENT' || quote.status === 'VIEWED')" class="sig" :title="$t('quotes.signals.viewed')">
          <PhEye :size="15" aria-hidden="true" />
          <span class="visually-hidden">{{ $t('quotes.signals.viewed') }}</span>
        </span>
        <span v-if="quote.source === 'WHATSAPP'" class="sig" :title="$t('quotes.signals.whatsapp')">
          <PhWhatsappLogo :size="15" aria-hidden="true" />
        </span>
        <span v-else-if="quote.hasVoice" class="sig" :title="$t('quotes.signals.voice')">
          <PhMicrophone :size="15" aria-hidden="true" />
          <span class="visually-hidden">{{ $t('quotes.signals.voice') }}</span>
        </span>
      </span>
    </div>
    <span v-if="quote.outcome" class="qcard__outcome" :class="`qcard__outcome--${quote.outcome.toLowerCase()}`">
      <component :is="quote.outcome === 'WON' ? PhCheckCircle : PhXCircle" :size="13" weight="fill" aria-hidden="true" />
      {{ $t(`outcome.${quote.outcome}`) }}
    </span>
    <span v-else-if="quote.status === 'EXPIRED'" class="qcard__outcome">{{ $t('status.EXPIRED') }}</span>
  </RouterLink>
</template>

<style scoped>
.qcard {
  --edge: var(--gray-300);
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 14px 12px 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm), var(--highlight);
  color: var(--text);
  text-decoration: none;
  transition:
    transform var(--duration) var(--ease),
    box-shadow var(--duration) var(--ease),
    border-color var(--duration) var(--ease);
}
.qcard::before {
  /* status edge */
  content: '';
  position: absolute;
  left: 0;
  top: 10px;
  bottom: 10px;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--edge);
}
.qcard--sent {
  --edge: var(--brand-400);
}
.qcard--viewed {
  --edge: var(--info);
}
.qcard--accepted {
  --edge: var(--accent-500);
}
.qcard--declined {
  --edge: var(--danger);
}
.qcard--question {
  border-color: color-mix(in srgb, var(--warning) 45%, transparent);
  background: linear-gradient(180deg, var(--warning-soft), var(--surface) 60%);
}
.qcard:hover {
  color: var(--text);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg), var(--highlight);
  border-color: var(--border-strong);
}
.qcard:focus-visible {
  outline: none;
  box-shadow: var(--shadow-md), 0 0 0 4px var(--focus-ring);
}
.qcard__top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
}
.qcard__who {
  font-weight: 700;
  font-size: var(--text-sm);
  min-width: 0;
}
.qcard__when {
  flex: none;
  font-size: 11px;
  color: var(--text-subtle);
  white-space: nowrap;
}
.qcard__title {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: var(--text-sm);
  line-height: 1.4;
  color: var(--text-muted);
}
.qcard__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 2px;
}
.qcard__total {
  font-family: var(--font-display);
  font-size: 1.15rem;
  font-weight: 750;
  letter-spacing: -0.02em;
}
.qcard__signals {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--text-subtle);
}
.sig {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 11px;
  font-weight: 650;
}
.sig--question {
  color: var(--warning);
}
.sig--nudge {
  color: var(--brand-600);
}
.qcard__outcome {
  position: absolute;
  top: -8px;
  right: 10px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 20px;
  padding: 0 8px;
  border-radius: var(--radius-pill);
  background: var(--gray-700);
  color: var(--text-inverse);
  font-size: 10.5px;
  font-weight: 750;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  box-shadow: var(--shadow-sm);
}
.qcard__outcome--won {
  background: var(--accent-600);
}
.qcard__outcome--lost {
  background: var(--gray-500);
}
</style>
