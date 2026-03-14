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

const CACHE_VERSION = 'peopledisplay-v2.0.2'; // 🔧 FIXED: POST requests skip
const CACHE_NAME = CACHE_VERSION;

// Files to cache for offline use
const CACHE_URLS = [
    '/',
    '/index.php',
    '/login.php',
    '/style.css',
    '/app.js',
    '/manifest.json',
    // Icons
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-512x512.png',
    // Fallback pages
    '/offline.html'
];

// API endpoints to cache (for offline queue)
const API_CACHE = 'peopledisplay-api-v1';

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

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // 🔧 FIX: Skip POST requests entirely (can't be cached)
    if (request.method === 'POST') {
        return; // Let POST requests pass through without caching
    }
    
    // Skip cross-origin requests
    if (url.origin !== location.origin) {
        return;
    }
    
    // API requests - network first, cache fallback (GET only)
    if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/admin/api/')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Clone response for cache
                    const responseClone = response.clone();
                    
                    // Cache successful GET responses only
                    if (response.status === 200 && request.method === 'GET') {
                        caches.open(API_CACHE).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    
                    return response;
                })
                .catch(() => {
                    // Network failed, try cache (GET only)
                    if (request.method === 'GET') {
                        return caches.match(request)
                            .then((cachedResponse) => {
                                if (cachedResponse) {
                                    return cachedResponse;
                                }
                                
                                // No cache, return offline page
                                return caches.match('/offline.html');
                            });
                    }
                    
                    // POST failed and no cache possible
                    return new Response('Network error', { status: 503 });
                })
        );
        return;
    }
    
    // Static assets - cache first, network fallback (GET only)
    event.respondWith(
        caches.match(request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    // Return cached version
                    return cachedResponse;
                }
                
                // Not in cache, fetch from network
                return fetch(request)
                    .then((response) => {
                        // Don't cache non-successful responses
                        if (!response || response.status !== 200 || response.type === 'error') {
                            return response;
                        }
                        
                        // Clone response for cache (GET only)
                        if (request.method === 'GET') {
                            const responseClone = response.clone();
                            
                            // Cache for future use
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, responseClone);
                            });
                        }
                        
                        return response;
                    })
                    .catch(() => {
                        // Network failed, show offline page (GET only)
                        if (request.method === 'GET') {
                            return caches.match('/offline.html');
                        }
                        return new Response('Network error', { status: 503 });
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
