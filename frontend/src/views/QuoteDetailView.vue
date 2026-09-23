<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  PhArrowSquareOut,
  PhBellSimple,
  PhChatCircleText,
  PhCheckCircle,
  PhClockCountdown,
  PhCopy,
  PhFilePdf,
  PhLinkSimple,
  PhListChecks,
  PhMapPin,
  PhMicrophone,
  PhPaperPlaneTilt,
  PhPencilSimple,
  PhPhone,
  PhTrash,
  PhTrendDown,
  PhUser,
  PhWhatsappLogo,
} from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiCopy from '@/components/ui/UiCopy.vue'
import UiMenu, { type MenuItem } from '@/components/ui/UiMenu.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import NudgeCard from '@/components/quote/NudgeCard.vue'
import OutcomeDialog from '@/components/quote/OutcomeDialog.vue'
import QuoteDocument from '@/components/quote/QuoteDocument.vue'
import QuoteTimeline from '@/components/quote/QuoteTimeline.vue'
import SentDialog from '@/components/quote/SentDialog.vue'
import StatusBadge from '@/components/quote/StatusBadge.vue'
import { api, ApiError } from '@/lib/api'
import { formatDateTime, formatPhone, formatRelative } from '@/lib/format'
import type { OutboundMessage } from '@/lib/messages'
import type { NudgeRow, Outcome, QuoteDetail } from '@/lib/quote'
import { telLink, waLink } from '@/lib/whatsapp'
import { useAuth } from '@/stores/auth'
import { useConfirm } from '@/stores/confirm'
import { useOverview } from '@/stores/overview'
import { useToasts } from '@/stores/toasts'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuth()
const toasts = useToasts()
const confirm = useConfirm()
const overview = useOverview()

const id = computed(() => Number(route.params.id))
const quote = ref<QuoteDetail | null>(null)
const trade = ref<string | null>(null)
const outcomeOpen = ref(false)
const outcome = ref<Outcome>('WON')
const sent = ref<OutboundMessage | null>(null)
const sentOpen = ref(false)
const busy = ref(false)

const title = computed(() => quote.value?.title ?? (quote.value?.source === 'WHATSAPP' ? t('quotes.voiceNoteWaiting') : t('quotes.untitled')))
const pendingNudge = computed<NudgeRow | null>(() => {
  const q = quote.value
  const n = q?.nudges.find((x) => x.status === 'DRAFTED')
  if (!q || !n) return null
  return { ...n, quote: { ...q, customer: q.customer }, customerLocale: q.customer?.locale ?? 'sq' }
})
const lastQuestion = computed(() => {
  const q = quote.value
  if (!q?.hasQuestion) return null
  return [...q.events].reverse().find((e) => e.type === 'QUESTION') ?? null
})
const menu = computed<MenuItem[]>(() => {
  const q = quote.value
  if (!q) return []
  const items: MenuItem[] = [
    { label: t('detail.duplicate'), icon: PhCopy, action: duplicate },
    { label: t('detail.pdf'), icon: PhFilePdf, action: () => window.open(`/api/quotes/${q.id}/pdf`, '_blank') },
  ]
  if (q.status !== 'DRAFT') items.push({ label: t('detail.openCustomerPage'), icon: PhArrowSquareOut, action: () => window.open(q.publicUrl, '_blank') })
  if (q.can.outcome && q.outcome !== 'WON') items.push({ label: t('detail.markWon'), icon: PhCheckCircle, action: () => mark('WON') })
  if (q.can.outcome && q.outcome !== 'LOST') items.push({ label: t('detail.markLost'), icon: PhTrendDown, action: () => mark('LOST') })
  if (q.can.delete) items.push({ label: t('detail.delete'), icon: PhTrash, danger: true, action: remove })
  return items
})
const waitingText = computed(() => {
  const q = quote.value
  if (!q) return ''
  switch (q.status) {
    case 'DRAFT':
      return t('detail.state.draft')
    case 'SENT':
      return t('detail.state.sent', { when: formatRelative(q.sentAt) })
    case 'VIEWED':
      return t('detail.state.viewed', { when: formatRelative(q.viewedAt) })
    case 'ACCEPTED':
      return t('detail.state.accepted', { when: formatRelative(q.respondedAt) })
    case 'DECLINED':
      return t('detail.state.declined', { when: formatRelative(q.respondedAt) })
    case 'SILENT':
      return t('detail.state.silent')
    default:
      return t('detail.state.expired')
  }
})

async function load() {
  const [q, settings] = await Promise.all([api.get<QuoteDetail>(`/quotes/${id.value}`), api.get<{ trade: string | null }>('/settings/quoting')])
  quote.value = q
  trade.value = settings.trade
}

onMounted(load)

