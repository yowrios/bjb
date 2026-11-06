/* BRYAN JFIT — trabajador en segundo plano: permite abrir el cronómetro sin internet
   y mostrar avisos del cronómetro. Las páginas siempre se piden primero a internet. */
var VERSION = 'bjfit-v2';
var BASE = self.location.pathname.replace(/\/sw\.js$/, '');
var PRECACHE = [BASE + '/cronometro.php', BASE + '/assets/css/site.css', BASE + '/assets/css/crono.css', BASE + '/assets/js/site.js',
  BASE + '/assets/js/cronometro.js', BASE + '/assets/img/logo.webp', BASE + '/assets/img/icono-192.png'];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(VERSION).then(function (c) { return Promise.all(PRECACHE.map(function (u) { return c.add(u).catch(function () {}); })); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (k) { return Promise.all(k.filter(function (x) { return x !== VERSION; }).map(function (x) { return caches.delete(x); })); }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
  var r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin) return;
  if (/\/(admin|cliente)\//.test(u.pathname) || /api\.php|media\.php|descargar\.php/.test(u.pathname)) return; // nunca guardar datos privados
  var esCrono = u.pathname === BASE + '/cronometro.php';
  if (r.mode === 'navigate') {
    // Solo el cronómetro pasa por aquí (internet primero; sin conexión, la copia guardada).
    // Las demás páginas van directo al servidor, sin intermediarios.
    if (!esCrono) return;
    e.respondWith(fetch(r).then(function (res) {
      if (res.ok) { var cp = res.clone(); caches.open(VERSION).then(function (c) { c.put(BASE + '/cronometro.php', cp); }); }
      return res;
    }).catch(function () { return caches.match(BASE + '/cronometro.php'); }));
    return;
  }
  if (/\/assets\//.test(u.pathname)) {
    e.respondWith(caches.match(r, { ignoreSearch: true }).then(function (m) {
      var red = fetch(r).then(function (res) { if (res.ok) { var cp = res.clone(); caches.open(VERSION).then(function (c) { c.put(r, cp); }); } return res; });
      return m ? (red.catch(function () {}), m) : red;
    }));
  }
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (cs) {
    for (var i = 0; i < cs.length; i++) if (cs[i].url.indexOf('cronometro.php') >= 0) return cs[i].focus();
    return self.clients.openWindow(BASE + '/cronometro.php');
  }));
});
