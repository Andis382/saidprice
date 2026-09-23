<script setup lang="ts">
import { ref, watch } from 'vue'
import { PhAddressBook, PhMapPin, PhPhone, PhUserPlus, PhX } from '@phosphor-icons/vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiField from '@/components/ui/UiField.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSearch from '@/components/ui/UiSearch.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import UiSpinner from '@/components/ui/UiSpinner.vue'
import { api, query } from '@/lib/api'
import { formatPhone } from '@/lib/format'
import type { Customer, CustomerDraft } from '@/lib/quote'

/**
 * Who the quote is for. Editing these fields edits the customer; picking someone from the
 * list links the quote to them. A new name and number add a new customer when saved.
 */
const model = defineModel<CustomerDraft>({ required: true })
defineProps<{ errors: (field: string) => string | undefined }>()

const picking = ref(false)
const search = ref('')
const results = ref<Customer[] | null>(null)
const languages = [
  { value: 'sq' as const, label: 'Shqip' },
  { value: 'en' as const, label: 'English' },
]

let timer = 0
watch([search, picking], () => {
  if (!picking.value) return
  window.clearTimeout(timer)
  timer = window.setTimeout(async () => {
    results.value = await api.get<Customer[]>('/customers' + query({ q: search.value.trim() }))
  }, 200)
})

function pick(c: Customer) {
  model.value = { id: c.id, name: c.name, phone: c.phone ? formatPhone(c.phone) : '', address: c.address ?? '', locale: c.locale }
  picking.value = false
  search.value = ''
}

function startNew() {
  model.value = { id: null, name: '', phone: '', address: '', locale: model.value.locale }
  picking.value = false
}
</script>

<template>
  <div class="cust">
    <div class="cust__top">
      <UiBadge :tone="model.id ? 'info' : 'accent'" size="sm">{{ model.id ? $t('editor.customer.existing') : $t('editor.customer.new') }}</UiBadge>
      <div class="cust__switch">
        <UiButton v-if="!picking" variant="ghost" size="sm" :icon="PhAddressBook" @click="picking = true">{{ $t('editor.customer.pick') }}</UiButton>
        <UiButton v-if="model.id && !picking" variant="ghost" size="sm" :icon="PhUserPlus" @click="startNew">{{ $t('editor.customer.startNew') }}</UiButton>
        <UiButton v-if="picking" variant="ghost" size="sm" :icon="PhX" @click="picking = false">{{ $t('common.close') }}</UiButton>
      </div>
    </div>

    <div v-if="picking" class="picker">
      <UiSearch v-model="search" :placeholder="$t('editor.customer.searchPlaceholder')" />
      <div v-if="!results" class="picker__loading"><UiSpinner /></div>
      <ul v-else class="picker__list">
        <li v-for="c in results.slice(0, 8)" :key="c.id">
          <button type="button" class="picker__item" @click="pick(c)">
            <span class="strong">{{ c.name }}</span>
            <span class="small muted">{{ [formatPhone(c.phone), c.address].filter((x) => x && x !== '—').join(' · ') }}</span>
          </button>
        </li>
        <li v-if="!results.length" class="small muted picker__none">{{ $t('editor.customer.noMatch') }}</li>
      </ul>
    </div>

    <div class="grid-2">
      <UiField id="f-customer-name" :label="$t('editor.customer.name')" :error="errors('customer.name')" required>
        <template #default="{ id, invalid, describedby }">
          <UiInput :id="id" v-model="model.name" :invalid="invalid" :describedby="describedby" autocomplete="off" />
        </template>
      </UiField>
      <UiField id="f-customer-phone" :label="$t('editor.customer.phone')" :hint="$t('editor.customer.phoneHint')" :error="errors('customer.phone')">
        <template #default="{ id, invalid, describedby }">
          <UiInput :id="id" v-model="model.phone" type="tel" inputmode="tel" :icon="PhPhone" :invalid="invalid" :describedby="describedby" autocomplete="off" />
        </template>
      </UiField>
      <UiField id="f-customer-address" :label="$t('editor.customer.address')" :error="errors('customer.address')" optional>
        <template #default="{ id, invalid, describedby }">
          <UiInput :id="id" v-model="model.address" :icon="PhMapPin" :invalid="invalid" :describedby="describedby" autocomplete="off" />
        </template>
      </UiField>
      <UiField id="f-customer-locale" :label="$t('editor.customer.language')" :hint="$t('editor.customer.languageHint')">
        <UiSegmented v-model="model.locale" :options="languages" :label="$t('editor.customer.language')" block />
      </UiField>
    </div>
  </div>
</template>

<style scoped>
.cust {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.cust__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
}
.cust__switch {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}
.picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface-muted);
}
.picker__loading {
  display: grid;
  place-items: center;
  padding: 14px;
}
.picker__list {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 280px;
  margin: 0;
  padding: 0;
  overflow: auto;
  list-style: none;
}
.picker__item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1px;
  width: 100%;
  min-height: 48px;
  padding: 8px 12px;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--text);
  text-align: left;
}
.picker__item:hover {
  background: var(--surface);
  border-color: var(--border);
}
.picker__item:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--focus-ring);
}
.picker__none {
  padding: 10px 12px;
}
</style>