async function send() {
  const q = quote.value
  if (!q) return
  busy.value = true
  try {
    const res = await api.post<{ quote: QuoteDetail; message: OutboundMessage }>(`/quotes/${q.id}/${q.can.send ? 'send' : 'resend'}`)
    quote.value = res.quote
    sent.value = res.message
    sentOpen.value = true
    overview.refresh()
  } catch (e) {
    if (e instanceof ApiError && e.isValidation) {
      toasts.error(t('detail.cannotSend'), Object.values(e.errors).flat().join(' '))
      await router.push({ name: 'quote-edit', params: { id: q.id } })
    } else {
      toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
    }
  } finally {
    busy.value = false
  }
}

async function resend() {
  if (await confirm.ask({ title: t('detail.resendTitle'), text: t('detail.resendText'), confirmLabel: t('detail.resend') })) await send()
}

function mark(value: Outcome) {
  outcome.value = value
  outcomeOpen.value = true
}

async function duplicate() {
  const copy = await api.post<QuoteDetail>(`/quotes/${quote.value?.id}/duplicate`)
  toasts.success(t('detail.duplicated', { number: copy.number }))
  await router.push({ name: 'quote-edit', params: { id: copy.id } })
}

async function remove() {
  const q = quote.value
  if (!q || !(await confirm.ask({ title: t('detail.deleteTitle', { number: q.number }), text: t('detail.deleteText'), danger: true, confirmLabel: t('common.delete') }))) return
  await api.delete(`/quotes/${q.id}`)
  toasts.success(t('detail.deleted'))
  await router.push({ name: 'quotes' })
}

function onNudgeSent(message: OutboundMessage) {
  sent.value = message
  sentOpen.value = true
  load()
  overview.refresh()
}
</script>

