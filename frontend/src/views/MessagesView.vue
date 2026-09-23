<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhArrowClockwise, PhChatCircleDots, PhPaperPlaneTilt, PhRobot, PhWhatsappLogo } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import UiTabs from '@/components/ui/UiTabs.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import { api } from '@/lib/api'
import { useForm } from '@/lib/form'
import { formatDateTime, formatPhone } from '@/lib/format'
import type { OutboundMessage } from '@/lib/messages'
import { useAuth } from '@/stores/auth'
import { useOverview } from '@/stores/overview'

type InboundMessage = { id: number; fromPhone: string; body: string | null; kind: string; handled: boolean; handledBy: string | null; receivedAt: string }

const { t } = useI18n()
const auth = useAuth()
const overview = useOverview()
const tab = ref<'out' | 'in'>('out')
const outbox = ref<OutboundMessage[] | null>(null)
const inbox = ref<InboundMessage[] | null>(null)
const sim = useForm({ from: '', body: '', kind: 'text' as 'text' | 'audio' })
const simKinds = computed(() => [
  { value: 'text' as const, label: t('messages.simText') },
  { value: 'audio' as const, label: t('messages.simVoice') },
])
const quickReplies = computed(() => t('messages.quickReplies').split(';').map((s) => s.trim()))

const simReplies = ref<OutboundMessage[] | null>(null)

function simKind(kind: 'text' | 'audio' | undefined) {
  if (!kind) return
  sim.data.kind = kind
  // A voice note only becomes a draft when it comes from the business phone.
  if (kind === 'audio' && auth.organization?.phone) sim.data.from = '+' + auth.organization.phone
}

const tabs = computed(() => [
  { value: 'out' as const, label: t('messages.outbox'), icon: PhPaperPlaneTilt, count: outbox.value?.length ?? null },
  { value: 'in' as const, label: t('messages.inbox'), icon: PhChatCircleDots, count: inbox.value?.length ?? null },
])
const anySimulated = computed(() => outbox.value?.some((m) => m.status === 'SIMULATED'))

const tone: Record<OutboundMessage['status'], 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info'> = {
  QUEUED: 'neutral',
  SENT: 'info',
  DELIVERED: 'primary',
  READ: 'success',
  FAILED: 'danger',
  SIMULATED: 'warning',
}

async function load() {
  const [o, i] = await Promise.all([api.get<OutboundMessage[]>('/messages'), api.get<InboundMessage[]>('/messages/inbound')])
  outbox.value = o
  inbox.value = i
}

onMounted(load)

async function retry(m: OutboundMessage) {
  const updated = await api.post<OutboundMessage>(`/messages/${m.id}/retry`)
  Object.assign(m, updated)
}

async function simulate() {
  const res = await sim.submit(() => api.post<{ replies: OutboundMessage[] }>('/dev/inbound', sim.data))
  if (res) {
    simReplies.value = res.replies
    sim.data.body = ''
    await Promise.all([load(), overview.refresh()])
  }
}
</script>

