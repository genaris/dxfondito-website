import { describe, expect, it } from 'vitest'
import type { ContactInput, LiveContact } from './live.ts'
import {
  applyResults,
  deleteLocal,
  EMPTY_QUEUE,
  keepMine,
  markSent,
  mergeChanges,
  nextChanges,
  pendingCount,
  replaceServer,
  rows,
  saveLocal,
  takeTheirs,
} from './liveQueue.ts'

const ID = 'aaaaaaaa-0000-4000-8000-000000000001'
const OTHER = 'aaaaaaaa-0000-4000-8000-000000000002'

const input: ContactInput = {
  operatorId: 4,
  callSign: 'lu1abc',
  name: 'Juana Pérez',
  qsoAt: '2026-10-04T14:29:00Z',
  frequency: '7.130',
  mode: 'SSB',
  rstSent: '59',
  rstRcvd: '59',
  notes: '',
}

function server(version: number, values: Partial<LiveContact> = {}): LiveContact {
  return {
    clientId: ID,
    version,
    operatorId: 4,
    station: 'LU2AOG/A',
    callSign: 'LU1ABC',
    name: 'Juana Pérez',
    qsoAt: '2026-10-04T14:29:00Z',
    frequency: '7.130',
    band: '40m',
    mode: 'SSB',
    rstSent: '59',
    rstRcvd: '59',
    notes: null,
    enteredBy: 'LU2AOG',
    updatedAt: '2026-10-04T14:29:10Z',
    ...values,
  }
}

const operators = [{ id: 4, callSign: 'LU2AOG', name: 'Ariel', station: 'LU2AOG/A' }]

describe('the queue', () => {
  it('sends a new contact and forgets it after the answer', () => {
    const data = saveLocal(EMPTY_QUEUE, ID, input)
    const { changes, seqs } = nextChanges(data)

    expect(changes).toEqual([{ clientId: ID, op: 'save', baseVersion: null, contact: input }])

    const done = applyResults(markSent(data, seqs), seqs, [{ clientId: ID, status: 'ok', contact: server(1) }])
    expect(pendingCount(done)).toBe(0)
    expect(done.server[ID].version).toBe(1)
  })

  it('keeps a correction of the user during the request, on the new version', () => {
    let data = saveLocal(EMPTY_QUEUE, ID, input)
    const { seqs } = nextChanges(data)
    data = markSent(data, seqs)
    data = saveLocal(data, ID, { ...input, rstRcvd: '57' })

    data = applyResults(data, seqs, [{ clientId: ID, status: 'ok', contact: server(1) }])

    expect(nextChanges(data).changes).toEqual([
      { clientId: ID, op: 'save', baseVersion: 1, contact: { ...input, rstRcvd: '57' } },
    ])
  })

  it('corrects a contact of the API on its version', () => {
    const data = saveLocal(replaceServer(EMPTY_QUEUE, [server(3)], '2026-10-04T14:20:00Z'), ID, { ...input, rstRcvd: '55' })

    expect(nextChanges(data).changes[0].baseVersion).toBe(3)
  })

  it('forgets a new contact that the user deletes before the request', () => {
    const data = deleteLocal(saveLocal(EMPTY_QUEUE, ID, input), ID)

    expect(pendingCount(data)).toBe(0)
    expect(rows(data, operators)).toEqual([])
  })

  it('deletes a contact that possibly went to the API', () => {
    let data = saveLocal(EMPTY_QUEUE, ID, input)
    data = markSent(data, nextChanges(data).seqs)
    data = deleteLocal(data, ID)

    expect(nextChanges(data).changes).toEqual([{ clientId: ID, op: 'delete', baseVersion: null }])
    expect(rows(data, operators)).toEqual([])
  })

  it('keeps an error until a correction, and sends the others', () => {
    let data = saveLocal(saveLocal(EMPTY_QUEUE, ID, { ...input, frequency: '' }), OTHER, input)
    const { seqs } = nextChanges(data)
    data = applyResults(data, seqs, [
      { clientId: ID, status: 'error', error: 'The contact is not valid', problems: [{ field: 'FREQ', problem: 'missing' }] },
      { clientId: OTHER, status: 'ok', contact: server(1, { clientId: OTHER }) },
    ])

    expect(nextChanges(data).changes).toEqual([])
    expect(rows(data, operators).find((row) => row.clientId === ID)?.state).toBe('error')

    data = saveLocal(data, ID, input)
    expect(nextChanges(data).changes.map((change) => change.clientId)).toEqual([ID])
  })

  it('waits for the user after a conflict', () => {
    let data = saveLocal(replaceServer(EMPTY_QUEUE, [server(1)], 'c'), ID, { ...input, rstRcvd: '58' })
    const { seqs } = nextChanges(data)
    data = applyResults(data, seqs, [{ clientId: ID, status: 'conflict', server: server(2, { rstRcvd: '57' }) }])

    expect(nextChanges(data).changes).toEqual([])
    expect(rows(data, operators)[0]).toMatchObject({ state: 'conflict', rstRcvd: '58' })

    const mine = keepMine(data, ID)
    expect(nextChanges(mine).changes).toEqual([{ clientId: ID, op: 'save', baseVersion: 2, contact: { ...input, rstRcvd: '58' } }])

    const theirs = takeTheirs(data, ID)
    expect(pendingCount(theirs)).toBe(0)
    expect(rows(theirs, operators)[0]).toMatchObject({ state: 'sent', rstRcvd: '57' })
  })

  it('makes a contact again after the user keeps it, when a different browser deleted it', () => {
    let data = saveLocal(replaceServer(EMPTY_QUEUE, [server(1)], 'c'), ID, input)
    data = applyResults(data, nextChanges(data).seqs, [{ clientId: ID, status: 'conflict', server: null }])

    expect(nextChanges(keepMine(data, ID)).changes[0].baseVersion).toBeNull()
  })

  it('merges the changes of the other browsers and ignores an older version', () => {
    let data = replaceServer(EMPTY_QUEUE, [server(2)], 'a')
    data = mergeChanges(data, { contacts: [server(1, { rstRcvd: '51' }), server(1, { clientId: OTHER })], deleted: [], cursor: 'b' })

    expect(data.server[ID].version).toBe(2)
    expect(data.server[OTHER]).toBeDefined()
    expect(data.cursor).toBe('b')

    data = mergeChanges(data, { contacts: [], deleted: [OTHER], cursor: 'c' })
    expect(data.server[OTHER]).toBeUndefined()
  })

  it('gives the rows the newest first, with the station of the operator', () => {
    const data = saveLocal(replaceServer(EMPTY_QUEUE, [server(1)], 'a'), OTHER, { ...input, callSign: 'cx2ab', qsoAt: '2026-10-04T14:35:00Z', frequency: '145.750', mode: 'FM' })
    const list = rows(data, operators)

    expect(list.map((row) => row.callSign)).toEqual(['CX2AB', 'LU1ABC'])
    expect(list[0]).toMatchObject({ station: 'LU2AOG/A', band: '2m', state: 'pending' })
    expect(list[1].state).toBe('sent')
  })
})
