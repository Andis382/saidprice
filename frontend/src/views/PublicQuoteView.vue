<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { PhChatCircleText, PhCheckCircle, PhFilePdf, PhHourglassSimpleLow, PhPhone, PhThumbsDown, PhWarningCircle, PhXCircle } from '@phosphor-icons/vue'
import PublicLayout from '@/components/layout/PublicLayout.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiField from '@/components/ui/UiField.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import QuoteDocument from '@/components/quote/QuoteDocument.vue'
import { api, ApiError } from '@/lib/api'
import { formatDate, formatMoney, formatPhone, setFormatCurrency } from '@/lib/format'
import type { PublicQuote } from '@/lib/quote'
import { telLink } from '@/lib/whatsapp'
import { setLocale } from '@/i18n'

/** The page the customer opens from WhatsApp: read the quote, then accept, decline or ask. */
const { t } = useI18n()
const route = useRoute()
const token = String(route.params.token)

const quote = ref<PublicQuote | null>(null)
const missing = ref(false)
const dialog = ref<'accept' | 'decline' | 'ask' | null>(null)
const text = ref('')
const busy = ref(false)
const asked = ref(false)
const justAnswered = ref(false)

const open = computed({
  get: () => dialog.value !== null,
  set: (v) => {
    if (!v) dialog.value = null
  },
})
const declineReasons = computed(() => t('public.declineReasons').split(';').map((s) => s.trim()))

onMounted(async () => {
  try {
    const q = await api.get<PublicQuote>(`/public/quotes/${token}`, { allow401: true })
    let chosen = null
    try {
      chosen = localStorage.getItem('locale')
    } catch {
      /* storage may be blocked */
    }
    if (!chosen) setLocale(q.locale)
    setFormatCurrency(q.currency)
    quote.value = q
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) missing.value = true
    else throw e
  }
})

function ask(kind: 'accept' | 'decline' | 'ask') {
  text.value = ''
  dialog.value = kind
}

