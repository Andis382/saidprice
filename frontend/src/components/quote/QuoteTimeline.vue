<script setup lang="ts">
import type { Component } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  PhBellRinging,
  PhBellSimple,
  PhBellSimpleSlash,
  PhCalendarX,
  PhChatCircleText,
  PhCheckCircle,
  PhCopy,
  PhEye,
  PhHourglassLow,
  PhMicrophone,
  PhPaperPlaneTilt,
  PhPencilSimple,
  PhTrendDown,
  PhTrophy,
  PhWhatsappLogo,
  PhXCircle,
} from '@phosphor-icons/vue'
import { formatDateTime, formatMoney, formatRelative } from '@/lib/format'
import type { QuoteEvent } from '@/lib/quote'

/** The quote's story, oldest first: who did what, when, and through which channel. */
const props = defineProps<{ events: QuoteEvent[]; customerName?: string | null }>()
const { t } = useI18n()

type Look = { icon: Component; tone: 'neutral' | 'primary' | 'success' | 'danger' | 'warning' | 'info' }

function look(e: QuoteEvent): Look {
  switch (e.type) {
    case 'CREATED':
      return { icon: e.meta.source === 'VOICE' ? PhMicrophone : e.meta.source === 'WHATSAPP' ? PhWhatsappLogo : e.meta.source === 'DUPLICATE' ? PhCopy : PhPencilSimple, tone: 'neutral' }
    case 'EDITED':
      return { icon: PhPencilSimple, tone: 'neutral' }
    case 'SENT':
    case 'RESENT':
      return { icon: PhPaperPlaneTilt, tone: 'primary' }
    case 'VIEWED':
      return { icon: PhEye, tone: 'info' }
    case 'NUDGE_DRAFTED':
      return { icon: PhBellSimple, tone: 'neutral' }
    case 'NUDGE_SENT':
      return { icon: PhBellRinging, tone: 'primary' }
    case 'NUDGE_DISMISSED':
      return { icon: PhBellSimpleSlash, tone: 'neutral' }
    case 'ACCEPTED':
      return { icon: PhCheckCircle, tone: 'success' }
    case 'DECLINED':
      return { icon: PhXCircle, tone: 'danger' }
    case 'QUESTION':
      return { icon: PhChatCircleText, tone: 'warning' }
    case 'SILENT':
      return { icon: PhHourglassLow, tone: 'neutral' }
    case 'EXPIRED':
      return { icon: PhCalendarX, tone: 'neutral' }
    case 'WON':
      return { icon: PhTrophy, tone: 'success' }
    default:
      return { icon: PhTrendDown, tone: 'danger' }
  }
}

function title(e: QuoteEvent): string {
  switch (e.type) {
    case 'CREATED':
      return e.meta.source === 'DUPLICATE' ? t('events.CREATED_DUPLICATE', { from: e.meta.from ?? '' }) : t(`events.CREATED_${e.meta.source ?? 'TYPED'}`)
    case 'EDITED':
      return e.meta.fromCents !== e.meta.toCents
        ? t('events.EDITED_TOTAL', { from: formatMoney(Number(e.meta.fromCents)), to: formatMoney(Number(e.meta.toCents)) })
        : t('events.EDITED')
    case 'NUDGE_DRAFTED':
    case 'NUDGE_SENT':
    case 'NUDGE_DISMISSED':
      return t(`events.${e.type}`, { n: e.meta.sequence ?? 1 })
    case 'WON':
      return e.meta.statusBefore && e.meta.statusBefore !== 'ACCEPTED' ? t('events.WON_BY_PHONE') : t('events.WON')
    default:
      return t(`events.${e.type}`)
  }
}

function who(e: QuoteEvent): string {
  const via = e.channel && e.channel !== 'APP' ? ` · ${t(`events.via.${e.channel}`)}` : ''
  if (e.actor === 'CUSTOMER') return (props.customerName ?? t('events.customer')) + via
  if (e.actor === 'SYSTEM') return t('events.system')
  return (e.userName ?? '') + via
}
</script>

<template>
  <ol class="timeline">
    <li v-for="e in events" :key="e.id" class="event" :class="`event--${look(e).tone}`">
      <span class="event__icon"><component :is="look(e).icon" :size="16" weight="bold" aria-hidden="true" /></span>
      <div class="event__body">
        <p class="event__title">{{ title(e) }}</p>
        <p class="event__meta">
          <span>{{ who(e) }}</span>
          <time :datetime="e.createdAt" :title="formatRelative(e.createdAt)">{{ formatDateTime(e.createdAt) }}</time>
        </p>
        <p v-if="e.body" class="event__quote">“{{ e.body }}”</p>
      </div>
    </li>
  </ol>
</template>

<style scoped>
.timeline {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.event {
  --c: var(--text-subtle);
  --bg: var(--surface-sunken);
  position: relative;
  display: grid;
  grid-template-columns: 32px minmax(0, 1fr);
  gap: 12px;
  padding-bottom: 14px;
}
.event:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 15.5px;
  top: 32px;
  bottom: 0;
  width: 1.5px;
  background: var(--border-strong);
}
.event--primary {
  --c: var(--primary-soft-text);
  --bg: var(--primary-soft);
}
.event--success {
  --c: var(--success-text);
  --bg: var(--success-soft);
}
.event--danger {
  --c: var(--danger-text);
  --bg: var(--danger-soft);
}
.event--warning {
  --c: var(--warning-text);
  --bg: var(--warning-soft);
}
.event--info {
  --c: var(--info-text);
  --bg: var(--info-soft);
}
.event__icon {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  color: var(--c);
  background: var(--bg);
  border: 1px solid color-mix(in srgb, var(--c) 18%, transparent);
}
.event__body {
  min-width: 0;
  padding-top: 5px;
}
.event__title {
  font-size: var(--text-sm);
  font-weight: 650;
  line-height: 1.35;
}
.event__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 2px 10px;
  margin-top: 2px;
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.event__quote {
  margin-top: 6px;
  padding: 8px 12px;
  border-radius: 4px var(--radius-sm) var(--radius-sm) var(--radius-sm);
  background: var(--surface-muted);
  border: 1px solid var(--border);
  font-size: var(--text-sm);
  color: var(--text-muted);
}
</style>