<template>
  <AppPage :eyebrow="quote?.number" :title="title" :back="{ name: 'quotes' }" :back-label="$t('nav.quotes')">
    <template #meta>
      <template v-if="quote">
        <StatusBadge :status="quote.status" :outcome="quote.outcome" />
        <span class="hero-note">{{ waitingText }}</span>
      </template>
    </template>
    <template #actions>
      <template v-if="quote">
        <UiButton v-if="quote.can.edit" variant="inverse" :icon="PhPencilSimple" :to="{ name: 'quote-edit', params: { id: quote.id } }">{{ $t('common.edit') }}</UiButton>
        <UiButton v-if="quote.can.send" :icon="PhPaperPlaneTilt" :loading="busy" @click="send">{{ $t('detail.send') }}</UiButton>
        <UiButton v-else-if="quote.can.resend" variant="inverse" :icon="PhPaperPlaneTilt" :loading="busy" @click="resend">{{ $t('detail.resend') }}</UiButton>
        <UiButton v-if="quote.can.outcome && !quote.outcome && ['SENT', 'VIEWED', 'ACCEPTED'].includes(quote.status)" :icon="PhCheckCircle" @click="mark('WON')">
          {{ $t('detail.markWon') }}
        </UiButton>
        <div class="menu-wrap"><UiMenu :items="menu" :label="$t('common.more')" /></div>
      </template>
    </template>

    <div v-if="!quote" class="grid">
      <div class="stack"><UiSkeleton card :lines="8" /></div>
      <div class="stack"><UiSkeleton card :lines="4" /><UiSkeleton card :lines="6" /></div>
    </div>

    <div v-else class="grid">
      <div class="stack main">
        <UiNotice v-if="lastQuestion" tone="warning" :icon="PhChatCircleText" :title="$t('detail.questionTitle', { name: quote.customer?.name ?? '', when: formatRelative(lastQuestion.createdAt) })">
          <p class="question">“{{ lastQuestion.body }}”</p>
          <template #actions>
            <UiButton v-if="quote.customer?.phone" :href="waLink(quote.customer.phone) ?? undefined" target="_blank" size="sm" :icon="PhWhatsappLogo">{{ $t('detail.reply') }}</UiButton>
          </template>
        </UiNotice>

        <section v-if="pendingNudge" class="followup" :aria-label="$t('detail.followUpReady')">
          <p class="followup__label"><PhBellSimple :size="16" weight="bold" aria-hidden="true" /> {{ $t('detail.followUpReady') }}</p>
          <NudgeCard :nudge="pendingNudge" :show-quote="false" @sent="onNudgeSent" @dismissed="load" />
        </section>
        <p v-else-if="quote.nextFollowUpAt" class="next">
          <PhClockCountdown :size="18" weight="bold" aria-hidden="true" />
          {{ $t('detail.nextFollowUp', { when: formatDateTime(quote.nextFollowUpAt) }) }}
        </p>

        <UiNotice v-if="quote.status === 'DECLINED' && quote.responseNote" tone="danger" :title="$t('detail.declinedBecause')">{{ quote.responseNote }}</UiNotice>
        <UiNotice v-if="quote.status === 'ACCEPTED' && quote.responseNote" tone="success" :title="$t('detail.acceptedNote')">{{ quote.responseNote }}</UiNotice>
        <UiNotice v-if="quote.outcome === 'LOST' && quote.lostReason" tone="info" :title="$t('detail.lostBecause')">{{ quote.lostReason }}</UiNotice>

        <QuoteDocument
          :business="{ name: auth.organization?.name ?? '', phone: auth.organization?.phone, trade }"
          :number="quote.number"
          :title="title"
          :customer-name="quote.customer?.name"
          :items="quote.items"
          :totals="quote.totals"
          :proposed-for="quote.proposedFor"
          :valid-until="quote.validUntil"
          :notes="quote.notes"
          :terms="quote.terms"
          :date="quote.sentAt"
          :lang="quote.customer?.locale"
        />
      </div>

      <div class="stack side">
        <UiCard :title="$t('detail.customer')" :icon="PhUser">
          <template v-if="quote.customer">
            <RouterLink :to="{ name: 'customer', params: { id: quote.customer.id } }" class="cust-name">{{ quote.customer.name }}</RouterLink>
            <ul class="facts">
              <li v-if="quote.customer.phone"><PhPhone :size="16" weight="bold" aria-hidden="true" /> <span class="num">{{ formatPhone(quote.customer.phone) }}</span></li>
              <li v-if="quote.customer.address"><PhMapPin :size="16" weight="bold" aria-hidden="true" /> {{ quote.customer.address }}</li>
              <li><PhChatCircleText :size="16" weight="bold" aria-hidden="true" /> {{ $t('detail.writesIn', { lang: $t(`languages.${quote.customer.locale}`) }) }}</li>
            </ul>
            <div v-if="quote.customer.phone" class="contact">
              <UiButton variant="secondary" size="sm" :icon="PhPhone" :href="telLink(quote.customer.phone) ?? undefined">{{ $t('common.call') }}</UiButton>
              <UiButton variant="secondary" size="sm" :icon="PhWhatsappLogo" :href="waLink(quote.customer.phone) ?? undefined" target="_blank">{{ $t('common.whatsapp') }}</UiButton>
            </div>
          </template>
          <p v-else class="muted small">{{ $t('detail.noCustomer') }}</p>
        </UiCard>

        <UiCard v-if="quote.status !== 'DRAFT'" :title="$t('detail.link')" :subtitle="$t('detail.linkHint')" :icon="PhLinkSimple">
          <div class="stack stack-sm">
            <UiCopy :value="quote.publicUrl" :label="$t('detail.link')" />
            <div class="contact">
              <UiButton variant="ghost" size="sm" :icon="PhArrowSquareOut" :href="quote.publicUrl" target="_blank">{{ $t('detail.openCustomerPage') }}</UiButton>
              <UiButton variant="ghost" size="sm" :icon="PhFilePdf" :href="`/api/quotes/${quote.id}/pdf`" target="_blank">{{ $t('detail.pdf') }}</UiButton>
            </div>
          </div>
        </UiCard>

        <UiCard :title="$t('detail.timeline')" :icon="PhListChecks">
          <QuoteTimeline :events="quote.events" :customer-name="quote.customer?.name" />
        </UiCard>

        <UiCard v-if="quote.voice || quote.transcript" :title="$t('detail.voice')" :icon="PhMicrophone">
          <div class="stack stack-sm">
            <audio v-if="quote.voice" :src="quote.voice.url" controls preload="metadata" class="audio" :aria-label="$t('editor.voice.recording')" />
            <p v-if="quote.transcript" class="transcript">“{{ quote.transcript }}”</p>
          </div>
        </UiCard>
      </div>
    </div>

    <OutcomeDialog v-model:open="outcomeOpen" :quote="quote" :outcome="outcome" @done="(q) => (quote = q)" />
    <SentDialog v-model:open="sentOpen" :message="sent" :title="sent?.templateKey === 'quote_nudge' ? $t('sent.nudgeTitle') : $t('sent.quoteTitle', { number: quote?.number ?? '' })" />
  </AppPage>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(300px, 380px);
  gap: 20px;
  align-items: start;
}
.hero-note {
  display: inline-flex;
  align-items: center;
  font-size: var(--text-sm);
  color: var(--text-inverse-muted);
}
.menu-wrap {
  display: inline-flex;
  border-radius: var(--radius-sm);
  background: rgb(255 255 255 / 0.12);
  border: 1px solid rgb(255 255 255 / 0.18);
}
.menu-wrap :deep(.menu__trigger) {
  color: var(--header-text);
}
.question {
  font-size: var(--text-md);
  font-weight: 600;
  color: var(--text);
}
.followup {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.followup__label,
.next {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: var(--text-sm);
  font-weight: 650;
  color: var(--text-muted);
}
.next {
  padding: 12px 16px;
  border: 1px dashed var(--border-strong);
  border-radius: var(--radius);
}
.cust-name {
  font-family: var(--font-display);
  font-size: var(--text-lg);
  font-weight: 750;
  color: var(--text);
  text-decoration: none;
}
.cust-name:hover {
  text-decoration: underline;
  text-decoration-color: var(--brand-400);
}
.facts {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.facts li {
  display: flex;
  align-items: center;
  gap: 8px;
}
.contact {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}
.audio {
  width: 100%;
  height: 40px;
}
.transcript {
  font-size: var(--text-sm);
  color: var(--text-muted);
  font-style: italic;
}
@media (max-width: 980px) {
  .grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
