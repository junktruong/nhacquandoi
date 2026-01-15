const VERSION = "v3"; // tăng version
const STATIC_CACHE = `static-${VERSION}`;
const RUNTIME_CACHE = `runtime-${VERSION}`;
const AUDIO_CACHE = `audio-v2`;

const toUrl = (p) => new URL(p, self.registration.scope).toString();

self.addEventListener("install", (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        await cache.addAll([
            toUrl("./"),
            toUrl("offline.html"),
            toUrl("assets/style.css"),
            toUrl("assets/app.js"),
            toUrl("assets/logo.png"),
            toUrl("manifest.webmanifest")
        ]);
        self.skipWaiting();
    })());
});

self.addEventListener("activate", (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.map(k => {
            if (k !== STATIC_CACHE && k !== RUNTIME_CACHE && k !== AUDIO_CACHE) return caches.delete(k);
        }));
        self.clients.claim();
    })());
});

async function cacheFirst(req, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(req.url);
    if (cached) return cached;
    const res = await fetch(req);
    if (res && res.ok) cache.put(req.url, res.clone());
    return res;
}

async function rangeFromCacheOrNetwork(req) {
    const cache = await caches.open(AUDIO_CACHE);
    let res = await cache.match(req.url);

    if (!res) {
        const fullRes = await fetch(req.url);
        if (fullRes && fullRes.ok) {
            await cache.put(req.url, fullRes.clone());
            res = fullRes;
        } else return fullRes;
    }

    const range = req.headers.get('range');
    if (!range) return res;

    const buf = await res.arrayBuffer();
    const m = /bytes=(\d+)-(\d*)/.exec(range);
    if (!m) return res;

    const start = parseInt(m[1], 10);
    const end = m[2] ? parseInt(m[2], 10) : (buf.byteLength - 1);
    const chunk = buf.slice(start, end + 1);

    return new Response(chunk, {
        status: 206,
        headers: {
            'Content-Type': res.headers.get('Content-Type') || 'audio/mpeg',
            'Content-Range': `bytes ${start}-${end}/${buf.byteLength}`,
            'Accept-Ranges': 'bytes',
            'Content-Length': String(chunk.byteLength),
        }
    });
}

self.addEventListener("fetch", (event) => {
   const req = event.request;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Never cache Admin pages/APIs
  if (url.pathname.startsWith("/admin")) {
    event.respondWith(fetch(req));
    return;
  }

    if (req.mode === "navigate") {
        event.respondWith((async () => {
            try {
                const fresh = await fetch(req);
                const cache = await caches.open(RUNTIME_CACHE);
                cache.put(req, fresh.clone());
                return fresh;
            } catch {
                const cached = await caches.match(req);
                return cached || caches.match(toUrl("offline.html"));
            }
        })());
        return;
    }

    if (url.pathname.startsWith("/uploads/")) {
        event.respondWith(rangeFromCacheOrNetwork(req));
        return;
    }

    if (url.pathname.includes("/assets/") || url.pathname.endsWith("manifest.webmanifest")) {
        event.respondWith(cacheFirst(req, RUNTIME_CACHE));
        return;
    }
});