<template>
  <AppPage :title="$t('messages.title')" :subtitle="$t('messages.subtitle')">
    <UiNotice v-if="anySimulated" tone="info">{{ $t('messages.simulatedHint') }}</UiNotice>

    <div class="layout" :class="{ 'layout--demo': auth.demo }">
      <UiCard padding="none">
        <div class="tabs-wrap">
          <UiTabs v-model="tab" :tabs="tabs" :label="$t('messages.title')" />
        </div>
        <UiSkeleton v-if="!outbox" :lines="5" height="18px" class="pad" />
        <template v-else-if="tab === 'out'">
          <UiEmpty v-if="!outbox.length" :icon="PhPaperPlaneTilt" :title="$t('messages.empty')" compact />
          <ul v-else class="msgs">
            <li v-for="m in outbox" :key="m.id" class="msg">
              <div class="msg__head">
                <div class="msg__to">
                  <span class="strong">{{ m.recipientName || formatPhone(m.recipient) }}</span>
                  <span v-if="m.recipientName" class="small subtle">{{ formatPhone(m.recipient) }}</span>
                </div>
                <UiBadge :tone="tone[m.status]" dot size="sm">{{ $t(`messages.status.${m.status}`) }}</UiBadge>
              </div>
              <p class="msg__body">{{ m.body }}</p>
              <p v-if="m.error" class="small" style="color: var(--danger-text)">{{ m.error }}</p>
              <div class="msg__foot">
                <span class="xsmall subtle">{{ formatDateTime(m.createdAt) }}</span>
                <div class="cluster">
                  <UiButton v-if="m.status === 'FAILED'" size="sm" variant="ghost" :icon="PhArrowClockwise" @click="retry(m)">{{ $t('messages.retry') }}</UiButton>
                  <UiButton size="sm" variant="secondary" :icon="PhWhatsappLogo" :href="m.waMeUrl" target="_blank">{{ $t('messages.openWhatsApp') }}</UiButton>
                </div>
              </div>
            </li>
          </ul>
        </template>
        <template v-else>
          <UiEmpty v-if="!inbox?.length" :icon="PhChatCircleDots" :title="$t('messages.emptyInbox')" compact />
          <ul v-else class="msgs">
            <li v-for="m in inbox" :key="m.id" class="msg">
              <div class="msg__head">
                <span class="strong">{{ formatPhone(m.fromPhone) }}</span>
                <UiBadge :tone="m.handled ? 'success' : 'warning'" size="sm">{{ m.handled ? $t('messages.handledBy') : $t('messages.unhandled') }}</UiBadge>
              </div>
              <p class="msg__body">{{ m.body ?? `[${m.kind}]` }}</p>
              <span class="xsmall subtle">{{ formatDateTime(m.receivedAt) }}</span>
            </li>
          </ul>
        </template>
      </UiCard>

      <UiCard v-if="auth.demo" :title="$t('messages.simulator')" :subtitle="$t('messages.simulatorHint')" :icon="PhRobot" tone="muted">
        <form class="stack" novalidate @submit.prevent="simulate">
          <UiSegmented :model-value="sim.data.kind" :options="simKinds" :label="$t('messages.simKind')" block @update:model-value="simKind" />
          <UiField id="f-from" :label="$t('messages.from')" :hint="sim.data.kind === 'audio' ? $t('messages.simVoiceHint') : undefined" :error="sim.error('from')">
            <template #default="{ id, invalid, describedby }">
              <UiInput :id="id" v-model="sim.data.from" type="tel" inputmode="tel" :invalid="invalid" :describedby="describedby" />
            </template>
          </UiField>
          <template v-if="sim.data.kind === 'text'">
            <UiField id="f-body" :label="$t('messages.body')" :error="sim.error('body')">
              <template #default="{ id, invalid }">
                <UiTextarea :id="id" v-model="sim.data.body" :rows="2" :invalid="invalid" />
              </template>
            </UiField>
            <div class="quick" role="group" :aria-label="$t('messages.quickLabel')">
              <button v-for="q in quickReplies" :key="q" type="button" class="quick__chip" @click="sim.data.body = q">{{ q }}</button>
            </div>
          </template>
          <UiButton type="submit" :icon="PhPaperPlaneTilt" :loading="sim.processing.value">{{ $t('messages.simulate') }}</UiButton>
          <div v-if="simReplies" class="stack stack-sm">
            <p v-for="r in simReplies" :key="r.id" class="bubble">{{ r.body }}</p>
          </div>
        </form>
      </UiCard>
    </div>
  </AppPage>
</template>

<style scoped>
.layout {
  display: grid;
  gap: 20px;
  align-items: start;
}
.layout--demo {
  grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
}
.quick {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: -6px;
}
.quick__chip {
  min-height: 32px;
  padding: 0 10px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-pill);
  background: var(--surface);
  color: var(--text-muted);
  font-size: var(--text-xs);
  font-weight: 600;
}
.quick__chip:hover {
  color: var(--text);
}
.quick__chip:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--focus-ring);
}
@media (max-width: 900px) {
  .layout--demo {
    grid-template-columns: minmax(0, 1fr);
  }
}
.tabs-wrap {
  padding: 6px 12px 0;
}
.pad {
  padding: 20px;
}
.msgs {
  margin: 0;
  padding: 0;
  list-style: none;
}
.msg {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}
.msg:last-child {
  border-bottom: 0;
}
.msg__head,
.msg__foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.msg__to {
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}
.msg__body {
  padding: 12px 14px;
  font-size: var(--text-sm);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  background: var(--surface-muted);
  border: 1px solid var(--border);
  border-radius: 4px var(--radius) var(--radius) var(--radius);
}
.bubble {
  padding: 10px 12px;
  font-size: var(--text-sm);
  white-space: pre-wrap;
  background: var(--accent-soft);
  border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent);
  color: var(--text);
  border-radius: var(--radius) 4px var(--radius) var(--radius);
  box-shadow: var(--shadow-xs);
}
</style>
