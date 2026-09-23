/**
 * Locale-aware formatting. English goes through Intl ("en-GB"); Albanian is written out in
 * ./albanian because Chrome and Android ship without Albanian locale data.
 */
import * as sq from './albanian'

let locale = 'en'
let currency = 'EUR'
let timeZone: string | undefined

export function setFormatLocale(next: string) {
  locale = next
}

export function setFormatCurrency(next: string) {
  currency = next
}

export function setFormatTimeZone(next: string | undefined) {
  timeZone = next
}

function isSq(forLocale: string = locale) {
  return forLocale === 'sq'
}

function toDate(value: string | number | Date): Date {
  if (value instanceof Date) return value
  // Plain dates ("2026-09-23") are calendar days, not instants: read them at local noon.
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) return new Date(value + 'T12:00:00')
  return new Date(value)
}

/** `inLocale` renders a date for someone reading another language (the customer's preview). */
export function formatDate(value: string | number | Date | null | undefined, style: 'short' | 'medium' | 'long' = 'medium', inLocale?: string) {
  if (value === null || value === undefined || value === '') return '—'
  const d = toDate(value)
  const zone = isPlainDate(value) ? undefined : timeZone
  if (isSq(inLocale)) return sq.date(sq.dateParts(d, zone), style)
  const options: Intl.DateTimeFormatOptions =
    style === 'short'
      ? { day: 'numeric', month: 'short' }
      : style === 'long'
        ? { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }
        : { day: 'numeric', month: 'short', year: 'numeric' }
  return new Intl.DateTimeFormat('en-GB', { ...options, timeZone: zone }).format(d)
}

/** Units for formatAge, in minutes: used while the age is below `upTo`. */
const AGE_UNITS = [
  { upTo: 60, size: 1, en: 'm', sq: ' min' },
  { upTo: 60 * 24, size: 60, en: 'h', sq: ' orë' },
  { upTo: 60 * 24 * 7, size: 60 * 24, en: 'd', sq: ' ditë' },
  { upTo: 60 * 24 * 56, size: 60 * 24 * 7, en: 'w', sq: ' javë' },
  { upTo: Infinity, size: 60 * 24 * 30, en: 'mo', sq: ' muaj' },
]

/** How long ago, as short as a card allows: "3h", "2d" in English, "3 orë", "2 ditë" in Albanian. */
export function formatAge(value: string | number | Date | null | undefined, now: Date = new Date()) {
  if (value === null || value === undefined || value === '') return '—'
  const minutes = Math.max(0, Math.round((now.getTime() - toDate(value).getTime()) / 60000))
  if (minutes < 1) return isSq() ? 'tani' : 'now'
  const unit = AGE_UNITS.find((u) => minutes < u.upTo) ?? AGE_UNITS[AGE_UNITS.length - 1]!
  return `${Math.round(minutes / unit.size)}${isSq() ? unit.sq : unit.en}`
}

export function formatDateTime(value: string | number | Date | null | undefined) {
  if (value === null || value === undefined || value === '') return '—'
  if (isSq()) return sq.dateTime(sq.dateParts(toDate(value), timeZone))
  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    timeZone,
  }).format(toDate(value))
}

export function formatTime(value: string | number | Date | null | undefined) {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'string' && /^\d{2}:\d{2}/.test(value)) return value.slice(0, 5)
  return new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone }).format(toDate(value))
}

export function formatWeekday(value: string | Date, style: 'short' | 'long' = 'long') {
  if (isSq()) return sq.weekday(sq.dateParts(toDate(value)), style)
  return new Intl.DateTimeFormat('en-GB', { weekday: style }).format(toDate(value))
}

export function formatMonth(value: string | Date) {
  if (isSq()) return sq.month(sq.dateParts(toDate(value)))
  return new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' }).format(toDate(value))
}

/** Units for formatRelative, in seconds: used while the distance is below `below`. */
const RELATIVE_UNITS = [
  { below: 60, size: 1, unit: 'second' },
  { below: 3600, size: 60, unit: 'minute' },
  { below: 86400, size: 3600, unit: 'hour' },
  { below: 86400 * 7, size: 86400, unit: 'day' },
  { below: 86400 * 45, size: 86400 * 7, unit: 'week' },
  { below: 86400 * 365, size: 86400 * 30, unit: 'month' },
  { below: Infinity, size: 86400 * 365, unit: 'year' },
] as const

