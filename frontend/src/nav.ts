import type { Component } from 'vue'
import { PhAddressBook, PhBellRinging, PhChartBar, PhChatCircleDots, PhGearSix, PhMicrophone, PhNotepad } from '@phosphor-icons/vue'

export type NavItem = {
  /** route name */
  name: string
  /** i18n key for the label */
  label: string
  icon: Component
  /** shown in the phone's bottom bar (max 3 besides the call to action); others go under "More" */
  primary?: boolean
  /** the one action every screen offers: a raised button in the phone bar, a yellow button on desktop */
  cta?: boolean
  /** key in the overview store whose count shows as a badge */
  badge?: 'followUps'
  /** reached from the account menu on desktop (and the More sheet on phones), not the top bar */
  accountMenu?: boolean
  roles?: string[]
}

export const NAV: NavItem[] = [
  { name: 'quotes', label: 'nav.quotes', icon: PhNotepad, primary: true },
  { name: 'follow-ups', label: 'nav.followUps', icon: PhBellRinging, primary: true, badge: 'followUps' },
  { name: 'quote-new', label: 'nav.newQuote', icon: PhMicrophone, cta: true },
  { name: 'stats', label: 'nav.stats', icon: PhChartBar, primary: true },
  { name: 'customers', label: 'nav.customers', icon: PhAddressBook },
  { name: 'messages', label: 'nav.messages', icon: PhChatCircleDots },
  { name: 'settings', label: 'nav.settings', icon: PhGearSix, accountMenu: true },
]
