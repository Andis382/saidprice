/** Quote types as the API sends them, and the small rules the screens share. */

export type QuoteStatus = 'DRAFT' | 'SENT' | 'VIEWED' | 'ACCEPTED' | 'DECLINED' | 'SILENT' | 'EXPIRED'
export type Outcome = 'WON' | 'LOST'
export type ItemKind = 'PARTS' | 'LABOUR' | 'OTHER'
export type Tone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'accent'

export type QuoteItem = {
  id?: number
  description: string
  qty: number
  unit: string | null
  unitPriceCents: number
  kind: ItemKind
  totalCents?: number
}

export type Customer = {
  id: number
  name: string
  phone: string | null
  address: string | null
  locale: 'en' | 'sq'
}

/** The customer as the editor holds it: an existing one (id) or a new one to add on save. */
export type CustomerDraft = { id: number | null; name: string; phone: string; address: string; locale: 'en' | 'sq' }

/** A customer with their history, as the customer list shows it. */
export type CustomerRow = Customer & {
  quotesCount: number
  sentCount: number
  wonCount: number
  wonCents: number
  quotedCents: number
  lastQuoteAt: string | null
}

export type QuoteSummary = {
  id: number
  number: string
  title: string | null
  status: QuoteStatus
  outcome: Outcome | null
  source: 'VOICE' | 'TYPED' | 'WHATSAPP' | 'DUPLICATE'
  totalCents: number
  customer: { id: number; name: string; phone: string | null } | null
  proposedFor: string | null
  validUntil: string | null
  sentAt: string | null
  viewedAt: string | null
  respondedAt: string | null
  createdAt: string
  updatedAt: string
  nudgeCount: number
  hasVoice: boolean
  pendingNudge: boolean
  hasQuestion: boolean
}

export type QuoteEvent = {
  id: number
  type: string
  actor: 'USER' | 'CUSTOMER' | 'SYSTEM'
  userName: string | null
  channel: 'APP' | 'LINK' | 'WHATSAPP' | null
  body: string | null
  meta: Record<string, string | number | null>
  createdAt: string
}

export type Nudge = {
  id: number
  quoteId: number
  sequence: number
  body: string
  status: 'DRAFTED' | 'SENT' | 'DISMISSED'
  createdAt: string
  sentAt: string | null
  dismissedAt: string | null
}

/** A follow-up with the quote it belongs to, as the inbox lists it. */
export type NudgeRow = Nudge & { quote: QuoteSummary; customerLocale: 'en' | 'sq' }

export type Totals = { total: number; parts: number; labour: number; other: number }

export type QuoteDetail = Omit<QuoteSummary, 'customer'> & {
  customer: Customer | null
  items: QuoteItem[]
  totals: Totals
  validityDays: number
  notes: string | null
  terms: string | null
  transcript: string | null
  voice: { url: string; contentType: string } | null
  responseNote: string | null
  lostReason: string | null
  outcomeAt: string | null
  lastNudgedAt: string | null
  publicUrl: string
  events: QuoteEvent[]
  nudges: Nudge[]
  capture: { engine: 'parser' | 'ai' | null; currencySaid: string | null; duplicatedFrom: string | null }
  nextFollowUpAt: string | null
  lastMessage: { id: number; status: string; templateKey: string; waMeUrl: string; createdAt: string } | null
  can: { edit: boolean; send: boolean; resend: boolean; outcome: boolean; delete: boolean }
}

export type PublicQuote = {
  number: string
  title: string
  status: QuoteStatus
  locale: 'en' | 'sq'
  currency: string
  business: { name: string; phone: string | null; trade: string | null }
  customerName: string | null
  items: QuoteItem[]
  totals: Totals
  totalCents: number
  proposedFor: string | null
  validUntil: string | null
  notes: string | null
  terms: string | null
  sentAt: string | null
  respondedAt: string | null
  responseNote: string | null
  canAnswer: boolean
}

export type WeekStats = {
  weekStart?: string
  sent: number
  accepted: number
  declined: number
  silent: number
  open: number
  won: number
  quotedCents: number
  wonCents: number
  conversion: number | null
  medianAnswerHours: number | null
}

export type ParsedQuote = {
  locale: 'en' | 'sq'
  customerName: string | null
  customerPhone: string | null
  customerAddress: string | null
  title: string | null
  items: QuoteItem[]
  proposedFor: string | null
  notes: string | null
  currency: string | null
  engine: 'parser' | 'ai'
  totalCents: number
}

export const BOARD_COLUMNS = ['DRAFT', 'SENT', 'VIEWED', 'ACCEPTED', 'DECLINED', 'SILENT'] as const
export type BoardColumn = (typeof BOARD_COLUMNS)[number]

/**
 * Same arithmetic as the server (QuoteTotals::line): quantity in whole hundredths times the
 * price in cents is exact, and the line is rounded to cents once, halves up.
 */
export function lineTotal(qty: number | null | undefined, unitPriceCents: number | null | undefined): number {
  const hundredths = Math.round((Number(qty) || 0) * 100)
  const price = Math.round(Number(unitPriceCents) || 0)
  return Math.round((hundredths * price) / 100)
}

export function totalsOf(items: Pick<QuoteItem, 'qty' | 'unitPriceCents' | 'kind'>[]): Totals {
  const totals: Totals = { total: 0, parts: 0, labour: 0, other: 0 }
  for (const item of items) {
    const line = lineTotal(item.qty, item.unitPriceCents)
    totals.total += line
    if (item.kind === 'PARTS') totals.parts += line
    else if (item.kind === 'LABOUR') totals.labour += line
    else totals.other += line
  }
  return totals
}

/** Accepted counts as won unless the job fell through later. */
export function isWon(q: Pick<QuoteSummary, 'status' | 'outcome'>): boolean {
  return q.outcome === 'WON' || (q.status === 'ACCEPTED' && q.outcome !== 'LOST')
}

export const STATUS_TONE: Record<QuoteStatus, Tone> = {
  DRAFT: 'neutral',
  SENT: 'primary',
  VIEWED: 'info',
  ACCEPTED: 'success',
  DECLINED: 'danger',
  SILENT: 'neutral',
  EXPIRED: 'neutral',
}

/** Which board column a status lives in: expired quotes sit with the silent ones. */
export function columnOf(status: QuoteStatus): BoardColumn {
  return status === 'EXPIRED' ? 'SILENT' : status
}

/** Days until (positive) or since (negative) a plain date, counted in whole calendar days. */
export function daysUntil(isoDate: string, today: Date = new Date()): number {
  const [y, m, d] = isoDate.split('-').map(Number)
  const target = Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1)
  const now = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate())
  return Math.round((target - now) / 86_400_000)
}

/** Plain date, validity days after a day: what "valid until" will read once sent today. */
export function addDays(isoDate: string, days: number): string {
  const [y, m, d] = isoDate.split('-').map(Number)
  const date = new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, (d ?? 1) + days))
  return date.toISOString().slice(0, 10)
}

/** An empty line of the given kind, priced at zero until someone types a price. */
export function blankItem(kind: ItemKind, description = ''): QuoteItem {
  return { description, qty: 1, unit: null, unitPriceCents: 0, kind }
}
