// The service worker of the site (FR-LIVE-10, system design, section 5.3). It keeps the browser program in the browser.
// Thus the live page opens again without a connection, and the user continues to enter contacts in its queue.
// It does not keep the requests of the API: the live page keeps its own data in localStorage.

const CACHE = 'dxf-program-v1'
// A slow connection, such as mobile data in a park, gives the kept page after this wait.
const PAGE_TIMEOUT_MS = 5000

const pageUrl = () => new URL('./', self.registration.scope).href

self.addEventListener('install', (event) => {
  event.waitUntil(
    keepProgram()
      .catch(() => undefined)
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

self.addEventListener('fetch', (event) => {
  const request = event.request
  if (request.method !== 'GET') return
  const url = new URL(request.url)
  // The API and the files of other sites go to the network, as without a service worker.
  if (url.origin !== self.location.origin || url.pathname.includes('/api/')) return
  if (request.mode === 'navigate') {
    event.respondWith(page(request, event))
  } else if (url.pathname.includes('/assets/') || url.pathname.endsWith('/favicon.png')) {
    event.respondWith(asset(request))
  }
})

/**
 * The page from the network, and else the kept page. Thus a new installation of the site comes with the next
 * opening of the site with a connection.
 */
async function page(request, event) {
  try {
    const response = await Promise.race([
      fetch(request),
      new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), PAGE_TIMEOUT_MS)),
    ])
    if (response.ok) event.waitUntil(keepPage(response.clone()))
    return response
  } catch {
    return (await caches.match(pageUrl())) ?? Response.error()
  }
}

/** The files of the program have the hash of their content in their names. Thus a kept file is always correct. */
async function asset(request) {
  const kept = await caches.match(request)
  if (kept) return kept
  const response = await fetch(request)
  if (response.ok) {
    const cache = await caches.open(CACHE)
    await cache.put(request, response.clone())
  }
  return response
}

async function keepProgram() {
  const response = await fetch(pageUrl(), { cache: 'no-store' })
  if (response.ok) await keepPage(response)
}

/** Keeps the page and the scripts and styles that it names, and removes the scripts and styles of older versions. */
async function keepPage(response) {
  const html = await response.clone().text()
  const cache = await caches.open(CACHE)
  await cache.put(pageUrl(), response)
  const files = [...html.matchAll(/(?:src|href)="\.?\/?(assets\/[^"]+\.(?:js|css))"/g)].map(
    (match) => new URL(match[1], self.registration.scope).href,
  )
  for (const file of files) {
    if (!(await cache.match(file))) await cache.add(file)
  }
  const current = new Set(files)
  for (const kept of await cache.keys()) {
    if (/\.(js|css)$/.test(new URL(kept.url).pathname) && !current.has(kept.url)) await cache.delete(kept)
  }
}
