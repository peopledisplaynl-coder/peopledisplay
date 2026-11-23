// ============================================================
// GOOGLE PRESENTATION AUTO-SHOW MODULE
// ============================================================
// Bestand: presentation_module.js
// Locatie: ROOT directory (naast app.js)
// Versie: 1.0
// ============================================================

(function() {
    'use strict';
    
    console.log('🎬 Presentation module loading...');
    
    // Configuration
    const CHECK_INTERVAL = 5000; // Check every 5 seconds
    
    // State — idleTimeout comes from the user's setting (presentation_idle_seconds)
    let presentationConfig = {
        canShow: false,
        presentationId: null,
        hasPresentation: false,
        screenMode: 'slides',   // 'slides' | 'infoscreen' | 'combined' (Infoscherm, 2.4.0)
        idleTimeout: 120000
    };
    
    let idleTimer = null;
    let lastActivityTime = Date.now();
    let isPresentationShowing = false;
    let presentationContainer = null;
    
    /**
     * Initialize presentation module
     */
    function initPresentationModule() {
        console.log('🎬 Initializing presentation module...');
        
        // Load user presentation settings
        loadPresentationSettings();
        
        // Setup activity listeners
        setupActivityListeners();
        
        // Start idle checker
        startIdleChecker();
        
        // Create presentation container (hidden)
        createPresentationContainer();
    }
    
    /**
     * Load presentation settings from API
     */
    async function loadPresentationSettings() {
        try {
            const BASE_PATH = window.BASE_PATH || '';
            const response = await fetch(BASE_PATH + '/api/get_user_presentation.php');
            const data = await response.json();
            
            console.log('📊 Presentation API response:', data);
            
            if (data.success) {
                presentationConfig = {
                    canShow: data.can_show,
                    presentationId: data.presentation_id,
                    hasPresentation: data.has_presentation,
                    screenMode: data.screen_mode || 'slides',
                    idleTimeout: Math.max(10, parseInt(data.idle_seconds, 10) || 120) * 1000
                };
                
                console.log('✅ Presentation settings loaded:', presentationConfig);
                
                if (!presentationConfig.canShow) {
                    console.log('ℹ️ Presentation auto-show is disabled for this user');
                }
                if (!presentationConfig.hasPresentation) {
                    console.log('ℹ️ No presentation ID configured for this user');
                }
            } else {
                console.log('⚠️ Failed to load presentation settings:', data.error);
            }
        } catch (error) {
            console.error('❌ Error loading presentation settings:', error);
        }
    }
    
    /**
     * Setup activity listeners
     */
    function setupActivityListeners() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, handleActivity, true);
        });
        
        // Infoscherm (2.4.0): touches inside the infoscreen iframe are reported via postMessage
        window.addEventListener('message', (event) => {
            if (event.origin === window.location.origin && event.data && event.data.type === 'pd-infoscreen-activity') {
                handleActivity();
            }
        });
        
        console.log('✅ Activity listeners registered');
    }
    
    /**
     * Handle user activity
     */
    function handleActivity() {
        lastActivityTime = Date.now();
        
        // If presentation is showing, hide it
        if (isPresentationShowing) {
            console.log('👆 User activity detected - hiding presentation');
            hidePresentation();
        }
    }
    
    /**
     * Start idle checker
     */
    function startIdleChecker() {
        setInterval(() => {
            checkIdle();
        }, CHECK_INTERVAL);
        
        console.log('✅ Idle checker started (check every ' + (CHECK_INTERVAL/1000) + 's)');
    }
    
    /**
     * Check if user is idle
     */
    function checkIdle() {
        // Skip if presentation not configured or already showing
        if (!presentationConfig.canShow || !presentationConfig.hasPresentation || isPresentationShowing) {
            return;
        }
        
        const idleTime = Date.now() - lastActivityTime;
        
        if (idleTime >= presentationConfig.idleTimeout) {
            console.log('💤 User idle for ' + Math.round(idleTime / 1000) + ' seconds - showing presentation');
            showPresentation();
        }
    }
    
    /**
     * Create presentation container
     */
    function createPresentationContainer() {
        presentationContainer = document.createElement('div');
        presentationContainer.id = 'presentation-overlay';
        presentationContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: black;
            z-index: 99999;
            display: none;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        `;
        
        // Add close button
        const closeButton = document.createElement('button');
        closeButton.innerHTML = '✕ Sluiten';
        closeButton.style.cssText = `
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            z-index: 100000;
            transition: all 0.3s;
        `;
        closeButton.onmouseover = () => closeButton.style.background = 'white';
        closeButton.onmouseout = () => closeButton.style.background = 'rgba(255,255,255,0.9)';
        closeButton.onclick = hidePresentation;
        
        // Add iframe (will be populated when showing)
        const iframe = document.createElement('iframe');
        iframe.id = 'presentation-iframe';
        iframe.style.cssText = `
            width: 95vw;
            height: 90vh;
            border: none;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        `;
        
        presentationContainer.appendChild(closeButton);
        presentationContainer.appendChild(iframe);
        document.body.appendChild(presentationContainer);
        
        console.log('✅ Presentation container created');
    }
    
    /**
     * Show presentation
     */
    function showPresentation() {
        const useInfoscreen = presentationConfig.screenMode === 'infoscreen' || presentationConfig.screenMode === 'combined';
        if (!useInfoscreen && !presentationConfig.presentationId) {
            console.log('⚠️ No presentation ID to show');
            return;
        }
        
        const iframe = document.getElementById('presentation-iframe');
        
        // The infoscreen closes on any touch (postMessage), so its own clock stays visible without the close button
        const closeBtn = presentationContainer.querySelector('button');
        if (closeBtn) closeBtn.style.display = useInfoscreen ? 'none' : '';
        
        let embedUrl;
        if (useInfoscreen) {
            // Infoscherm (own messages, news, weather); the combined mode shows the Slides inside it
            // Relative URL: this script runs on index.php in the app root (also in a subfolder install)
            embedUrl = 'infoscherm.php?embed=1';
            iframe.style.width = '100vw';
            iframe.style.height = '100vh';
            iframe.style.borderRadius = '0';
        } else {
            // Build Google Slides embed URL
            embedUrl = `https://docs.google.com/presentation/d/${presentationConfig.presentationId}/embed?start=true&loop=true&delayms=5000`;
            iframe.style.width = '95vw';
            iframe.style.height = '90vh';
            iframe.style.borderRadius = '8px';
        }
        
        iframe.src = embedUrl;
        presentationContainer.style.display = 'flex';
        isPresentationShowing = true;
        
        console.log('🎬 Presentation showing:', presentationConfig.presentationId);
        
        // Try to go fullscreen
        tryFullscreen();
    }
    
    /**
     * Hide presentation
     */
    function hidePresentation() {
        const iframe = document.getElementById('presentation-iframe');
        
        iframe.src = ''; // Stop loading
        presentationContainer.style.display = 'none';
        isPresentationShowing = false;
        
        // Reset activity timer
        lastActivityTime = Date.now();
        
        console.log('🎬 Presentation hidden');
        
        // Exit fullscreen if active
        exitFullscreen();
    }
    
    /**
     * Try to enter fullscreen
     */
    function tryFullscreen() {
        const elem = presentationContainer;
        
        if (elem.requestFullscreen) {
            elem.requestFullscreen().catch(err => {
                console.log('ℹ️ Fullscreen not allowed:', err);
            });
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    }
    
    /**
     * Exit fullscreen
     */
    function exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen().catch(() => {});
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
    
    // Export functions (if needed)
    window.PresentationModule = {
        init: initPresentationModule,
        show: showPresentation,
        hide: hidePresentation,
        reload: loadPresentationSettings
    };
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPresentationModule);
    } else {
        initPresentationModule();
    }
    
    console.log('✅ Presentation module loaded');
    
})();

// ============================================================
// END PRESENTATION MODULE
// ============================================================
