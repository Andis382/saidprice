<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PhMapPin, PhPhone } from '@phosphor-icons/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiFormErrors from '@/components/ui/UiFormErrors.vue'
import UiInput from '@/components/ui/UiInput.vue'
import UiSegmented from '@/components/ui/UiSegmented.vue'
import { api } from '@/lib/api'
import { useForm } from '@/lib/form'
import type { Customer } from '@/lib/quote'
import { useAuth } from '@/stores/auth'
import { useToasts } from '@/stores/toasts'

/** Add a customer, or correct one: name, number, address and the language they read. */
const open = defineModel<boolean>('open', { default: false })
const props = withDefaults(defineProps<{ customer?: Customer | null }>(), { customer: null })
const emit = defineEmits<{ saved: [customer: Customer] }>()

const { t } = useI18n()
const auth = useAuth()
const toasts = useToasts()
const form = useForm({ name: '', phone: '', address: '', locale: 'sq' as 'en' | 'sq' })
const languages = [
  { value: 'sq' as const, label: 'Shqip' },
  { value: 'en' as const, label: 'English' },
]

watch(open, (value) => {
  if (!value) return
  const c = props.customer
  form.reset(c ? { name: c.name, phone: c.phone ? '+' + c.phone : '', address: c.address ?? '', locale: c.locale } : { locale: auth.organization?.locale ?? 'sq' })
})

async function save() {
  const saved = await form.submit(() =>
    props.customer ? api.put<Customer>(`/customers/${props.customer.id}`, form.data) : api.post<Customer>('/customers', form.data),
  )
  if (saved) {
    toasts.success(t(props.customer ? 'customers.updated' : 'customers.added', { name: saved.name }))
    open.value = false
    emit('saved', saved)
  }
}
</script>

<template>
  <UiDialog v-model:open="open" :title="customer ? $t('customers.editTitle') : $t('customers.addTitle')" :description="$t('customers.dialogText')">
    <form id="customer-form" class="stack" novalidate @submit.prevent="save">
      <UiFormErrors :errors="form.errors.value" :message="form.message.value" :trigger="form.submitted.value" :ids="{ name: 'c-name', phone: 'c-phone', address: 'c-address' }" />
      <UiField id="c-name" :label="$t('editor.customer.name')" :error="form.error('name')" required>
        <template #default="{ id, invalid, describedby }">
          <UiInput :id="id" v-model="form.data.name" :invalid="invalid" :describedby="describedby" autocomplete="off" />
        </template>
      </UiField>
      <UiField id="c-phone" :label="$t('editor.customer.phone')" :hint="$t('editor.customer.phoneHint')" :error="form.error('phone')">
        <template #default="{ id, invalid, describedby }">
          <UiInput :id="id" v-model="form.data.phone" type="tel" inputmode="tel" :icon="PhPhone" :invalid="invalid" :describedby="describedby" autocomplete="off" />
        </template>
      </UiField>
      <UiField id="c-address" :label="$t('editor.customer.address')" :error="form.error('address')" optional>
        <template #default="{ id, invalid, describedby }">
          <UiInput :id="id" v-model="form.data.address" :icon="PhMapPin" :invalid="invalid" :describedby="describedby" autocomplete="off" />
        </template>
      </UiField>
      <UiField id="c-locale" :label="$t('editor.customer.language')" :hint="$t('editor.customer.languageHint')">
        <UiSegmented v-model="form.data.locale" :options="languages" :label="$t('editor.customer.language')" />
      </UiField>
    </form>
    <template #footer>
      <UiButton variant="ghost" @click="open = false">{{ $t('common.cancel') }}</UiButton>
      <UiButton type="submit" form="customer-form" :loading="form.processing.value">{{ $t('common.save') }}</UiButton>
    </template>
  </UiDialog>
</template>
