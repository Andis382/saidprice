import { afterEach, describe, expect, it } from 'vitest'
import * as sq from '../albanian'
import { formatDate, formatMoney, formatPercent, formatRelative, setFormatLocale, setFormatTimeZone } from '../format'

describe('Albanian numbers and money', () => {
  it('uses a decimal comma and groups thousands from ten thousand up', () => {
    expect(sq.number(1250.5, 2, 2)).toBe('1250,50')
    expect(sq.number(12345, 0, 0)).toBe('12 345')
    expect(sq.number(1234567.891, 0, 2)).toBe('1 234 567,89')
    expect(sq.number(-3.5, 0, 1)).toBe('-3,5')
    expect(sq.number(2, 0, 2)).toBe('2')
  })

  it('puts the currency after the amount', () => {
    expect(sq.money(145, 'EUR', false)).toBe('145 €')
    expect(sq.money(16.5, 'EUR', true)).toBe('16,50 €')
    expect(sq.money(3500, 'ALL', false)).toBe('3500 Lekë')
  })
})

describe('Albanian dates', () => {
  // Wednesday 7 October 2026, 14:00 in Tirana
  const parts = sq.dateParts(new Date('2026-10-07T12:00:00Z'), 'Europe/Tirane')

  it('reads the calendar day in the business time zone', () => {
    expect(parts).toMatchObject({ year: 2026, month: 9, day: 7, weekday: 3, hour: '14', minute: '00' })
  })

  it('writes short, medium and long dates the Albanian way', () => {
    expect(sq.date(parts, 'short')).toBe('7 tet')
    expect(sq.date(parts, 'medium')).toBe('7 tet 2026')
    expect(sq.date(parts, 'long')).toBe('e mërkurë, 7 tetor 2026')
    expect(sq.dateTime(parts)).toBe('7 tet, 14:00')
    expect(sq.weekday(parts, 'short')).toBe('Mër')
    expect(sq.month(parts)).toBe('tetor 2026')
  })

  it('says relative times with the right case', () => {
    expect(sq.relative(0, 'second')).toBe('tani')
    expect(sq.relative(-1, 'day')).toBe('dje')
    expect(sq.relative(1, 'day')).toBe('nesër')
    expect(sq.relative(-3, 'day')).toBe('para 3 ditësh')
    expect(sq.relative(-1, 'hour')).toBe('para 1 ore')
    expect(sq.relative(5, 'hour')).toBe('pas 5 orësh')
    expect(sq.relative(-1, 'week')).toBe('javën e kaluar')
    expect(sq.relative(2, 'week')).toBe('pas 2 javësh')
  })
})

describe('format helpers in Albanian', () => {
  afterEach(() => {
    setFormatLocale('en')
    setFormatTimeZone(undefined)
  })

  it('never depends on the browser having Albanian locale data', () => {
    setFormatLocale('sq')
    setFormatTimeZone('Europe/Tirane')
    const now = new Date('2026-09-23T12:00:00Z')
    expect(formatMoney(28950)).toBe('289,50 €')
    expect(formatDate('2026-10-07')).toBe('7 tet 2026')
    expect(formatRelative('2026-09-20T12:00:00Z', now)).toBe('para 3 ditësh')
    expect(formatPercent(0.335)).toBe('34%')
  })
})
