import { bandOf } from './live.ts'
import type { Change, ChangeResult, ContactInput, LiveContact, LiveOperator } from './live.ts'
import type { Problem } from './logs.ts'

// The queue of the live page (FR-LIVE-10, system design, section 5.3). Each change goes first to the queue, and the
// queue goes to the API when there is a connection. The functions give a new value: the page keeps it in its state
// and in localStorage.

/** A change that the API does not have yet. One for each contact: a second change replaces the first. */
export interface Pending {
  /** Goes up with each change of the contact. A result is for the change with the same number. */
  seq: number
  op: 'save' | 'delete'
  /** The version of the API that the user changed. Null for a new contact. */
  baseVersion: number | null
  contact: ContactInput | null
  /** True after a request with this contact: the API possibly has it. */
  sent: boolean
  /** The API refused the values. The change goes again after a correction. */
  error: string | null
  problems: Problem[]
  /** A different browser changed or deleted the contact first. The user decides. A deletion has no conflicts. */
  conflict: { server: LiveContact | null } | null
}

export interface QueueData {
  /** The contacts as the API has them, by identifier. */
  server: Record<string, LiveContact>
  /** The changes in their order. */
  pending: Record<string, Pending>
  /** The `since` value of the next request, or null before the first answer. */
  cursor: string | null
  seq: number
}

export const EMPTY_QUEUE: QueueData = { server: {}, pending: {}, cursor: null, seq: 0 }

/** The most changes in one request. The API accepts 200. */
const MAX_CHANGES = 100

export function saveLocal(data: QueueData, clientId: string, contact: ContactInput): QueueData {
  const seq = data.seq + 1
  const old = data.pending[clientId]
  const pending: Pending = {
    seq,
    op: 'save',
    // A correction of a change that is still in the queue keeps the version of the first change.
    baseVersion: old ? old.baseVersion : (data.server[clientId]?.version ?? null),
    contact,
    sent: old?.sent ?? false,
    error: null,
    problems: [],
    conflict: old?.conflict ?? null,
  }
  return { ...data, seq, pending: withEntry(data.pending, clientId, pending) }
}

export function deleteLocal(data: QueueData, clientId: string): QueueData {
  const seq = data.seq + 1
  const old = data.pending[clientId]
  // A new contact that never went to the API: the queue forgets it.
  if (old && old.baseVersion === null && !old.sent && !data.server[clientId]) {
    return { ...data, seq, pending: without(data.pending, clientId) }
  }
  const pending: Pending = {
    seq,
    op: 'delete',
    baseVersion: old ? old.baseVersion : (data.server[clientId]?.version ?? null),
    contact: null,
    sent: old?.sent ?? false,
    error: null,
    problems: [],
    conflict: null,
  }
  return { ...data, seq, pending: withEntry(data.pending, clientId, pending) }
}

/**
 * The changes for the next request: without the changes with an error or a conflict, which wait for the user.
 */
export function nextChanges(data: QueueData): { changes: Change[]; seqs: Record<string, number> } {
  const changes: Change[] = []
  const seqs: Record<string, number> = {}
  for (const [clientId, pending] of Object.entries(data.pending)) {
    if (pending.error !== null || pending.conflict !== null) continue
    if (changes.length >= MAX_CHANGES) break
    changes.push(
      pending.op === 'save' && pending.contact
        ? { clientId, op: 'save', baseVersion: pending.baseVersion, contact: pending.contact }
        : { clientId, op: 'delete', baseVersion: pending.baseVersion },
    )
    seqs[clientId] = pending.seq
  }
  return { changes, seqs }
}

/** Marks the changes of a request: after the request, the API possibly has them, also without an answer. */
export function markSent(data: QueueData, seqs: Record<string, number>): QueueData {
  const pending = { ...data.pending }
  for (const clientId of Object.keys(seqs)) {
    if (pending[clientId]) pending[clientId] = { ...pending[clientId], sent: true }
  }
  return { ...data, pending }
}

/**
 * The answer of the API to the changes with the numbers `seqs`. A change of the user during the request stays in
 * the queue: it goes with the next request, on the new version of the API.
 */
export function applyResults(data: QueueData, seqs: Record<string, number>, results: ChangeResult[]): QueueData {
  const server = { ...data.server }
  let pending = { ...data.pending }
  for (const result of results) {
    const clientId = result.clientId
    if (clientId === null || !(clientId in seqs)) continue
    const current = pending[clientId]
    const same = current?.seq === seqs[clientId]
    if (result.status === 'ok') {
      if (result.contact) server[clientId] = result.contact
      else delete server[clientId]
      if (same) pending = without(pending, clientId)
      else if (current) pending[clientId] = { ...current, baseVersion: result.contact?.version ?? null }
    } else if (result.status === 'conflict') {
      if (result.server) server[clientId] = result.server
      else delete server[clientId]
      if (current) pending[clientId] = { ...current, conflict: { server: result.server ?? null } }
    } else if (current && same) {
      pending[clientId] = { ...current, error: result.error ?? 'error', problems: result.problems ?? [] }
    }
  }
  return { ...data, server, pending }
}

/**
 * The changes of all browsers since the cursor. The overlap of the cursor gives some changes again: a version that
 * the page has does not change it.
 */
