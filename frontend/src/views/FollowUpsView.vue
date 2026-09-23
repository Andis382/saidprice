<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { PhBellSimpleSlash, PhCheckCircle, PhClockCounterClockwise, PhPaperPlaneTilt } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import NudgeCard from '@/components/quote/NudgeCard.vue'
import SentDialog from '@/components/quote/SentDialog.vue'
import { api } from '@/lib/api'
import { formatRelative } from '@/lib/format'
import type { OutboundMessage } from '@/lib/messages'
import type { NudgeRow } from '@/lib/quote'
import { useOverview } from '@/stores/overview'

const { t } = useI18n()
const overview = useOverview()
const pending = ref<NudgeRow[] | null>(null)
const recent = ref<NudgeRow[]>([])
const sent = ref<OutboundMessage | null>(null)
const sentOpen = ref(false)

async function load() {
  const data = await api.get<{ pending: NudgeRow[]; recent: NudgeRow[] }>('/nudges')
  pending.value = data.pending
  recent.value = data.recent
  overview.refresh()
}

function onSent(message: OutboundMessage) {
  sent.value = message
  sentOpen.value = true
  load()
}

onMounted(load)
</script>

<template>
  <AppPage :title="$t('followUps.title')" :subtitle="$t('followUps.subtitle')">
    <div class="layout">
      <div class="stack">
        <template v-if="!pending">
          <UiSkeleton card :lines="5" />
          <UiSkeleton card :lines="5" />
        </template>
        <UiEmpty v-else-if="!pending.length" :icon="PhCheckCircle" :title="$t('followUps.emptyTitle')" :text="$t('followUps.emptyText')" />
        <template v-else>
          <p class="count" role="status">{{ $t('followUps.count', { n: pending.length }, pending.length) }}</p>
          <TransitionGroup name="list" tag="div" class="stack">
            <NudgeCard v-for="n in pending" :key="n.id" :nudge="n" @sent="onSent" @dismissed="load" />
          </TransitionGroup>
        </template>
      </div>

      <aside class="stack">
        <UiCard :title="$t('followUps.howTitle')" tone="muted">
          <ol class="how">
            <li>{{ t('followUps.how.one') }}</li>
            <li>{{ t('followUps.how.two') }}</li>
            <li>{{ t('followUps.how.three') }}</li>
          </ol>
        </UiCard>
        <UiCard :title="$t('followUps.recent')" :icon="PhClockCounterClockwise">
          <ul v-if="recent.length" class="recent">
            <li v-for="n in recent" :key="n.id">
              <component :is="n.status === 'SENT' ? PhPaperPlaneTilt : PhBellSimpleSlash" :size="16" weight="bold" class="recent__icon" :class="{ 'is-sent': n.status === 'SENT' }" aria-hidden="true" />
              <div class="recent__body">
                <RouterLink :to="{ name: 'quote', params: { id: n.quote.id } }" class="recent__who">{{ n.quote.customer?.name }}</RouterLink>
                <p class="small muted">
                  {{ n.status === 'SENT' ? $t('followUps.recentSent', { when: formatRelative(n.sentAt) }) : $t('followUps.recentDismissed', { when: formatRelative(n.dismissedAt) }) }}
                </p>
              </div>
            </li>
          </ul>
          <p v-else class="small muted">{{ $t('followUps.recentEmpty') }}</p>
        </UiCard>
      </aside>
    </div>

    <SentDialog v-model:open="sentOpen" :message="sent" :title="$t('sent.nudgeTitle')" />
  </AppPage>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(280px, 340px);
  gap: 20px;
  align-items: start;
}
/* sits on the lower edge of the dark band */
.count {
  font-size: var(--text-sm);
  font-weight: 650;
  color: var(--text-inverse-muted);
}
.how {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding-left: 20px;
  font-size: var(--text-sm);
  color: var(--text-muted);
}
.recent {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.recent li {
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr);
  gap: 10px;
  align-items: start;
}
.recent__icon {
  width: 28px;
  height: 28px;
  padding: 6px;
  border-radius: 50%;
  background: var(--surface-sunken);
  color: var(--text-subtle);
}
.recent__icon.is-sent {
  background: var(--primary-soft);
  color: var(--primary-soft-text);
}
.recent__who {
  font-weight: 650;
  font-size: var(--text-sm);
  color: var(--text);
  text-decoration: none;
}
.recent__who:hover {
  text-decoration: underline;
}
.list-leave-active {
  transition:
    opacity 220ms var(--ease),
    transform 220ms var(--ease);
}
.list-leave-to {
  opacity: 0;
  transform: translateX(24px);
}
@media (max-width: 960px) {
  .layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
