import { describe, expect, it } from 'vitest'
import { BATCH, insertAt, sendInBatches, unknownVariables } from './mail.ts'
import type { SendResult } from './mail.ts'

describe('unknownVariables', () => {
  it('finds the variables that the kind of message does not have', () => {
    const variables = { saludo: '', indicativo: '' }
    expect(unknownVariables('Hola {saludo}, {nombr} {nombr} {indicativo} {}', variables)).toEqual(['nombr', ''])
  })
})

describe('insertAt', () => {
  it('puts the variable at the cursor and moves the cursor after it', () => {
    expect(insertAt('Hola !', 5, 5, '{saludo}')).toEqual({ text: 'Hola {saludo}!', cursor: 13 })
    expect(insertAt('Hola XX!', 5, 7, '{saludo}')).toEqual({ text: 'Hola {saludo}!', cursor: 13 })
  })
})

describe('sendInBatches', () => {
  const sent = (callSign: string) => ({ callSign, status: 'sent' as const })

  it('sends all items in batches', async () => {
    const batches: string[][] = []
    const items = Array.from({ length: BATCH * 2 + 1 }, (_, index) => `LU${index}`)
    await sendInBatches(
      items,
      async (batch) => {
        batches.push(batch)
        return { results: batch.map(sent), retryAt: null }
      },
      { onResult: () => undefined, onWait: () => undefined, stopped: () => false },
    )
    expect(batches.map((batch) => batch.length)).toEqual([BATCH, BATCH, 1])
  })

  it('waits at the limit of the hour and sends the rest after it', async () => {
    let now = Date.parse('2026-10-05T12:00:00Z')
    const answers: SendResult[] = [
      { results: [sent('LU1')], retryAt: '2026-10-05T12:30:00Z' },
      { results: [sent('LU2'), sent('LU3')], retryAt: null },
    ]
    const batches: string[][] = []
    const waits: string[] = []
    await sendInBatches(
      ['LU1', 'LU2', 'LU3'],
      async (batch) => {
        batches.push(batch)
        return answers.shift()!
      },
      {
        onResult: () => undefined,
        onWait: (retryAt) => waits.push(retryAt),
        stopped: () => false,
        wait: async (milliseconds) => {
          now += milliseconds
        },
        now: () => now,
      },
    )
    expect(waits).toEqual(['2026-10-05T12:30:00Z'])
    expect(batches).toEqual([['LU1', 'LU2', 'LU3'], ['LU2', 'LU3']])
  })

  it('stops after the current batch', async () => {
    let calls = 0
    await sendInBatches(
      Array.from({ length: BATCH * 3 }, (_, index) => `LU${index}`),
      async (batch) => {
        calls++
        return { results: batch.map(sent), retryAt: null }
      },
      { onResult: () => undefined, onWait: () => undefined, stopped: () => calls >= 1 },
    )
    expect(calls).toBe(1)
  })

  it('stops if an answer cannot advance', async () => {
    let calls = 0
    await sendInBatches(
      ['LU1'],
      async () => {
        calls++
        return { results: [], retryAt: null }
      },
      { onResult: () => undefined, onWait: () => undefined, stopped: () => false },
    )
    expect(calls).toBe(1)
  })
})
