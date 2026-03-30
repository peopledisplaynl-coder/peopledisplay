/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: service-worker.js
 * LOCATIE:      ROOT (/)
 * UPLOAD NAAR:  /service-worker.js
 * ═══════════════════════════════════════════════════════════════════
 * 
 * PeopleDisplay Service Worker v2.0
 * - Offline support
 * - Background sync
 * - Push notifications ready
 * - Kiosk mode optimized
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

const CACHE_VERSION = 'peopledisplay-v2.0.4'; // 🔧 FIXED: app.js/style.css network-first
const CACHE_NAME = CACHE_VERSION;

// Alleen ECHT statische assets cachen — iconen en manifest
const CACHE_URLS = [
    '/manifest.json',
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-512x512.png',
    '/offline.html'
];

// Bestanden die ALTIJD via netwerk worden opgehaald (nooit cachen)
const ALWAYS_NETWORK = [
    '.php',
    '/api/',
    '/admin/',
    'app.js',
    'style.css'
];

// PHP pagina's die NOOIT gecached mogen worden (authenticatie vereist)
const NEVER_CACHE = [
    '.php',
    '/api/',
    '/admin/',
    'app.js',
    'style.css'
];

// Install event - cache essential files
self.addEventListener('install', (event) => {
    console.log('[ServiceWorker] Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[ServiceWorker] Caching app shell');
                // Cache files one by one to avoid HEAD request issues
                return Promise.all(
                    CACHE_URLS.map(url => {
                        return cache.add(url).catch(err => {
                            console.warn('[ServiceWorker] Failed to cache:', url, err);
                        });
                    })
                );
            })
            .then(() => {
                console.log('[ServiceWorker] Skip waiting');
                return self.skipWaiting();
            })
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    console.log('[ServiceWorker] Activating...');
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME && cacheName !== API_CACHE) {
                            console.log('[ServiceWorker] Removing old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[ServiceWorker] Claiming clients');
                return self.clients.claim();
            })
    );
});

// Fetch event - Safari-safe implementatie
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip cross-origin requests
    if (url.origin !== location.origin) {
        return;
    }

    // Skip POST requests
    if (request.method !== 'GET') {
        return;
    }

    // NOOIT cachen: PHP pagina's, API calls, admin pagina's, app.js, style.css
    const neverCache = NEVER_CACHE.some(pattern => url.pathname.includes(pattern));
    if (neverCache || url.pathname === '/') {
        // Altijd netwerk gebruiken, nooit cache
        event.respondWith(
            fetch(request).catch(() => {
                return caches.match('/offline.html');
            })
        );
        return;
    }

    // Statische assets — cache first, network fallback
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((response) => {
                // Alleen succesvolle responses cachen
                if (!response || response.status !== 200 || response.type === 'error') {
                    return response;
                }

                // Nooit responses met redirects cachen (Safari fix)
                if (response.redirected) {
                    return response;
                }

                const responseClone = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, responseClone);
                });

                return response;
            }).catch(() => {
                return caches.match('/offline.html');
            });
        })
    );
});

// Background sync - queue check-ins when offline
self.addEventListener('sync', (event) => {
    console.log('[ServiceWorker] Background sync:', event.tag);
    
    if (event.tag === 'sync-checkins') {
        event.waitUntil(syncCheckIns());
    }
});

// Sync queued check-ins
async function syncCheckIns() {
    try {
        // Get queued check-ins from IndexedDB
        const db = await openDB();
        const queue = await getQueuedCheckIns(db);
        
        if (queue.length === 0) {
            console.log('[ServiceWorker] No check-ins to sync');
            return;
        }
        
        console.log('[ServiceWorker] Syncing', queue.length, 'check-ins');
        
        // Process each queued check-in
        for (const item of queue) {
            try {
                const response = await fetch('/api/update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(item.data)
                });
                
                if (response.ok) {
                    // Remove from queue
                    await removeFromQueue(db, item.id);
                    console.log('[ServiceWorker] Synced check-in:', item.id);
                }
            } catch (error) {
                console.error('[ServiceWorker] Failed to sync:', error);
            }
        }
        
        console.log('[ServiceWorker] Sync complete');
        
    } catch (error) {
        console.error('[ServiceWorker] Sync error:', error);
        throw error;
    }
}

// IndexedDB helpers
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('PeopleDisplayDB', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('checkInQueue')) {
                db.createObjectStore('checkInQueue', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

function getQueuedCheckIns(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['checkInQueue'], 'readonly');
        const store = transaction.objectStore('checkInQueue');
        const request = store.getAll();
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

function removeFromQueue(db, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['checkInQueue'], 'readwrite');
        const store = transaction.objectStore('checkInQueue');
        const request = store.delete(id);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}

// Push notification event (future use)
self.addEventListener('push', (event) => {
    console.log('[ServiceWorker] Push received');
    
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'PeopleDisplay';
    const options = {
        body: data.body || 'Nieuwe melding',
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-72.png',
        vibrate: [200, 100, 200],
        data: data.data || {}
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
    console.log('[ServiceWorker] Notification clicked');
    
    event.notification.close();
    
    // Open app
    event.waitUntil(
        clients.openWindow('/')
    );
});

// Message event - communicate with app
self.addEventListener('message', (event) => {
    console.log('[ServiceWorker] Message received:', event.data);
    
    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data.type === 'CACHE_URLS') {
        event.waitUntil(
            caches.open(CACHE_NAME).then((cache) => {
                return cache.addAll(event.data.urls);
            })
        );
    }
});

console.log('[ServiceWorker] Loaded:', CACHE_VERSION);
