/**
 * Albanian dates, numbers and money, written out by hand. Chrome and Android ship without
 * Albanian locale data, so Intl quietly falls back to English there; the people this app is
 * for mostly use exactly those browsers. English keeps using Intl ("en-GB" is always there).
 */

const MONTHS = ['janar', 'shkurt', 'mars', 'prill', 'maj', 'qershor', 'korrik', 'gusht', 'shtator', 'tetor', 'nëntor', 'dhjetor']
const MONTHS_SHORT = ['jan', 'shk', 'mar', 'pri', 'maj', 'qer', 'korr', 'gush', 'sht', 'tet', 'nën', 'dhj']
const WEEKDAYS = ['e diel', 'e hënë', 'e martë', 'e mërkurë', 'e enjte', 'e premte', 'e shtunë']
const WEEKDAYS_SHORT = ['Die', 'Hën', 'Mar', 'Mër', 'Enj', 'Pre', 'Sht']
const CURRENCY_SYMBOLS: Record<string, string> = { EUR: '€', ALL: 'Lekë', USD: 'USD', GBP: '£', CHF: 'CHF' }

export type DateParts = { year: number; month: number; day: number; weekday: number; hour: string; minute: string }

/** Calendar parts of an instant in a time zone, read through Intl in English (always available). */
export function dateParts(date: Date, timeZone?: string): DateParts {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(date)
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? ''
  const weekday = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(get('weekday'))
  return { year: Number(get('year')), month: Number(get('month')) - 1, day: Number(get('day')), weekday, hour: get('hour'), minute: get('minute') }
}

export function date(p: DateParts, style: 'short' | 'medium' | 'long'): string {
  if (style === 'short') return `${p.day} ${MONTHS_SHORT[p.month]}`
  if (style === 'long') return `${WEEKDAYS[p.weekday]}, ${p.day} ${MONTHS[p.month]} ${p.year}`
  return `${p.day} ${MONTHS_SHORT[p.month]} ${p.year}`
}

export function dateTime(p: DateParts): string {
  return `${p.day} ${MONTHS_SHORT[p.month]}, ${p.hour}:${p.minute}`
}

export function weekday(p: DateParts, style: 'short' | 'long'): string {
  return (style === 'short' ? WEEKDAYS_SHORT : WEEKDAYS)[p.weekday] ?? ''
}

export function month(p: DateParts): string {
  return `${MONTHS[p.month]} ${p.year}`
}

/** 1250.5 -> "1250,50"; groups of three with a space from 10 000 up, as Albanian writes them. */
export function number(value: number, minFraction: number, maxFraction: number): string {
  const negative = value < 0
  const fixed = Math.abs(value).toFixed(maxFraction)
  let [whole = '0', fraction = ''] = fixed.split('.')
  fraction = fraction.replace(/0+$/, '')
  while (fraction.length < minFraction) fraction += '0'
  if (whole.length > 4) whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
  return (negative ? '-' : '') + whole + (fraction ? ',' + fraction : '')
}

export function money(units: number, currency: string, decimals: boolean): string {
  return `${number(units, decimals ? 2 : 0, decimals ? 2 : 0)} ${CURRENCY_SYMBOLS[currency] ?? currency}`
}

export function currencySymbol(currency: string): string {
  return CURRENCY_SYMBOLS[currency] ?? currency
}

type Unit = 'minute' | 'hour' | 'day' | 'week' | 'month' | 'year'

/** Singular and plural ablative after "para"/"pas": "para 1 dite", "para 3 ditësh". */
const UNIT_FORMS: Record<Unit, [string, string]> = {
  minute: ['minute', 'minutash'],
  hour: ['ore', 'orësh'],
  day: ['dite', 'ditësh'],
  week: ['jave', 'javësh'],
  month: ['muaji', 'muajsh'],
  year: ['viti', 'vjetësh'],
}

/** One unit back or ahead has its own word: "dje", "javën e kaluar". */
const NEIGHBOURS: Partial<Record<Unit, [string, string]>> = {
  day: ['dje', 'nesër'],
  week: ['javën e kaluar', 'javën e ardhshme'],
  month: ['muajin e kaluar', 'muajin e ardhshëm'],
  year: ['vitin e kaluar', 'vitin e ardhshëm'],
}

/** "para 3 ditësh", "pas 2 orësh", "dje", "tani". `n` is negative for the past. */
export function relative(n: number, unit: Unit | 'second'): string {
  if (unit === 'second' || n === 0) return 'tani'
  const neighbour = NEIGHBOURS[unit]
  if (neighbour && Math.abs(n) === 1) return n < 0 ? neighbour[0] : neighbour[1]
  const [one, many] = UNIT_FORMS[unit]
  const count = Math.abs(n)
  return `${n < 0 ? 'para' : 'pas'} ${count} ${count === 1 ? one : many}`
}