/** "3 days ago", "in 2 weeks" / "para 3 ditësh", "pas 2 javësh" */
export function formatRelative(value: string | number | Date | null | undefined, now: Date = new Date()) {
  if (value === null || value === undefined || value === '') return '—'
  const diffSeconds = Math.round((toDate(value).getTime() - now.getTime()) / 1000)
  const step = RELATIVE_UNITS.find((u) => Math.abs(diffSeconds) < u.below) ?? RELATIVE_UNITS[RELATIVE_UNITS.length - 1]!
  const n = Math.round(diffSeconds / step.size)
  if (isSq()) return sq.relative(n, step.unit)
  return new Intl.RelativeTimeFormat('en-GB', { numeric: 'auto' }).format(n, step.unit)
}

/** Money is always carried as integer cents. `locale` formats it for another reader. */
export function formatMoney(cents: number | null | undefined, options: { currency?: string; decimals?: boolean; locale?: string } = {}) {
  if (cents === null || cents === undefined) return '—'
  const decimals = options.decimals ?? cents % 100 !== 0
  const code = options.currency ?? currency
  if (isSq(options.locale ?? locale)) return sq.money(cents / 100, code, decimals)
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: code,
    minimumFractionDigits: decimals ? 2 : 0,
    maximumFractionDigits: decimals ? 2 : 0,
  }).format(cents / 100)
}

/** "€" for EUR, "Lekë" for ALL in Albanian: the symbol shown next to a price field. */
export function currencySymbol(code: string = currency) {
  if (isSq()) return sq.currencySymbol(code)
  const part = new Intl.NumberFormat('en-GB', { style: 'currency', currency: code }).formatToParts(0).find((p) => p.type === 'currency')
  return part?.value ?? code
}

/** The decimal mark people expect to type: "1,5" in Albanian, "1.5" in English. */
export function decimalSeparator() {
  return isSq() ? ',' : '.'
}

/** Parses what people type as a decimal: "1,5", "1.5", " 2 ". */
export function parseDecimal(input: string | number | null | undefined): number | null {
  if (input === null || input === undefined || input === '') return null
  if (typeof input === 'number') return Number.isFinite(input) ? input : null
  const n = Number(input.replace(/\s/g, '').replace(',', '.'))
  return Number.isFinite(n) ? n : null
}

export function formatNumber(value: number | null | undefined, maximumFractionDigits = 1, inLocale?: string) {
  if (value === null || value === undefined || Number.isNaN(value)) return '—'
  if (isSq(inLocale ?? locale)) return sq.number(value, 0, maximumFractionDigits)
  return new Intl.NumberFormat('en-GB', { maximumFractionDigits }).format(value)
}

export function formatPercent(value: number | null | undefined, maximumFractionDigits = 0) {
  if (value === null || value === undefined || Number.isNaN(value)) return '—'
  if (isSq()) return `${sq.number(value * 100, 0, maximumFractionDigits)}%`
  return new Intl.NumberFormat('en-GB', { style: 'percent', maximumFractionDigits }).format(value)
}

/** "355691234567" -> "+355 69 123 4567" (Albanian mobile grouping, generic otherwise) */
export function formatPhone(value: string | null | undefined) {
  if (!value) return '—'
  const digits = value.replace(/\D/g, '')
  if (digits.startsWith('355') && digits.length === 12) {
    return `+355 ${digits.slice(3, 5)} ${digits.slice(5, 8)} ${digits.slice(8)}`
  }
  if (digits.startsWith('383') && digits.length === 11) {
    return `+383 ${digits.slice(3, 5)} ${digits.slice(5, 8)} ${digits.slice(8)}`
  }
  return '+' + digits.replace(/(\d{3})(?=\d)/g, '$1 ').trim()
}

/** Parse "12,50" or "12.50" typed by a person into cents. */
export function parseMoney(input: string | number | null | undefined): number | null {
  if (input === null || input === undefined || input === '') return null
  if (typeof input === 'number') return Math.round(input * 100)
  const normalized = input.replace(/\s/g, '').replace(',', '.')
  const n = Number(normalized)
  return Number.isFinite(n) ? Math.round(n * 100) : null
}

export function centsToInput(cents: number | null | undefined): string {
  if (cents === null || cents === undefined) return ''
  return (cents / 100).toFixed(2).replace(/\.00$/, '')
}

export function todayIso(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function initials(name: string | null | undefined) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '')).toUpperCase()
}

function isPlainDate(value: unknown) {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)
}
