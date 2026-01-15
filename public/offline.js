async function downloadAllForOffline(onProgress) {
    // xin OS đừng dọn cache (không đảm bảo 100% nhưng tốt hơn)
    if (navigator.storage?.persist) {
        try { await navigator.storage.persist(); } catch (_) { }
    }

    const res = await fetch("/api/tracks.php", { cache: "no-store" });
    const data = await res.json();
    const tracks = data.tracks || [];

    const audioCache = await caches.open("audio-v1");

    for (let i = 0; i < tracks.length; i++) {
        const url = tracks[i].url;

        // nếu đã cache thì bỏ qua
        const existed = await audioCache.match(url);
        if (!existed) {
            const r = await fetch(url, { cache: "no-store" });
            if (!r.ok) throw new Error(`Fetch failed: ${url}`);
            await audioCache.put(url, r.clone());
        }

        if (onProgress) onProgress(i + 1, tracks.length, tracks[i]);
    }

    return { downloaded: tracks.length };
}
