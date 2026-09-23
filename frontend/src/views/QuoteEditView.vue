<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import {
  PhArrowsClockwise,
  PhCalendarBlank,
  PhClipboardText,
  PhEye,
  PhFloppyDisk,
  PhMicrophone,
  PhPaperPlaneTilt,
  PhPencilSimpleLine,
  PhStop,
  PhUser,
  PhWrench,
} from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiField from '@/components/ui/UiField.vue'
import UiFormErrors from '@/components/ui/UiFormErrors.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiStepper from '@/components/ui/UiStepper.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import CustomerFields from '@/components/quote/CustomerFields.vue'
import ItemsEditor from '@/components/quote/ItemsEditor.vue'
import QuoteDocument from '@/components/quote/QuoteDocument.vue'
import SentDialog from '@/components/quote/SentDialog.vue'
import { api, ApiError } from '@/lib/api'
import { useForm } from '@/lib/form'
import { formatDate, formatMoney, todayIso } from '@/lib/format'
import type { OutboundMessage } from '@/lib/messages'
import { addDays, totalsOf, type CustomerDraft, type ParsedQuote, type QuoteDetail, type QuoteItem } from '@/lib/quote'
import { appendTranscript, useSpeech } from '@/lib/speech'
import { useAuth } from '@/stores/auth'
import { useConfirm } from '@/stores/confirm'
import { useToasts } from '@/stores/toasts'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuth()
const toasts = useToasts()
const confirm = useConfirm()
const speech = useSpeech()

const id = Number(route.params.id)
const quote = ref<QuoteDetail | null>(null)
const trade = ref<string | null>(null)
const pane = ref<'edit' | 'preview'>('edit')
const sent = ref<OutboundMessage | null>(null)
const sentOpen = ref(false)
const sending = ref(false)
const reading = ref(false)
let saved = ''

const form = useForm({
  title: '',
  customer: { id: null, name: '', phone: '', address: '', locale: 'sq' } as CustomerDraft,
  items: [] as QuoteItem[],
  proposedFor: '',
  validityDays: 14 as number | null,
  notes: '',
  terms: '',
  transcript: '',
})

const totals = computed(() => totalsOf(form.data.items))
const dirty = computed(() => JSON.stringify(form.data) !== saved)
const fromCapture = computed(() => route.query.from === 'capture')
const validUntil = computed(() =>
  quote.value?.sentAt && quote.value.validUntil ? quote.value.validUntil : addDays(todayIso(), form.data.validityDays ?? 14),
)
const panes = computed(() => [
  { value: 'edit' as const, label: t('editor.paneEdit'), icon: PhPencilSimpleLine },
  { value: 'preview' as const, label: t('editor.panePreview'), icon: PhEye },
])
const errorIds = {
  'customer.name': 'f-customer-name',
  'customer.phone': 'f-customer-phone',
  'customer.address': 'f-customer-address',
  title: 'f-title',
  items: 'f-title',
  proposedFor: 'f-when',
  validityDays: 'f-validity',
  notes: 'f-notes',
  terms: 'f-terms',
}
const whenChips = computed(() => t(`editor.when.chips.${form.data.customer.locale}`).split(';').map((s) => s.trim()))

function fill(q: QuoteDetail) {
  quote.value = q
  form.fill({
    title: q.title ?? '',
    customer: q.customer
      ? { id: q.customer.id, name: q.customer.name, phone: q.customer.phone ?? '', address: q.customer.address ?? '', locale: q.customer.locale }
      : { id: null, name: '', phone: '', address: '', locale: auth.organization?.locale ?? 'sq' },
    items: q.items.map((i) => ({ ...i })),
    proposedFor: q.proposedFor ?? '',
    validityDays: q.validityDays,
    notes: q.notes ?? '',
    terms: q.terms ?? '',
    transcript: q.transcript ?? '',
  })
  saved = JSON.stringify(form.data)
}

onMounted(async () => {
  const [q, settings] = await Promise.all([api.get<QuoteDetail>(`/quotes/${id}`), api.get<{ trade: string | null }>('/settings/quoting')])
  trade.value = settings.trade
  if (!q.can.edit) {
    toasts.info(t('editor.locked'))
    await router.replace({ name: 'quote', params: { id } })
    return
  }
  fill(q)
})

