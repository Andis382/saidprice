<script setup lang="ts">
import { computed } from 'vue'
import { PhCheckCircle, PhWhatsappLogo } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import type { OutboundMessage } from '@/lib/messages'

/**
 * After a quote or follow-up goes out. With no WhatsApp account connected the message waits
 * in the outbox, and one tap opens it in the tradesperson's own WhatsApp, ready to send.
 */
const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{ message: OutboundMessage | null; title: string }>()
const emit = defineEmits<{ close: [] }>()

const simulated = computed(() => props.message?.status === 'SIMULATED')
</script>

<template>
  <UiDialog v-model:open="open" size="sm" :title="title" :description="simulated ? $t('sent.simulated') : $t('sent.delivered')" @close="emit('close')">
    <div v-if="message" class="sent">
      <p class="sent__to">
        <PhCheckCircle :size="18" weight="fill" aria-hidden="true" />
        {{ $t('sent.to', { name: message.recipientName ?? message.recipient }) }}
      </p>
      <div class="bubble">
        <p>{{ message.body }}</p>
      </div>
    </div>
    <template #footer>
      <UiButton variant="ghost" @click="open = false">{{ $t('common.done') }}</UiButton>
      <UiButton v-if="message && simulated" :href="message.waMeUrl" target="_blank" :icon="PhWhatsappLogo">{{ $t('sent.openWhatsApp') }}</UiButton>
    </template>
  </UiDialog>
</template>

<style scoped>
.sent {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.sent__to {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 650;
  font-size: var(--text-sm);
  color: var(--success-text);
}
.bubble {
  position: relative;
  max-height: 280px;
  overflow: auto;
  padding: 12px 14px;
  border-radius: 4px var(--radius) var(--radius) var(--radius);
  background: var(--accent-soft);
  border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent);
  font-size: var(--text-sm);
  white-space: pre-line;
  overflow-wrap: anywhere;
}
</style>
