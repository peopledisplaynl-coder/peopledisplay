/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: pwa-installer.js
 * LOCATIE:      ROOT (/)
 * UPLOAD NAAR:  /pwa-installer.js
 * INCLUDE IN:   index.php, overzicht.php, frontpage.php
 * ═══════════════════════════════════════════════════════════════════
 * 
 * PWA Installation & Kiosk Mode Manager
 * 
 * Features:
 * - Service Worker registration
 * - Install prompt handling
 * - Kiosk mode detection
 * - Auto-refresh on crash
 * - Offline queue management
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';
    
    // Configuration
    const CONFIG = {
        serviceWorkerPath: '/service-worker.js',
        autoRefreshInterval: 300000, // 5 minutes
        enableKioskMode: true,
        offlineQueueEnabled: true
    };
    
    let deferredPrompt = null;
    let isKioskMode = false;
    let swRegistration = null;
    
    /**
     * Initialize PWA
     */
    function initPWA() {
        console.log('🚀 [PWA] Initializing...');
        
        // Check if Service Worker is supported
        if (!('serviceWorker' in navigator)) {
            console.warn('⚠️ [PWA] Service Worker not supported');
            return;
        }
        
        // Register Service Worker
        registerServiceWorker();
        
        // Detect kiosk mode
        detectKioskMode();
        
        // Setup install prompt
        setupInstallPrompt();
        
        // Setup offline queue
        if (CONFIG.offlineQueueEnabled) {
            setupOfflineQueue();
        }
        
        // Setup auto-refresh (kiosk mode)
        if (isKioskMode) {
            setupAutoRefresh();
            disableUserInterruptions();
        }
        
        // Setup update checker
        setupUpdateChecker();
        
        console.log('✅ [PWA] Initialized');
    }
    
    /**
     * Register Service Worker
     */
    async function registerServiceWorker() {
        try {
            swRegistration = await navigator.serviceWorker.register(CONFIG.serviceWorkerPath);
            console.log('✅ [PWA] Service Worker registered:', swRegistration.scope);
            
            // Check for updates
            swRegistration.addEventListener('updatefound', () => {
                console.log('🔄 [PWA] Update found, installing...');
                const newWorker = swRegistration.installing;
                
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        console.log('✅ [PWA] Update installed, refresh to activate');
                        
                        // Auto-refresh in kiosk mode
                        if (isKioskMode) {
                            setTimeout(() => window.location.reload(), 3000);
                        } else {
                            showUpdateNotification();
                        }
                    }
                });
            });
            
        } catch (error) {
            console.error('❌ [PWA] Service Worker registration failed:', error);
        }
    }
    
    /**
     * Detect Kiosk Mode
     */
    function detectKioskMode() {
        // Check URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const kioskParam = urlParams.get('kiosk');
        
        // Check if running in standalone mode (installed PWA)
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                           window.navigator.standalone ||
                           document.referrer.includes('android-app://');
        
        // Check localStorage flag
        const kioskFlag = localStorage.getItem('kioskMode') === 'true';
        
        isKioskMode = kioskParam === 'true' || kioskFlag || (isStandalone && CONFIG.enableKioskMode);
        
        if (isKioskMode) {
            console.log('📺 [PWA] Kiosk Mode ENABLED');
            document.body.classList.add('kiosk-mode');
            localStorage.setItem('kioskMode', 'true');
        }
    }
    
    /**
     * Setup Install Prompt
     */
    function setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('💾 [PWA] Install prompt available');
            e.preventDefault();
            deferredPrompt = e;
            
            // Show custom install button
            showInstallButton();
        });
        
        // Detect successful installation
        window.addEventListener('appinstalled', () => {
            console.log('✅ [PWA] App installed successfully');
            deferredPrompt = null;
            hideInstallButton();
        });
    }
    
    /**
     * Show Install Button
     */
    function showInstallButton() {
        // Check if button already exists
        if (document.getElementById('pwa-install-btn')) return;
        
        // Don't show in kiosk mode
        if (isKioskMode) return;
        
        const button = document.createElement('button');
        button.id = 'pwa-install-btn';
        button.innerHTML = '📱 Installeer App';
        button.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 9999;
            transition: transform 0.2s;
        `;
        
        button.addEventListener('mouseover', () => {
            button.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseout', () => {
            button.style.transform = 'translateY(0)';
        });
        
        button.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            
            console.log('📱 [PWA] Install outcome:', outcome);
            deferredPrompt = null;
            
            if (outcome === 'accepted') {
                hideInstallButton();
            }
        });
        
        document.body.appendChild(button);
    }
    /**
     * Hide Install Button
     */
    function hideInstallButton() {
        const button = document.getElementById('pwa-install-btn');
        if (button) {
            button.remove();
        }
    }
    
    /**
     * Setup Offline Queue
     */
    function setupOfflineQueue() {
        // Listen for online/offline events
        window.addEventListener('online', () => {
            console.log('🌐 [PWA] Online - syncing queue...');
            syncOfflineQueue();
        });
        
        window.addEventListener('offline', () => {
            console.log('📡 [PWA] Offline - queueing enabled');
            showOfflineIndicator();
        });
        
        // Initial check
        if (!navigator.onLine) {
            showOfflineIndicator();
        }
    }
    
    /**
     * Sync Offline Queue
     */
    async function syncOfflineQueue() {
        if ('serviceWorker' in navigator && 'sync' in swRegistration) {
            try {
                await swRegistration.sync.register('sync-checkins');
                console.log('✅ [PWA] Background sync registered');
            } catch (error) {
                console.error('❌ [PWA] Background sync failed:', error);
            }
        }
    }
    
    /**
     * Show Offline Indicator
     */
    function showOfflineIndicator() {
        let indicator = document.getElementById('offline-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'offline-indicator';
            indicator.innerHTML = '📡 OFFLINE - Check-ins worden lokaal opgeslagen';
            indicator.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                padding: 10px;
                background: #f39c12;
                color: white;
                text-align: center;
                font-weight: 600;
                font-size: 14px;
                z-index: 99999;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            `;
            document.body.appendChild(indicator);
        }
        
        indicator.style.display = 'block';
    }
    
    /**
     * Hide Offline Indicator
     */
    function hideOfflineIndicator() {
        const indicator = document.getElementById('offline-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    /**
     * Setup Auto-Refresh (Kiosk Mode)
     */
    function setupAutoRefresh() {
        console.log('🔄 [PWA] Auto-refresh enabled (kiosk mode)');
        
        setInterval(() => {
            console.log('🔄 [PWA] Auto-refreshing...');
            window.location.reload();
        }, CONFIG.autoRefreshInterval);
        
        // Refresh on error
        window.addEventListener('error', (e) => {
            console.error('❌ [PWA] Error detected:', e.message);
            setTimeout(() => {
                console.log('🔄 [PWA] Reloading after error...');
                window.location.reload();
            }, 5000);
        });
    }
    
    /**
     * Disable User Interruptions (Kiosk Mode)
     */
    function disableUserInterruptions() {
        console.log('🔒 [PWA] Disabling user interruptions (kiosk mode)');
        
        // Disable right-click
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            return false;
        });
        
        // Disable F11, Ctrl+W, etc.
        document.addEventListener('keydown', (e) => {
            // F11 (fullscreen toggle)
            if (e.key === 'F11') {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+W (close tab)
            if (e.ctrlKey && e.key === 'w') {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+R (refresh) - allow
            // Ctrl+T (new tab)
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                return false;
            }
            
            // Alt+F4 (close window)
            if (e.altKey && e.key === 'F4') {
                e.preventDefault();
                return false;
            }
        });
        
        // Request fullscreen
        setTimeout(() => {
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(() => {
                    console.log('⚠️ [PWA] Fullscreen request denied');
                });
            }
        }, 1000);
    }
    
    /**
     * Setup Update Checker
     */
    function setupUpdateChecker() {
        // Check for updates every hour
        setInterval(() => {
            if (swRegistration) {
                swRegistration.update();
            }
        }, 3600000); // 1 hour
    }
    
    /**
     * Show Update Notification
     */
    function showUpdateNotification() {
        const notification = document.createElement('div');
        notification.innerHTML = `
            <div style="
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: white;
                padding: 20px 30px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 99999;
                text-align: center;
            ">
                <p style="margin: 0 0 15px 0; font-weight: 600;">Nieuwe versie beschikbaar!</p>
                <button onclick="window.location.reload()" style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 600;
                ">🔄 Ververs Nu</button>
            </div>
        `;
        document.body.appendChild(notification);
    }
    
    /**
     * Public API
     */
    window.PWA = {
        isKioskMode: () => isKioskMode,
        isOnline: () => navigator.onLine,
        install: () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
            }
        },
        sync: syncOfflineQueue
    };
    
    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPWA);
    } else {
        initPWA();
    }
    
})();
