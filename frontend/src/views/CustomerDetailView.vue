<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { PhChatCircleText, PhCoins, PhMapPin, PhMicrophone, PhNotepad, PhPencilSimple, PhPercent, PhPhone, PhWhatsappLogo } from '@phosphor-icons/vue'
import AppPage from '@/components/layout/AppPage.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiCard from '@/components/ui/UiCard.vue'
import UiEmpty from '@/components/ui/UiEmpty.vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'
import UiStat from '@/components/ui/UiStat.vue'
import CustomerDialog from '@/components/quote/CustomerDialog.vue'
import QuoteCard from '@/components/quote/QuoteCard.vue'
import { api, ApiError } from '@/lib/api'
import { formatMoney, formatPercent, formatPhone } from '@/lib/format'
import type { CustomerRow, QuoteDetail, QuoteSummary } from '@/lib/quote'
import { telLink, waLink } from '@/lib/whatsapp'
import { useToasts } from '@/stores/toasts'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toasts = useToasts()
const customer = ref<(CustomerRow & { quotes: QuoteSummary[] }) | null>(null)
const editing = ref(false)
const starting = ref(false)

const conversion = computed(() => (customer.value?.quotesCount ? customer.value.wonCount / customer.value.quotesCount : null))

async function load() {
  customer.value = await api.get(`/customers/${route.params.id}`)
}

async function newQuote() {
  starting.value = true
  try {
    const quote = await api.post<QuoteDetail>('/quotes', { customerId: customer.value?.id })
    await router.push({ name: 'quote-edit', params: { id: quote.id } })
  } catch (e) {
    toasts.error(e instanceof ApiError ? e.message : t('errors.generic'))
  } finally {
    starting.value = false
  }
}

onMounted(load)
</script>

<template>
  <AppPage :title="customer?.name ?? ''" :back="{ name: 'customers' }" :back-label="$t('customers.title')">
    <template #meta>
      <template v-if="customer">
        <span v-if="customer.phone" class="meta num"><PhPhone :size="15" weight="bold" aria-hidden="true" /> {{ formatPhone(customer.phone) }}</span>
        <span v-if="customer.address" class="meta"><PhMapPin :size="15" weight="bold" aria-hidden="true" /> {{ customer.address }}</span>
        <span class="meta"><PhChatCircleText :size="15" weight="bold" aria-hidden="true" /> {{ $t(`languages.${customer.locale}`) }}</span>
      </template>
    </template>
    <template #actions>
      <template v-if="customer">
        <UiButton v-if="customer.phone" variant="inverse" :icon="PhPhone" :href="telLink(customer.phone) ?? undefined">{{ $t('common.call') }}</UiButton>
        <UiButton v-if="customer.phone" variant="inverse" :icon="PhWhatsappLogo" :href="waLink(customer.phone) ?? undefined" target="_blank">{{ $t('common.whatsapp') }}</UiButton>
        <UiButton variant="inverse" :icon="PhPencilSimple" @click="editing = true">{{ $t('common.edit') }}</UiButton>
        <UiButton :icon="PhMicrophone" :loading="starting" @click="newQuote">{{ $t('customers.newQuote') }}</UiButton>
      </template>
    </template>

    <UiSkeleton v-if="!customer" card :lines="6" />
    <template v-else>
      <div class="tiles">
        <UiStat :label="$t('customers.quoted')" :value="formatMoney(customer.quotedCents)" tone="neutral" :icon="PhNotepad" :hint="$t('customers.quotesCount', { n: customer.quotesCount }, customer.quotesCount)" />
        <UiStat :label="$t('customers.won')" :value="formatMoney(customer.wonCents)" tone="success" :icon="PhCoins" :hint="$t('customers.wonOf', { won: customer.wonCount, total: customer.quotesCount })" />
        <UiStat :label="$t('stats.conversion')" :value="formatPercent(conversion)" tone="info" :icon="PhPercent" />
      </div>

      <UiCard :title="$t('customers.history')" :icon="PhNotepad">
        <div v-if="customer.quotes.length" class="quotes">
          <QuoteCard v-for="q in customer.quotes" :key="q.id" :quote="q" />
        </div>
        <UiEmpty v-else compact :icon="PhNotepad" :title="$t('customers.noQuotes')">
          <UiButton :icon="PhMicrophone" :loading="starting" @click="newQuote">{{ $t('customers.newQuote') }}</UiButton>
        </UiEmpty>
      </UiCard>
    </template>

    <CustomerDialog v-model:open="editing" :customer="customer" @saved="load" />
  </AppPage>
</template>

<style scoped>
.meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: var(--text-sm);
  color: var(--text-inverse-muted);
}
.tiles {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}
.quotes {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
}
@media (max-width: 720px) {
  .tiles {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
