import { describe, expect, it } from 'vitest'
import { appendTranscript, speechLang } from '../speech'
import { answerTime, share } from '../stats'

describe('appendTranscript', () => {
  it('starts with a capital letter', () => {
    expect(appendTranscript('', 'znj. hoxha rruga myslym shyri 24')).toBe('Znj. hoxha rruga myslym shyri 24')
  })

  it('joins phrases with a comma, which the quote reader uses as a pause', () => {
    expect(appendTranscript('Znj. Hoxha', 'ndërrim rubineti kuzhine')).toBe('Znj. Hoxha, ndërrim rubineti kuzhine')
  })

  it('keeps punctuation the person typed and tidies spaces', () => {
    expect(appendTranscript('85 pjesë,  ', '  60   punë ')).toBe('85 pjesë, 60 punë')
    expect(appendTranscript('Replace the tap.', 'Thursday morning')).toBe('Replace the tap. Thursday morning')
  })

  it('ignores empty phrases', () => {
    expect(appendTranscript('Arta Kola', '   ')).toBe('Arta Kola')
  })
})

describe('speechLang', () => {
  it('listens in Albanian or British English', () => {
    expect(speechLang('sq')).toBe('sq-AL')
    expect(speechLang('en')).toBe('en-GB')
  })
})

describe('answerTime', () => {
  it('picks the unit a person would say', () => {
    expect(answerTime(null)).toBeNull()
    expect(answerTime(0.4)).toEqual({ unit: 'minutes', n: 24 })
    expect(answerTime(5.2)).toEqual({ unit: 'hours', n: 5 })
    expect(answerTime(60)).toEqual({ unit: 'days', n: 2.5 })
  })

  it('shares are null when there is nothing to divide', () => {
    expect(share(3, 12)).toBe(0.25)
    expect(share(1, 0)).toBeNull()
  })
})
