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
    const IDLE_TIMEOUT = 60000; // 60 seconds idle before showing presentation
    const CHECK_INTERVAL = 5000; // Check every 5 seconds
    
    // State
    let presentationConfig = {
        canShow: false,
        presentationId: null,
        hasPresentation: false
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
                    hasPresentation: data.has_presentation
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
        
        if (idleTime >= IDLE_TIMEOUT) {
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
        if (!presentationConfig.presentationId) {
            console.log('⚠️ No presentation ID to show');
            return;
        }
        
        const iframe = document.getElementById('presentation-iframe');
        
        // Build Google Slides embed URL
        const embedUrl = `https://docs.google.com/presentation/d/${presentationConfig.presentationId}/embed?start=true&loop=true&delayms=5000`;
        
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