async function answer() {
  const kind = dialog.value
  if (!kind || !quote.value) return
  busy.value = true
  try {
    if (kind === 'ask') {
      await api.post(`/public/quotes/${token}/question`, { text: text.value })
      asked.value = true
    } else {
      quote.value = await api.post<PublicQuote>(`/public/quotes/${token}/${kind}`, kind === 'accept' ? { note: text.value } : { reason: text.value })
      justAnswered.value = true
    }
    dialog.value = null
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    if (e instanceof ApiError && e.status === 409 && quote.value) {
      quote.value = await api.get<PublicQuote>(`/public/quotes/${token}`)
      dialog.value = null
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PublicLayout :business="quote?.business.name ?? null" :subtitle="quote?.business.trade ?? null">
    <div v-if="missing" class="card-like">
      <UiEmpty :icon="PhWarningCircle" :title="$t('public.missingTitle')" :text="$t('public.missingText')" />
    </div>

    <template v-else-if="!quote">
      <UiSkeleton card :lines="4" />
      <UiSkeleton card :lines="8" />
    </template>

    <template v-else>
      <div v-if="quote.status === 'ACCEPTED'" class="banner banner--ok" role="status">
        <PhCheckCircle :size="30" weight="fill" aria-hidden="true" />
        <div>
          <p class="banner__title">{{ justAnswered ? $t('public.thanksTitle') : $t('public.acceptedTitle') }}</p>
          <p>{{ $t('public.acceptedText', { business: quote.business.name, date: formatDate(quote.respondedAt, 'long') }) }}</p>
        </div>
      </div>
      <div v-else-if="quote.status === 'DECLINED'" class="banner banner--no" role="status">
        <PhXCircle :size="30" weight="fill" aria-hidden="true" />
        <div>
          <p class="banner__title">{{ $t('public.declinedTitle') }}</p>
          <p>{{ $t('public.declinedText', { business: quote.business.name }) }}</p>
        </div>
      </div>
      <div v-else-if="quote.status === 'EXPIRED'" class="banner" role="status">
        <PhHourglassSimpleLow :size="30" weight="fill" aria-hidden="true" />
        <div>
          <p class="banner__title">{{ $t('public.expiredTitle') }}</p>
          <p>{{ $t('public.expiredText', { business: quote.business.name, date: formatDate(quote.validUntil, 'long') }) }}</p>
        </div>
      </div>

      <UiNotice v-if="asked" tone="success" :title="$t('public.askedTitle')">{{ $t('public.askedText', { business: quote.business.name }) }}</UiNotice>

      <section v-if="quote.canAnswer" class="answer" :aria-label="$t('public.answerLabel')">
        <p class="answer__lead">{{ $t('public.lead', { name: quote.customerName ?? '', total: formatMoney(quote.totalCents) }) }}</p>
        <UiButton size="lg" block :icon="PhCheckCircle" @click="ask('accept')">{{ $t('public.accept') }}</UiButton>
        <div class="answer__row">
          <UiButton variant="secondary" :icon="PhThumbsDown" @click="ask('decline')">{{ $t('public.decline') }}</UiButton>
          <UiButton variant="secondary" :icon="PhChatCircleText" @click="ask('ask')">{{ $t('public.ask') }}</UiButton>
        </div>
      </section>

      <QuoteDocument
        :business="quote.business"
        :number="quote.number"
        :title="quote.title"
        :customer-name="quote.customerName"
        :items="quote.items"
        :totals="quote.totals"
        :proposed-for="quote.proposedFor"
        :valid-until="quote.validUntil"
        :notes="quote.notes"
        :terms="quote.terms"
        :date="quote.sentAt"
      />

      <UiCard tone="muted" padding="sm">
        <div class="tools">
          <UiButton variant="ghost" :icon="PhFilePdf" :href="`/api/public/quotes/${token}/pdf`" target="_blank">{{ $t('public.pdf') }}</UiButton>
          <UiButton v-if="quote.business.phone" variant="ghost" :icon="PhPhone" :href="telLink(quote.business.phone) ?? undefined">
            {{ $t('public.call', { phone: formatPhone(quote.business.phone) }) }}
          </UiButton>
        </div>
      </UiCard>
    </template>

    <UiDialog
      v-model:open="open"
      size="sm"
      :title="dialog === 'accept' ? $t('public.acceptTitle') : dialog === 'decline' ? $t('public.declineTitle') : $t('public.askTitle')"
      :description="
        dialog === 'accept'
          ? $t('public.acceptText', { total: formatMoney(quote?.totalCents ?? 0), when: quote?.proposedFor || $t('document.toBeAgreed') })
          : dialog === 'decline'
            ? $t('public.declineText')
            : $t('public.askText', { business: quote?.business.name ?? '' })
      "
    >
      <form id="answer-form" class="stack" novalidate @submit.prevent="answer">
        <div v-if="dialog === 'decline'" class="chips" role="group" :aria-label="$t('public.reason')">
          <button v-for="r in declineReasons" :key="r" type="button" class="chip" :class="{ 'is-on': text === r }" @click="text = r">{{ r }}</button>
        </div>
        <UiField
          id="f-answer"
          :label="dialog === 'accept' ? $t('public.note') : dialog === 'decline' ? $t('public.reason') : $t('public.question')"
          :optional="dialog !== 'ask'"
        >
          <template #default="{ id, describedby }">
            <UiTextarea :id="id" v-model="text" :rows="3" :describedby="describedby" maxlength="1000" :required="dialog === 'ask'" />
          </template>
        </UiField>
      </form>
      <template #footer>
        <UiButton variant="ghost" @click="dialog = null">{{ $t('common.cancel') }}</UiButton>
        <UiButton
          type="submit"
          form="answer-form"
          :loading="busy"
          :disabled="dialog === 'ask' && !text.trim()"
          :variant="dialog === 'decline' ? 'secondary' : 'primary'"
        >
          {{ dialog === 'accept' ? $t('public.acceptConfirm') : dialog === 'decline' ? $t('public.declineConfirm') : $t('public.askSend') }}
        </UiButton>
      </template>
    </UiDialog>
  </PublicLayout>
</template>

<style scoped>
.card-like {
  padding: 24px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md), var(--highlight);
}
.banner {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  padding: 18px;
  border-radius: var(--radius-lg);
  background: var(--surface);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-md), var(--highlight);
  color: var(--text-muted);
}
.banner--ok {
  background: linear-gradient(180deg, var(--success-soft), var(--surface));
  border-color: color-mix(in srgb, var(--success) 30%, transparent);
}
.banner--ok :deep(svg) {
  color: var(--success);
}
.banner--no :deep(svg) {
  color: var(--danger);
}
.banner__title {
  font-family: var(--font-display);
  font-size: var(--text-lg);
  font-weight: 800;
  color: var(--text);
}
.answer {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 18px;
  border-radius: var(--radius-lg);
  background: var(--surface);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-lg), var(--highlight);
}
.answer__lead {
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.answer__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.tools {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 6px;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.chip {
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-pill);
  background: var(--surface);
  color: var(--text-muted);
  font-size: var(--text-sm);
  font-weight: 600;
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
