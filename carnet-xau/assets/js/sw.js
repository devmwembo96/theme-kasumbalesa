/* Carnet XAU — service worker : l'interface est mise en cache, les données restent en ligne.
 * Servi par le plugin via /?cx_pwa=sw, qui remplace les deux valeurs ci-dessous. */
const VERSION = __CX_VERSION__;
const PRECACHE = __CX_PRECACHE__;
const CACHE = "carnet-xau-" + VERSION;

self.addEventListener("install", e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(PRECACHE)).catch(() => {}).then(() => self.skipWaiting()));
});

self.addEventListener("activate", e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k.startsWith("carnet-xau-") && k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", e => {
  const req = e.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  // Données : jamais de cache.
  if (url.pathname.includes("/wp-json/") || url.searchParams.has("rest_route") || url.pathname.endsWith("admin-ajax.php") || url.searchParams.has("cx_pwa")) return;

  // Page du carnet : réseau d'abord, copie en cache si le réseau manque.
  if (req.mode === "navigate") {
    e.respondWith(
      fetch(req).then(res => {
        if (res.ok) { const copy = res.clone(); caches.open(CACHE).then(c => c.put(req, copy)); }
        return res;
      }).catch(() => caches.match(req))
    );
    return;
  }

  // Styles, scripts, polices et images : cache, puis mise à jour en arrière-plan.
  const isAsset = ["style", "script", "font", "image"].includes(req.destination);
  if (!isAsset) return;
  e.respondWith(
    caches.match(req).then(hit => {
      const net = fetch(req).then(res => {
        if (res.ok || res.type === "opaque") { const copy = res.clone(); caches.open(CACHE).then(c => c.put(req, copy)); }
        return res;
      }).catch(() => hit);
      return hit || net;
    })
  );
});