export function mergeChanges(data: QueueData, changes: { contacts?: LiveContact[]; deleted?: string[]; cursor: string }): QueueData {
  const server = { ...data.server }
  for (const contact of changes.contacts ?? []) {
    const known = server[contact.clientId]
    if (!known || known.version < contact.version) server[contact.clientId] = contact
  }
  for (const clientId of changes.deleted ?? []) delete server[clientId]
  return { ...data, server, cursor: changes.cursor }
}

/** All contacts of the API, after the first request of the page. The queue stays. */
export function replaceServer(data: QueueData, contacts: LiveContact[], cursor: string): QueueData {
  return { ...data, server: Object.fromEntries(contacts.map((contact) => [contact.clientId, contact])), cursor }
}

/** The user keeps the own change after a conflict: it goes again on the version of the API. */
export function keepMine(data: QueueData, clientId: string): QueueData {
  const current = data.pending[clientId]
  if (!current?.conflict) return data
  const pending: Pending = { ...current, baseVersion: current.conflict.server?.version ?? null, conflict: null }
  return { ...data, pending: { ...data.pending, [clientId]: pending } }
}

/** The user takes the version of the different browser after a conflict. */
export function takeTheirs(data: QueueData, clientId: string): QueueData {
  return { ...data, pending: without(data.pending, clientId) }
}

export function pendingCount(data: QueueData): number {
  return Object.keys(data.pending).length
}

/** A line of the list of the page. */
export interface Row {
  clientId: string
  operatorId: number
  station: string
  callSign: string
  name: string | null
  qsoAt: string
  frequency: string
  band: string | null
  mode: string
  rstSent: string | null
  rstRcvd: string | null
  notes: string | null
  /** `sent`: the API has this version. */
  state: 'sent' | 'pending' | 'error' | 'conflict'
  pending: Pending | null
}

/**
 * The contacts of the page, the newest first: the contacts of the API with the changes of the queue.
 * A deleted contact of the queue is not in the list.
 */
export function rows(data: QueueData, operators: LiveOperator[]): Row[] {
  const stations = new Map(operators.map((operator) => [operator.id, operator.station]))
  const list: Row[] = []
  const ids = new Set([...Object.keys(data.server), ...Object.keys(data.pending)])
  for (const clientId of ids) {
    const pending = data.pending[clientId] ?? null
    const server = data.server[clientId]
    const state = pending === null ? 'sent' : pending.conflict ? 'conflict' : pending.error ? 'error' : 'pending'
    if (pending?.op === 'delete') continue
    if (pending?.contact) {
      const input = pending.contact
      list.push({
        clientId,
        operatorId: input.operatorId,
        station: stations.get(input.operatorId) ?? server?.station ?? '',
        callSign: input.callSign.toUpperCase(),
        name: input.name || null,
        qsoAt: input.qsoAt,
        frequency: input.frequency,
        band: bandOf(input.frequency).band,
        mode: input.mode,
        rstSent: input.rstSent || null,
        rstRcvd: input.rstRcvd || null,
        notes: input.notes || null,
        state,
        pending,
      })
    } else if (server) {
      list.push({ ...fromServer(server), state, pending })
    }
  }
  return list.sort((a, b) => b.qsoAt.localeCompare(a.qsoAt) || b.clientId.localeCompare(a.clientId))
}

/** The values of a contact of the API, for a correction. */
export function inputOf(row: Row): ContactInput {
  return {
    operatorId: row.operatorId,
    callSign: row.callSign,
    name: row.name ?? '',
    qsoAt: row.qsoAt,
    frequency: row.frequency,
    mode: row.mode,
    rstSent: row.rstSent ?? '',
    rstRcvd: row.rstRcvd ?? '',
    notes: row.notes ?? '',
  }
}

function fromServer(contact: LiveContact): Omit<Row, 'state' | 'pending'> {
  return {
    clientId: contact.clientId,
    operatorId: contact.operatorId,
    station: contact.station,
    callSign: contact.callSign,
    name: contact.name,
    qsoAt: contact.qsoAt,
    frequency: contact.frequency,
    band: contact.band,
    mode: contact.mode,
    rstSent: contact.rstSent,
    rstRcvd: contact.rstRcvd,
    notes: contact.notes,
  }
}

/** The entry at the end: the order of the queue is the order of the changes. */
function withEntry(pending: Record<string, Pending>, clientId: string, entry: Pending): Record<string, Pending> {
  return { ...without(pending, clientId), [clientId]: entry }
}

function without(pending: Record<string, Pending>, clientId: string): Record<string, Pending> {
  const rest = { ...pending }
  delete rest[clientId]
  return rest
}

/** The queue of an activity in this browser. It stays after a reload, a closed tab or a restart of the device. */
export function loadQueue(activityId: number): QueueData {
  return readStored<QueueData>(queueKey(activityId)) ?? EMPTY_QUEUE
}

export function storeQueue(activityId: number, data: QueueData): void {
  writeStored(queueKey(activityId), data)
}

const queueKey = (activityId: number): string => `dxf-live-queue-${activityId}`

/**
 * A value of localStorage, or null. The storage can be absent or full, for example in a private window.
 */
export function readStored<T>(key: string): T | null {
  try {
    const text = localStorage.getItem(key)
    return text === null ? null : (JSON.parse(text) as T)
  } catch {
    return null
  }
}

/** Keeps a value in localStorage. False if the browser refused it. */
export function writeStored(key: string, value: unknown): boolean {
  try {
    localStorage.setItem(key, JSON.stringify(value))
    return true
  } catch {
    return false
  }
}