function payload() {
  const c = form.data.customer
  const hasCustomer = c.id !== null || c.name.trim() !== '' || c.phone.trim() !== ''
  return {
    title: form.data.title,
    customer: hasCustomer ? c : null,
    items: form.data.items.map((i) => ({ description: i.description, qty: i.qty, unit: i.unit, unitPriceCents: i.unitPriceCents, kind: i.kind })),
    proposedFor: form.data.proposedFor,
    validityDays: form.data.validityDays ?? 14,
    notes: form.data.notes,
    terms: form.data.terms,
    transcript: form.data.transcript,
  }
}

async function save(quiet = false): Promise<boolean> {
  const updated = await form.submit(() => api.put<QuoteDetail>(`/quotes/${id}`, payload()))
  if (!updated) return false
  fill(updated)
  if (!quiet) toasts.success(t('editor.saved'))
  return true
}

async function send() {
  sending.value = true
  try {
    if (!(await save(true))) return
    const action = quote.value?.can.send ? 'send' : 'resend'
    const res = await api.post<{ quote: QuoteDetail; message: OutboundMessage }>(`/quotes/${id}/${action}`)
    fill(res.quote)
    sent.value = res.message
    sentOpen.value = true
  } catch (e) {
    if (e instanceof ApiError && e.isValidation) {
      form.errors.value = e.errors
      form.message.value = e.message
      form.submitted.value++
    } else {
      toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
    }
  } finally {
    sending.value = false
  }
}

function afterSent() {
  router.push({ name: 'quote', params: { id } })
}

/** Reads the transcript into lines again (after dictating, or fixing a misheard price). */
async function readAgain() {
  if (form.data.items.length && !(await confirm.ask({ title: t('editor.voice.readAgainTitle'), text: t('editor.voice.readAgainText'), confirmLabel: t('editor.voice.readAgain') }))) return
  reading.value = true
  try {
    const parsed = await api.post<ParsedQuote>('/quotes/parse', { transcript: form.data.transcript, locale: form.data.customer.locale })
    form.data.items = parsed.items.map((i) => ({ ...i }))
    if (!form.data.title && parsed.title) form.data.title = parsed.title
    if (!form.data.proposedFor && parsed.proposedFor) form.data.proposedFor = parsed.proposedFor
    const c = form.data.customer
    if (!c.id) {
      c.name ||= parsed.customerName ?? ''
      c.phone ||= parsed.customerPhone ? '+' + parsed.customerPhone : ''
      c.address ||= parsed.customerAddress ?? ''
    }
    toasts.success(t('editor.voice.readDone', { n: parsed.items.length }))
  } catch (e) {
    toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
  } finally {
    reading.value = false
  }
}

function dictate() {
  if (speech.listening.value) {
    speech.stop()
    return
  }
  speech.start(form.data.customer.locale, (phrase) => {
    form.data.transcript = appendTranscript(form.data.transcript, phrase)
  })
}

onBeforeRouteLeave(async () => {
  if (!quote.value || !dirty.value || sentOpen.value) return true
  return confirm.ask({ title: t('editor.leaveTitle'), text: t('editor.leaveText'), confirmLabel: t('editor.leave'), danger: true })
})
</script>

