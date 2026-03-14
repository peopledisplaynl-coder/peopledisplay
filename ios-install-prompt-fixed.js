/**
 * iOS Install Prompt - Custom banner voor iPad/iPhone gebruikers
 * Versie: 1.0.1 - Safari share icoon fix
 * Toont instructies omdat iOS geen automatische install prompt heeft
 */

(function() {
    'use strict';
    
    // Detecteer iOS/iPadOS (Safari)
    function isIOS() {
        const ua = window.navigator.userAgent;
        const isIPad = /iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isIPhone = /iPhone/.test(ua);
        const isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua);
        return (isIPad || isIPhone) && isSafari;
    }
    
    // Check of app al geïnstalleerd is
    function isStandalone() {
        return (
            window.navigator.standalone === true ||
            window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches
        );
    }
    
    // Check of banner al eerder getoond/gesloten is
    function wasPromptShown() {
        return localStorage.getItem('ios-install-prompt-shown') === 'true';
    }
    
    // Markeer banner als getoond
    function markPromptAsShown() {
        localStorage.setItem('ios-install-prompt-shown', 'true');
    }
    
    // Maak en toon de installatie banner
    function showInstallPrompt() {
        // Creëer banner element
        const banner = document.createElement('div');
        banner.id = 'ios-install-banner';
        banner.innerHTML = `
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                animation: slideDown 0.3s ease-out;
            ">
                <style>
                    @keyframes slideDown {
                        from { transform: translateY(-100%); }
                        to { transform: translateY(0); }
                    }
                    @keyframes slideUp {
                        from { transform: translateY(0); }
                        to { transform: translateY(-100%); }
                    }
                    .ios-banner-closing {
                        animation: slideUp 0.3s ease-out !important;
                    }
                </style>
                <div style="max-width: 800px; margin: 0 auto; position: relative;">
                    <button id="ios-banner-close" style="
                        position: absolute;
                        top: -8px;
                        right: -8px;
                        background: rgba(255,255,255,0.2);
                        border: none;
                        color: white;
                        font-size: 24px;
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: background 0.2s;
                    " onmouseover="this.style.background='rgba(255,255,255,0.3)'" 
                       onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                        ×
                    </button>
                    
                    <div style="display: flex; align-items: flex-start; gap: 16px;">
                        <div style="
                            background: white;
                            border-radius: 12px;
                            padding: 8px;
                            flex-shrink: 0;
                        ">
                            <img src="/images/icons/icon-72x72.png" width="40" height="40" style="border-radius: 8px;" alt="App Icon">
                        </div>
                        
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 600;">
                                📱 Installeer PeopleDisplay
                            </h3>
                            <p style="margin: 0 0 12px 0; font-size: 14px; opacity: 0.95; line-height: 1.5;">
                                Installeer deze app voor snelle toegang vanaf je iPad/iPhone home screen:
                            </p>
                            
                            <ol style="margin: 0; padding-left: 20px; font-size: 14px; opacity: 0.95; line-height: 1.6;">
                                <li style="margin-bottom: 6px;">
                                    Tap op het <strong>Safari share icoon</strong> 
                                    <span style="display: inline-block; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; margin: 0 4px;">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="vertical-align: middle;">
                                            <rect x="3" y="5" width="8" height="8" rx="1" stroke="white" stroke-width="1.5"/>
                                            <path d="M7 5V1M7 1L5 3M7 1L9 3" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    onderaan je scherm
                                </li>
                                <li style="margin-bottom: 6px;">
                                    Scroll omlaag en tap <strong>"Zet op beginscherm"</strong> of <strong>"Add to Home Screen"</strong>
                                </li>
                                <li>
                                    Tap <strong>"Voeg toe"</strong> of <strong>"Add"</strong> rechtsboven
                                </li>
                            </ol>
                            
                            <p style="margin: 12px 0 0 0; font-size: 12px; opacity: 0.85;">
                                💡 De app werkt dan als een native applicatie!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Voeg banner toe aan pagina
        document.body.appendChild(banner);
        
        // Sluit functie
        function closeBanner() {
            banner.classList.add('ios-banner-closing');
            setTimeout(() => {
                banner.remove();
                markPromptAsShown();
            }, 300);
        }
        
        // Event listener voor sluit knop
        document.getElementById('ios-banner-close').addEventListener('click', closeBanner);
        
        // Auto-close na 30 seconden
        setTimeout(() => {
            if (document.getElementById('ios-install-banner')) {
                closeBanner();
            }
        }, 30000);
    }
    
    // Initialisatie
    function init() {
        // Wacht tot DOM geladen is
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // Check of we moeten tonen
        if (isIOS() && !isStandalone() && !wasPromptShown()) {
            // Wacht 2 seconden voor betere UX
            setTimeout(showInstallPrompt, 2000);
        }
    }
    
    // Start
    init();
    
})();
