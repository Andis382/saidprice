<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { PhEye, PhEyeSlash, PhPaperPlaneTilt, PhPencilSimple, PhX } from '@phosphor-icons/vue'
import UiAvatar from '@/components/ui/UiAvatar.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'
import { api, ApiError } from '@/lib/api'
import { formatMoney, formatRelative } from '@/lib/format'
import type { NudgeRow } from '@/lib/quote'
import type { OutboundMessage } from '@/lib/messages'
import { useToasts } from '@/stores/toasts'

/** A drafted follow-up: read it, change a word if needed, send it — or let it go. */
const props = withDefaults(defineProps<{ nudge: NudgeRow; showQuote?: boolean }>(), { showQuote: true })
const emit = defineEmits<{ sent: [message: OutboundMessage]; dismissed: [] }>()

const { t } = useI18n()
const toasts = useToasts()
const editing = ref(false)
const body = ref(props.nudge.body)
const busy = ref<'send' | 'dismiss' | null>(null)

async function send() {
  busy.value = 'send'
  try {
    const res = await api.post<{ message: OutboundMessage }>(`/nudges/${props.nudge.id}/send`, { body: body.value })
    editing.value = false
    emit('sent', res.message)
  } catch (e) {
    toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
  } finally {
    busy.value = null
  }
}

function cancelEdit() {
  editing.value = false
  body.value = props.nudge.body
}

async function dismiss() {
  busy.value = 'dismiss'
  try {
    await api.post(`/nudges/${props.nudge.id}/dismiss`)
    toasts.info(t('followUps.dismissed'))
    emit('dismissed')
  } catch (e) {
    toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <article class="nudge">
    <header v-if="showQuote" class="nudge__head">
      <UiAvatar :name="nudge.quote.customer?.name" :size="40" />
      <div class="nudge__who">
        <p class="nudge__name">
          {{ nudge.quote.customer?.name }}
          <RouterLink :to="{ name: 'quote', params: { id: nudge.quote.id } }" class="nudge__number">{{ nudge.quote.number }}</RouterLink>
        </p>
        <p class="nudge__job">
          <span class="nudge__title">{{ nudge.quote.title }}</span>
          <span class="nudge__price num">{{ formatMoney(nudge.quote.totalCents) }}</span>
        </p>
      </div>
      <UiBadge :tone="nudge.sequence === 1 ? 'primary' : 'warning'" size="sm">{{ $t(nudge.sequence === 1 ? 'followUps.first' : 'followUps.second') }}</UiBadge>
    </header>

    <p v-if="showQuote" class="nudge__facts">
      <span>{{ $t('followUps.sentWhen', { when: formatRelative(nudge.quote.sentAt) }) }}</span>
      <span v-if="nudge.quote.viewedAt" class="nudge__seen"><PhEye :size="14" weight="bold" aria-hidden="true" /> {{ $t('followUps.viewedWhen', { when: formatRelative(nudge.quote.viewedAt) }) }}</span>
      <span v-else class="nudge__seen"><PhEyeSlash :size="14" weight="bold" aria-hidden="true" /> {{ $t('followUps.notOpened') }}</span>
    </p>

    <div v-if="!editing" class="bubble">
      <p>{{ body }}</p>
      <span class="bubble__lang">{{ $t('followUps.inLanguage', { lang: $t(`languages.${nudge.customerLocale}`) }) }}</span>
    </div>
    <div v-else>
      <label class="visually-hidden" :for="`nudge-${nudge.id}`">{{ $t('followUps.textLabel') }}</label>
      <UiTextarea :id="`nudge-${nudge.id}`" v-model="body" :rows="4" maxlength="1000" />
    </div>

    <div class="nudge__actions">
      <UiButton :icon="PhPaperPlaneTilt" :loading="busy === 'send'" :disabled="!body.trim() || busy !== null" @click="send">{{ $t('followUps.send') }}</UiButton>
      <UiButton v-if="!editing" variant="secondary" :icon="PhPencilSimple" :disabled="busy !== null" @click="editing = true">{{ $t('common.edit') }}</UiButton>
      <UiButton v-else variant="secondary" :disabled="busy !== null" @click="cancelEdit">{{ $t('common.cancel') }}</UiButton>
      <UiButton variant="ghost" :icon="PhX" :loading="busy === 'dismiss'" :disabled="busy !== null" class="nudge__dismiss" @click="dismiss">{{ $t('followUps.dismiss') }}</UiButton>
    </div>
  </article>
</template>

<style scoped>
.nudge {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 18px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm), var(--highlight);
}
.nudge__head {
  display: flex;
  align-items: center;
  gap: 12px;
}
.nudge__who {
  flex: 1;
  min-width: 0;
}
.nudge__name {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 10px;
  font-weight: 700;
}
.nudge__number {
  font-size: var(--text-xs);
  font-weight: 650;
  color: var(--text-subtle);
  text-decoration: none;
}
.nudge__number:hover {
  color: var(--primary);
  text-decoration: underline;
}
.nudge__job {
  display: flex;
  gap: 6px;
  min-width: 0;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.nudge__title {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.nudge__price {
  flex: none;
  font-weight: 700;
  color: var(--text);
}
.nudge__price::before {
  content: '·';
  margin-right: 6px;
  color: var(--text-subtle);
  font-weight: 400;
}
.nudge__facts {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 16px;
  font-size: var(--text-xs);
  color: var(--text-subtle);
}
.nudge__seen {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.bubble {
  position: relative;
  padding: 12px 14px 22px;
  border-radius: 4px var(--radius) var(--radius) var(--radius);
  background: var(--accent-soft);
  border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent);
  font-size: var(--text-sm);
  line-height: 1.55;
}
.bubble__lang {
  position: absolute;
  right: 12px;
  bottom: 5px;
  font-size: 10.5px;
  font-weight: 650;
  color: var(--accent-soft-text);
  opacity: 0.8;
}
.nudge__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.nudge__dismiss {
  margin-left: auto;
}
@media (max-width: 520px) {
  .nudge {
    padding: 14px;
  }
  .nudge__actions :deep(.btn) {
    flex: 1;
  }
  .nudge__dismiss {
    margin-left: 0;
  }
}
</style>
