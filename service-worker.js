// تغيير اسم الكاش إلى v2 لإجبار الهاتف على تحميل الملفات الجديدة
const CACHE_NAME = 'wealth-excel-pwa-v2';

const urlsToCache = [
  '/',
  '/login.php', // مهم جداً: يجب أن يتطابق مع start_url في الـ Manifest
  '/manifest.json',
  '/assets/icon-192x192.png?v=2.0',
  '/assets/icon-512x512.png?v=2.0'
];

// حدث التثبيت
self.addEventListener('install', event => {
  self.skipWaiting(); // يجبر الـ Service Worker الجديد على العمل فوراً
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(urlsToCache);
    })
  );
});

// حدث التفعيل (لتنظيف الكاش القديم وحذف الأيقونات القديمة)
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// حدث جلب البيانات
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});