<template>
  <AppPage
    :eyebrow="quote ? quote.number : undefined"
    :title="$t(quote?.status === 'DRAFT' ? 'editor.titleDraft' : 'editor.titleSent')"
    :subtitle="$t(quote?.status === 'DRAFT' ? 'editor.subtitleDraft' : 'editor.subtitleSent')"
    :back="{ name: 'quote', params: { id } }"
    :back-label="$t('editor.backToQuote')"
  >
    <template #actions>
      <UiButton variant="inverse" :icon="PhFloppyDisk" :loading="form.processing.value && !sending" :disabled="!quote || sending" @click="save()">{{ $t('common.save') }}</UiButton>
      <UiButton :icon="PhPaperPlaneTilt" :loading="sending" :disabled="!quote" @click="send">
        {{ quote?.can.send === false ? $t('editor.sendAgain') : $t('editor.send') }}
      </UiButton>
    </template>

    <div v-if="!quote" class="layout">
      <div class="stack"><UiSkeleton card :lines="4" /><UiSkeleton card :lines="6" /></div>
      <UiSkeleton card :lines="10" />
    </div>

    <template v-else>
      <UiNotice v-if="quote.source === 'WHATSAPP' && !form.data.transcript && !form.data.items.length" tone="info" :title="$t('editor.notices.whatsappTitle')">
        {{ $t('editor.notices.whatsappText') }}
      </UiNotice>
      <UiNotice v-else-if="fromCapture && quote.capture.engine" tone="success" :title="$t(quote.capture.engine === 'ai' ? 'editor.notices.aiTitle' : 'editor.notices.parserTitle')">
        {{ $t('editor.notices.checkText') }}
      </UiNotice>
      <UiNotice v-if="quote.capture.currencySaid" tone="warning">
        {{ $t('editor.notices.currency', { said: quote.capture.currencySaid, business: auth.organization?.currency ?? 'EUR' }) }}
      </UiNotice>

      <div class="panes">
        <UiSegmented v-model="pane" :options="panes" :label="$t('editor.panes')" block />
      </div>

      <div class="layout" :class="`layout--${pane}`">
        <form class="stack stack-lg form" novalidate @submit.prevent="send">
          <UiFormErrors :errors="form.errors.value" :message="form.message.value" :trigger="form.submitted.value" :ids="errorIds" />

          <UiCard :title="$t('editor.customer.title')" :icon="PhUser">
            <CustomerFields v-model="form.data.customer" :errors="form.error" />
          </UiCard>

          <UiCard :title="$t('editor.job.title')" :icon="PhWrench">
            <div class="stack">
              <UiField id="f-title" :label="$t('editor.job.titleLabel')" :error="form.error('title') ?? form.error('items')">
                <template #default="{ id: fid, invalid, describedby }">
                  <UiInput :id="fid" v-model="form.data.title" size="lg" :placeholder="$t('editor.job.titlePlaceholder')" :invalid="invalid" :describedby="describedby" maxlength="200" />
                </template>
              </UiField>
              <ItemsEditor v-model="form.data.items" :errors="form.errors.value" />
            </div>
          </UiCard>

          <UiCard :title="$t('editor.when.title')" :icon="PhCalendarBlank">
            <div class="stack">
              <UiField id="f-when" :label="$t('editor.when.proposed')" :hint="$t('editor.when.proposedHint')" :error="form.error('proposedFor')">
                <template #default="{ id: fid, invalid, describedby }">
                  <UiInput :id="fid" v-model="form.data.proposedFor" :invalid="invalid" :describedby="describedby" maxlength="160" />
                </template>
              </UiField>
              <div class="chips" role="group" :aria-label="$t('editor.when.quick')">
                <button v-for="c in whenChips" :key="c" type="button" class="chip" :class="{ 'is-on': form.data.proposedFor === c }" @click="form.data.proposedFor = c">{{ c }}</button>
              </div>
              <div class="validity">
                <UiField id="f-validity" :label="$t('editor.when.validity')" :error="form.error('validityDays')">
                  <UiStepper id="f-validity" v-model="form.data.validityDays" :label="$t('editor.when.validity')" :min="1" :max="365" />
                </UiField>
                <p class="validity__until">
                  {{ quote.sentAt ? $t('editor.when.validUntil', { date: formatDate(validUntil, 'long') }) : $t('editor.when.validUntilIfToday', { date: formatDate(validUntil, 'long') }) }}
                </p>
              </div>
              <UiField id="f-notes" :label="$t('editor.when.notes')" :hint="$t('editor.when.notesHint')" :error="form.error('notes')" optional>
                <template #default="{ id: fid, invalid, describedby }">
                  <UiTextarea :id="fid" v-model="form.data.notes" :rows="2" :invalid="invalid" :describedby="describedby" maxlength="2000" />
                </template>
              </UiField>
              <UiField id="f-terms" :label="$t('editor.when.terms')" :error="form.error('terms')" optional>
                <template #default="{ id: fid, invalid, describedby }">
                  <UiTextarea :id="fid" v-model="form.data.terms" :rows="3" :invalid="invalid" :describedby="describedby" maxlength="4000" />
                </template>
              </UiField>
            </div>
          </UiCard>

          <UiCard v-if="quote.voice || quote.transcript || quote.source === 'VOICE' || quote.source === 'WHATSAPP'" :title="$t('editor.voice.title')" :icon="PhMicrophone">
            <div class="stack">
              <audio v-if="quote.voice" :src="quote.voice.url" controls preload="metadata" class="audio" :aria-label="$t('editor.voice.recording')" />
              <UiField id="f-transcript" :label="$t('editor.voice.transcript')" :hint="$t('editor.voice.transcriptHint')">
                <template #default="{ id: fid, describedby }">
                  <UiTextarea :id="fid" v-model="form.data.transcript" :rows="3" :describedby="describedby" maxlength="5000" />
                </template>
              </UiField>
              <p v-if="speech.interim.value" class="small subtle">{{ speech.interim.value }}…</p>
              <div class="cluster">
                <UiButton v-if="speech.supported" variant="secondary" size="sm" :icon="speech.listening.value ? PhStop : PhMicrophone" @click="dictate">
                  {{ speech.listening.value ? $t('editor.voice.stopDictating') : $t('editor.voice.dictate') }}
                </UiButton>
                <UiButton variant="soft" size="sm" :icon="PhArrowsClockwise" :loading="reading" :disabled="!form.data.transcript.trim()" @click="readAgain">{{ $t('editor.voice.readAgain') }}</UiButton>
              </div>
            </div>
          </UiCard>

          <div class="bottom-actions">
            <UiButton variant="secondary" :icon="PhFloppyDisk" :loading="form.processing.value && !sending" :disabled="sending" @click="save()">{{ $t('common.save') }}</UiButton>
            <UiButton type="submit" :icon="PhPaperPlaneTilt" :loading="sending">{{ quote.can.send ? $t('editor.send') : $t('editor.sendAgain') }}</UiButton>
          </div>
        </form>

        <aside class="preview" :aria-label="$t('editor.previewTitle')">
          <p class="preview__label"><PhClipboardText :size="16" weight="bold" aria-hidden="true" /> {{ $t('editor.previewTitle') }}</p>
          <QuoteDocument
            :business="{ name: auth.organization?.name ?? '', phone: auth.organization?.phone, trade }"
            :number="quote.number"
            :title="form.data.title || form.data.items[0]?.description || $t('quotes.untitled')"
            :customer-name="form.data.customer.name"
            :items="form.data.items"
            :totals="totals"
            :proposed-for="form.data.proposedFor"
            :valid-until="validUntil"
            :notes="form.data.notes"
            :terms="form.data.terms"
            :lang="form.data.customer.locale"
          />
        </aside>
      </div>

      <div class="sticky" role="region" :aria-label="$t('editor.items.total')">
        <div class="sticky__total">
          <span class="small muted">{{ $t('editor.items.total') }}</span>
          <span class="num">{{ formatMoney(totals.total) }}</span>
        </div>
        <UiButton variant="secondary" :icon="PhFloppyDisk" :loading="form.processing.value && !sending" :disabled="sending" :aria-label="$t('common.save')" @click="save()" />
        <UiButton :icon="PhPaperPlaneTilt" :loading="sending" @click="send">{{ $t('editor.sendShort') }}</UiButton>
      </div>
    </template>

    <SentDialog v-model:open="sentOpen" :message="sent" :title="$t('sent.quoteTitle', { number: quote?.number ?? '' })" @close="afterSent" />
  </AppPage>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(340px, 420px);
  gap: 24px;
  align-items: start;
}
.form {
  min-width: 0;
}
.preview {
  position: sticky;
  top: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.preview__label {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 4px;
  font-size: var(--text-xs);
  font-weight: 750;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--text-subtle);
}
.panes {
  display: none;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: -6px;
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
}
.chip:hover {
  color: var(--text);
  border-color: var(--gray-400);
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
.validity {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 8px 18px;
}
.validity__until {
  padding-bottom: 12px;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.audio {
  width: 100%;
  height: 40px;
}
.bottom-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.sticky {
  display: none;
}
@media (max-width: 1060px) {
  .layout {
    grid-template-columns: minmax(0, 1fr);
    /* room for the floating total bar */
    padding-bottom: 76px;
  }
  .panes {
    display: block;
    position: sticky;
    top: 8px;
    z-index: 5;
  }
  .layout--edit .preview,
  .layout--preview .form {
    display: none;
  }
  .preview {
    position: static;
  }
  .bottom-actions {
    display: none;
  }
  .sticky {
    position: fixed;
    z-index: 25;
    left: 12px;
    right: 12px;
    bottom: calc(var(--bottom-nav-h) + env(safe-area-inset-bottom) + 12px);
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 8px 8px 16px;
    background: var(--gray-900);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
  }
  .sticky__total {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    line-height: 1.15;
  }
  .sticky__total .muted {
    color: var(--text-inverse-muted);
  }
  .sticky__total .num {
    color: var(--text-inverse);
    font-family: var(--font-display);
    font-size: var(--text-xl);
    font-weight: 800;
  }
}
@media (min-width: 900px) and (max-width: 1060px) {
  .sticky {
    bottom: 16px;
  }
}
</style>
