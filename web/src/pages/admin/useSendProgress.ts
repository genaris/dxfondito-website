import { useRef, useState } from 'react'
import type { SendResult } from '../../mail.ts'

type ResultLine = SendResult['results'][number] & { key: string }

export interface SendProgressState {
  running: boolean
  total: number
  sent: number
  failed: number
  skipped: number
  waitingUntil: string | null
  /** The attempt to send again after a broken connection, or null. */
  reconnecting: number | null
  log: ResultLine[]
  stop: () => void
  run: (
    total: number,
    work: (callbacks: {
      onResult: (result: SendResult['results'][number]) => void
      onWait: (retryAt: string) => void
      onReconnect: (attempt: number | null) => void
      stopped: () => boolean
    }) => Promise<void>,
  ) => Promise<void>
}

/**
 * The progress of a sending in batches (FR-MAIL-9): the counts, the wait at the limit of the hour, and the problems.
 */
export function useSendProgress(): SendProgressState {
  const [running, setRunning] = useState(false)
  const [total, setTotal] = useState(0)
  const [sent, setSent] = useState(0)
  const [failed, setFailed] = useState(0)
  const [skipped, setSkipped] = useState(0)
  const [waitingUntil, setWaitingUntil] = useState<string | null>(null)
  const [reconnecting, setReconnecting] = useState<number | null>(null)
  const [log, setLog] = useState<ResultLine[]>([])
  const stopped = useRef(false)

  async function run(count: number, work: Parameters<SendProgressState['run']>[1]) {
    stopped.current = false
    setRunning(true)
    setTotal(count)
    setSent(0)
    setFailed(0)
    setSkipped(0)
    setWaitingUntil(null)
    setReconnecting(null)
    setLog([])
    const awake = new KeepAwake()
    try {
      await work({
        onResult: (result) => {
          setWaitingUntil(null)
          if (result.status === 'sent') setSent((value) => value + 1)
          else {
            if (result.status === 'failed') setFailed((value) => value + 1)
            else setSkipped((value) => value + 1)
            setLog((lines) => [...lines, { ...result, key: `${lines.length}` }])
          }
        },
        onWait: setWaitingUntil,
        onReconnect: setReconnecting,
        stopped: () => stopped.current,
      })
    } catch {
      setLog((lines) => [...lines, { status: 'failed', error: 'error de conexión con el sitio', key: `${lines.length}` }])
    } finally {
      awake.release()
      setRunning(false)
      setWaitingUntil(null)
      setReconnecting(null)
    }
  }

  return {
    running,
    total,
    sent,
    failed,
    skipped,
    waitingUntil,
    reconnecting,
    log,
    stop: () => (stopped.current = true),
    run,
  }
}

/**
 * Keeps the screen on during a long sending: a sleeping computer breaks the requests. The browser drops the lock
 * when the page is hidden, so the lock comes again when the page is visible. Without support, nothing happens.
 */
class KeepAwake {
  private lock: WakeLockSentinel | null = null
  private released = false
  private readonly onVisible = () => {
    if (document.visibilityState === 'visible') void this.request()
  }

  constructor() {
    document.addEventListener('visibilitychange', this.onVisible)
    void this.request()
  }

  release(): void {
    this.released = true
    document.removeEventListener('visibilitychange', this.onVisible)
    void this.lock?.release().catch(() => undefined)
    this.lock = null
  }

  private async request(): Promise<void> {
    if (!('wakeLock' in navigator) || (this.lock !== null && !this.lock.released)) return
    try {
      const lock = await navigator.wakeLock.request('screen')
      if (this.released) void lock.release()
      else this.lock = lock
    } catch {
      // The browser can refuse the lock, for example with low battery.
    }
  }
}
