/**
 * ═══════════════════════════════════════════════════════════════════
 * WiFi Auto Check-in v4.0
 * 
 * NIEUWE FEATURES:
 * - ✅ Popup komt niet meer terug na "Ja, check in"
 * - ✅ Herhaalde check na "Nee" als nog steeds OUT
 * - ✅ Auto check-out na 5 min (was 15) geen WiFi
 * - ✅ Betere status tracking en logging
 * ═══════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';
    
    // Configuration
    const CONFIG = {
        checkInterval: 60000,         // Check every 60 seconds
        snoozeMinutes: 30,            // Ask again after 30 minutes if dismissed
        promptTimeout: 20000,         // Auto-dismiss after 20 seconds
        gracePeriodMinutes: 2,        // ⚡ 2 MINUTES grace period (was 5)
        recheckIfOutMinutes: 3        // ✅ Recheck if still OUT after 3 min (was 15)
    };
    
    // State
    let checkInterval = null;
    let gracePeriodInterval = null;
    let lastDetectedIP = null;
    let lastConnectionType = null;
    let currentLocation = null;
    let promptShown = false;
    let isCheckingIn = false;
    let wifiLostTime = null;
    let lastKnownLocation = null;
    let lastSuccessfulCheck = Date.now();
    let countdownModal = null;
    let countdownInterval = null;
    let autoCheckoutCompleted = false;  // 🆕 Prevent restart after checkout
    
    /**
     * 🆕 Show grace period countdown modal
     */
    function showGracePeriodModal() {
        // Don't show if already showing
        if (countdownModal) return;
        
        // 🔔 PLAY WARNING SOUND
        playWarningSound('start');
        
        countdownModal = document.createElement('div');
        countdownModal.id = 'grace-period-modal';
        countdownModal.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.85); z-index: 9999999;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            animation: fadeIn 0.3s ease;
        `;
        
        countdownModal.innerHTML = `
            <style>
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
                @keyframes pulse { 
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
            </style>
            <div style="background: white; border-radius: 20px; max-width: 420px; width: 100%; padding: 35px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.5); animation: slideUp 0.4s ease;">
                <div style="font-size: 72px; margin-bottom: 20px; animation: pulse 2s infinite;">⚠️</div>
                <h2 style="margin: 0 0 12px 0; color: #e74c3c; font-size: 26px; font-weight: 700;">WiFi Verbinding Verloren!</h2>
                <p style="color: #7f8c8d; margin: 0 0 25px 0; font-size: 15px; line-height: 1.6;">
                    Je bent niet meer verbonden met het WiFi netwerk.<br>
                    Automatische uitcheck over:
                </p>
                
                <!-- COUNTDOWN DISPLAY -->
                <div style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);">
                    <div id="countdown-display" style="font-size: 64px; font-weight: 900; font-family: 'Arial Black', sans-serif; letter-spacing: 2px;">5:00</div>
                    <div style="font-size: 14px; margin-top: 8px; opacity: 0.9; font-weight: 600;">minuten</div>
                </div>
                
                <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 12px; padding: 15px; margin-bottom: 25px;">
                    <p style="color: #856404; font-size: 13px; margin: 0; line-height: 1.5;">
                        💡 <strong>Tip:</strong> Maak opnieuw verbinding met WiFi om uitcheck te annuleren
                    </p>
                </div>
                
                <button id="grace-cancel-btn" style="width: 100%; padding: 16px; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; margin-bottom: 12px; box-shadow: 0 4px 15px rgba(72, 187, 120, 0.4);">✓ Ik Blijf Ingecheckt</button>
                <button id="grace-checkout-now-btn" style="width: 100%; padding: 14px; background: #f8f9fa; color: #495057; border: 2px solid #e9ecef; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer;">🚪 Check Nu Uit</button>
            </div>
        `;
        
        document.body.appendChild(countdownModal);
        
        // Button handlers
        document.getElementById('grace-cancel-btn').addEventListener('click', cancelGracePeriod);
        document.getElementById('grace-checkout-now-btn').addEventListener('click', checkOutNow);
        
        // Start countdown updater
        updateCountdown();
        countdownInterval = setInterval(updateCountdown, 1000); // Update every second
        
        console.log('⏰ [Grace] Countdown modal shown');
    }
    
    /**
     * 🆕 Update countdown display
     */
    function updateCountdown() {
        console.log('🔄 [Countdown] Update tick');
        
        if (!wifiLostTime) {
            console.log('⚠️ [Countdown] No wifiLostTime - stopping');
            return;
        }
        
        if (!countdownModal) {
            console.log('⚠️ [Countdown] No modal - stopping');
            return;
        }
        
        const elapsed = Date.now() - wifiLostTime;
        const remaining = (CONFIG.gracePeriodMinutes * 60 * 1000) - elapsed;
        
        console.log(`⏰ [Countdown] Elapsed: ${Math.floor(elapsed/1000)}s, Remaining: ${Math.floor(remaining/1000)}s`);
        
        if (remaining <= 0) {
            // Time's up! - TRIGGER CHECKOUT
            console.log('⏰⏰⏰ [Countdown] ZERO REACHED! ⏰⏰⏰');
            showToast('⏰ TIJD OP! Uitchecken...', 5000); // 🆕 Visual feedback
            
            const display = document.getElementById('countdown-display');
            if (display) {
                display.textContent = '0:00';
                display.style.animation = 'pulse 0.5s infinite';
            }
            
            // Stop countdown interval
            if (countdownInterval) {
                console.log('🛑 [Countdown] Stopping interval');
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            
            console.log('🚪🚪🚪 [Countdown] CALLING autoCheckOut() NOW! 🚪🚪🚪');
            
            // Hide modal first
            hideGracePeriodModal();
            
            // Then checkout
            autoCheckOut().then(() => {
                console.log('✅ [Countdown] autoCheckOut completed');
                showToast('✅ Checkout voltooid', 3000); // 🆕 Visual feedback
            }).catch(err => {
                console.error('❌ [Countdown] autoCheckOut failed:', err);
                showToast('❌ Checkout GEFAALD: ' + err.message, 5000); // 🆕 Visual feedback
            });
            
            return;
        }
        
        const minutes = Math.floor(remaining / 1000 / 60);
        const seconds = Math.floor((remaining / 1000) % 60);
        const timeString = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        // 🔔 PLAY WARNING SOUND AT 1 MINUTE
        if (minutes === 1 && seconds === 0) {
            playWarningSound('warning');
        }
        
        const display = document.getElementById('countdown-display');
        if (display) {
            display.textContent = timeString;
            
            // Change color when less than 1 minute
            if (minutes < 1) {
                display.parentElement.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
                display.style.animation = 'pulse 1s infinite';
            }
        }
    }
    
    /**
     * 🆕 Hide grace period modal
     */
    function hideGracePeriodModal() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
        
        if (countdownModal) {
            countdownModal.remove();
            countdownModal = null;
            console.log('⏰ [Grace] Countdown modal hidden');
        }
    }
    
    /**
     * 🆕 Cancel grace period (user wants to stay checked in)
     */
    function cancelGracePeriod() {
        console.log('✅ [Grace] User cancelled - staying checked in');
        
        // Reset grace period
        wifiLostTime = null;
        lastKnownLocation = null;
        
        hideGracePeriodModal();
        showToast('✅ Je blijft ingecheckt');
    }
    
    /**
     * 🆕 Check out immediately
     */
    async function checkOutNow() {
        console.log('🚪 [Grace] User requested immediate checkout');
        hideGracePeriodModal();
        await autoCheckOut();
    }
    
    /**
     * 🆕 Check if prompt was dismissed today (prevents multiple prompts same day)
     */
    function isPromptDismissedToday(locationId) {
        const key = `wifiPromptDismissed_${locationId}`;
        const dismissedDate = localStorage.getItem(key);
        if (!dismissedDate) return false;
        
        const today = new Date().toDateString();
        const dismissed = new Date(dismissedDate).toDateString();
        
        return today === dismissed;
    }
    
    /**
     * Dismiss for entire day
     */
    function dismissForToday(locationId) {
        const key = `wifiPromptDismissed_${locationId}`;
        localStorage.setItem(key, new Date().toISOString());
        console.log('🚫 [WiFi] Dismissed for today');
    }
    
    /**
     * 🆕 Mark as checked-in today (prevents popup after successful check-in)
     */
    function markCheckedInToday(locationId) {
        const key = `wifiCheckedIn_${locationId}`;
        localStorage.setItem(key, new Date().toISOString());
        console.log('✅ [WiFi] Marked as checked-in for today');
    }
    
    /**
     * 🆕 Check if already checked-in today
     */
    async function isCheckedInToday(locationId) {
        const key = `wifiCheckedIn_${locationId}`;
        const checkedInDate = localStorage.getItem(key);
        
        if (!checkedInDate) return false;
        
        const today = new Date().toDateString();
        const checkedIn = new Date(checkedInDate).toDateString();
        
        if (today !== checkedIn) {
            // Different day - clear old marker
            localStorage.removeItem(key);
            return false;
        }
        
        // ✅ EXTRA CHECK: Verify employee is actually IN
        try {
            const employee = await getLinkedEmployee();
            if (employee) {
                const response = await fetch(`/api/get_employee.php?id=${employee.employee_id}`);
                const data = await response.json();
                
                if (data.success && data.employee) {
                    const status = data.employee.status;
                    
                    if (status === 'OUT') {
                        // Employee is OUT - clear marker
                        console.log('🔄 [WiFi] Employee is OUT - clearing check-in marker');
                        localStorage.removeItem(key);
                        return false;
                    }
                    
                    console.log(`✅ [WiFi] Employee is ${status} - marker valid`);
                    return true;
                }
            }
        } catch (e) {
            console.log('⚠️ [WiFi] Status check failed, trusting marker');
        }
        
        // Fallback: trust the marker
        return true;
    }
    
    /**
     * Snooze prompt for X minutes
     */
    function isPromptSnoozed(locationId) {
        const key = `wifiPromptSnoozed_${locationId}`;
        const snoozedTime = localStorage.getItem(key);
        if (!snoozedTime) return false;
        
        const elapsed = Date.now() - parseInt(snoozedTime);
        const minutesElapsed = elapsed / 1000 / 60;
        
        if (minutesElapsed >= CONFIG.snoozeMinutes) {
            localStorage.removeItem(key);
            return false;
        }
        
        console.log(`⏰ [WiFi] Snoozed (${Math.ceil(CONFIG.snoozeMinutes - minutesElapsed)} min left)`);
        return true;
    }
    
    /**
     * Set snooze
     */
    function snoozePrompt(locationId) {
        const key = `wifiPromptSnoozed_${locationId}`;
        localStorage.setItem(key, Date.now().toString());
    }
    
    /**
     * 🆕 Check for grace period expiry (runs on separate timer)
     */
    async function checkGracePeriod() {
        // Only check if we should be running
        if (!shouldRun()) return;
        
        // 🆕 Check connection type FIRST (before API call)
        const onWiFi = isOnWiFi();
        const connectionType = getConnectionType();
        
        if (!onWiFi) {
            console.log(`📡 [WiFi] Not on WiFi (type: ${connectionType})`);
        }
        
        // 🆕 If WiFi just lost and we have a linked employee, start grace period immediately
        const linkedEmployeeID = localStorage.getItem('linkedEmployeeID');
        if (!onWiFi && linkedEmployeeID && !wifiLostTime && !autoCheckoutCompleted) {
            console.log('⚠️ [Grace] WiFi lost detected - checking employee status first');
            
            // ✅ FIX: Check employee status BEFORE starting grace period
            try {
                const employee = await getLinkedEmployee();
                if (employee) {
                    const statusResponse = await fetch(`/api/get_employee.php?id=${employee.employee_id}`);
                    const statusData = await statusResponse.json();
                    
                    if (statusData.success && statusData.employee) {
                        const currentStatus = statusData.employee.status;
                        console.log(`📊 [Grace] Employee current status: ${currentStatus}`);
                        
                        // ✅ ONLY start grace period if employee is IN
                        if (currentStatus !== 'IN') {
                            console.log('🛑 [Grace] Employee is OUT - not starting grace period');
                            return; // EXIT - don't start grace period
                        }
                    }
                }
            } catch (e) {
                console.error('❌ [Grace] Status check failed:', e);
                // Continue anyway if check fails
            }
            
            console.log('✅ [Grace] Employee is IN - starting grace period');
            
            // Try to get employee from localStorage first (faster)
            const mockEmployee = localStorage.getItem('mockEmployee');
            let employeeLocation = 'Onbekende Locatie';
            
            if (mockEmployee) {
                try {
                    const parsed = JSON.parse(mockEmployee);
                    employeeLocation = parsed.locatie || employeeLocation;
                } catch (e) {}
            }
            
            // Start grace period immediately
            wifiLostTime = Date.now();
            lastKnownLocation = {
                id: employeeLocation,
                name: employeeLocation
            };
            
            console.log(`⏰ [WiFi] WiFi lost (type: ${connectionType}) - grace period started (${CONFIG.gracePeriodMinutes} min)`);
            showGracePeriodModal();
            return; // Don't do API check if no WiFi
        }
        
        // 🆕 If already in grace period and no WiFi, skip API check (will fail anyway)
        if (wifiLostTime && !onWiFi) {
            console.log('⏰ [Grace] Grace period active, no WiFi - skipping API check');
            
            // Just check if time expired (fallback)
            const minutesElapsed = (Date.now() - wifiLostTime) / 1000 / 60;
            const minutesLeft = CONFIG.gracePeriodMinutes - minutesElapsed;
            
            if (minutesLeft <= 0 && !autoCheckoutCompleted) {
                console.log('🚨 [Grace] FALLBACK TRIGGER - Time expired!');
                showToast('🚨 Tijd verstreken - uitchecken...', 3000);
                hideGracePeriodModal();
                await autoCheckOut();
            }
            
            return; // Don't do API check
        }
        
        // Check if employee is currently IN (only if we have WiFi or grace period active)
        const employee = await getLinkedEmployee();
        if (!employee) return;
        
        try {
            const response = await fetch(`/api/get_employee.php?id=${employee.employee_id}`);
            const data = await response.json();
            
            if (!data.success || !data.employee) return;
            
            const isIN = data.employee.status === 'IN';
            
            if (!isIN) {
                // Not IN, no need for grace period
                if (wifiLostTime) {
                    console.log('✅ [WiFi] Employee not IN - cancelling grace period');
                    wifiLostTime = null;
                    lastKnownLocation = null;
                    hideGracePeriodModal(); // ✅ HIDE MODAL IMMEDIATELY
                }
                
                // ✅ STOP checking - employee is OUT
                return;
            }
            
            // Employee is IN - check if WiFi is still there
            const timeSinceLastCheck = Date.now() - lastSuccessfulCheck;
            const minutesSinceLastCheck = timeSinceLastCheck / 1000 / 60;
            
            // 🆕 WiFi lost if: 1) Not on WiFi connection, OR 2) No successful check in 2 minutes
            const wifiLost = !onWiFi || minutesSinceLastCheck >= 2;
            
            if (wifiLost) {
                // 🆕 Don't restart if auto-checkout was completed
                if (autoCheckoutCompleted) {
                    console.log('🛑 [Grace] Auto-checkout completed - not restarting grace period');
                    return;
                }
                
                if (!wifiLostTime) {
                    // Start grace period
                    wifiLostTime = Date.now();
                    lastKnownLocation = { 
                        name: data.employee.locatie,
                        id: data.employee.locatie
                    };
                    
                    const reason = !onWiFi ? `Verbinding type: ${connectionType}` : 'Geen WiFi check in 2 min';
                    console.log(`⏰ [WiFi] WiFi lost (${reason}) - grace period started (${CONFIG.gracePeriodMinutes} min)`);
                    
                    // 🆕 Show countdown modal instead of toast
                    showGracePeriodModal();
                } else {
                    // Grace period active
                    const minutesElapsed = (Date.now() - wifiLostTime) / 1000 / 60;
                    const minutesLeft = CONFIG.gracePeriodMinutes - minutesElapsed;
                    
                    console.log(`⏰ [Grace] ${Math.ceil(minutesLeft)} min left (on WiFi: ${onWiFi}, type: ${connectionType})`);
                    
                    // Make sure modal is showing (countdown handles the actual checkout)
                    if (!countdownModal) {
                        showGracePeriodModal();
                    }
                    
                    // 🆕 FALLBACK: Double-check if time expired (in case countdown failed)
                    if (minutesLeft <= 0) {
                        console.log('🚨 [Grace] FALLBACK TRIGGER - Time expired but countdown missed it!');
                        showToast('🚨 FALLBACK: Uitchecken nu...', 3000);
                        hideGracePeriodModal();
                        await autoCheckOut();
                    }
                }
            } else {
                // Recent successful check AND on WiFi - all good
                if (wifiLostTime) {
                    console.log('✅ [WiFi] WiFi restored - grace period cancelled');
                    wifiLostTime = null;
                    lastKnownLocation = null;
                    hideGracePeriodModal();  // 🆕 Hide modal
                    showToast('✅ WiFi hersteld - uitcheck geannuleerd');
                    
                    // ✅ TRIGGER CHECK-IN CHECK (after auto-checkout or location change)
                    console.log('🔄 [WiFi] Triggering check-in check after WiFi restore...');
                    setTimeout(() => {
                        checkWiFi(); // Check for auto check-in
                    }, 3000); // Wait 3 seconds to let things settle
                }
            }
            
        } catch (error) {
            console.error('❌ [Grace] Check failed:', error);
            // If API check fails (no internet), keep grace period running
        }
    }
    
    /**
     * Auto check-out
     */
    async function autoCheckOut() {
        console.log('🚪 [AutoCheckout] Function called');
        showToast('🚪 Auto checkout gestart...', 3000); // 🆕 Visual
        
        const employee = await getLinkedEmployee();
        if (!employee || !lastKnownLocation) {
            console.log('⚠️ [WiFi] Cannot auto check-out - missing data');
            console.log('   Employee:', employee ? 'OK' : 'MISSING');
            console.log('   Location:', lastKnownLocation ? lastKnownLocation.name : 'MISSING');
            showToast('❌ Checkout gefaald: geen employee/locatie', 5000); // 🆕 Visual
            wifiLostTime = null;
            lastKnownLocation = null;
            return;
        }
        
        // Save location ID BEFORE resetting (to avoid crash)
        const locationId = lastKnownLocation.id;
        const locationName = lastKnownLocation.name;
        
        // 🆕 CRITICAL: Reset grace period IMMEDIATELY to prevent restart
        console.log('🛑 [AutoCheckout] Resetting grace period to prevent restart');
        wifiLostTime = null;
        lastKnownLocation = null;
        autoCheckoutCompleted = true;  // 🆕 Set flag to prevent restart
        
        showToast(`🚪 Uitchecken van ${locationName}...`, 3000); // 🆕 Visual
        
        try {
            console.log(`🚪 [WiFi] Auto checking out from ${locationName}`);
            console.log(`   Employee ID: ${employee.employee_id}`);
            console.log(`   Calling API: /api/update_employee_status.php`);
            
            const response = await fetch('/api/update_employee_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    employee_id: employee.employee_id,
                    status: 'OUT',
                    locatie: locationName
                })
            });
            
            console.log('📡 [AutoCheckout] API response received');
            
            const data = await response.json();
            console.log('📡 [AutoCheckout] API data:', data);
            
            if (data.success) {
                console.log('✅ [WiFi] Auto check-out successful');
                showToast('✅ Succesvol uitgecheckt!', 3000); // 🆕 Visual
            } else {
                console.error('❌ [AutoCheckout] API returned error:', data.error);
                showToast('⚠️ API Error: ' + data.error, 5000); // 🆕 Visual
            }
        } catch (error) {
            console.error('❌ [WiFi] Auto check-out API call failed:', error);
            console.error('   Error details:', error.message);
            console.error('   Stack:', error.stack);
            showToast('❌ API Call Failed: ' + error.message, 5000); // 🆕 Visual
        }
        
        // 🆕 ALWAYS clear check-in marker
        try {
            const keyCheckedIn = `wifiCheckedIn_${locationId}`;
            localStorage.removeItem(keyCheckedIn);
            console.log('🗑️ [AutoCheckout] Cleared check-in marker');
            
            // ✅ FIX: Also clear last prompt timestamp
            const keyLastPrompt = `wifiLastPrompt_${locationId}`;
            localStorage.removeItem(keyLastPrompt);
            console.log('🗑️ [AutoCheckout] Cleared last prompt timestamp');
            
            // ✅ Also clear dismissed/snoozed markers for this location
            const keyDismissed = `wifiPromptDismissed_${locationId}`;
            const keySnoozed = `wifiPromptSnoozed_${locationId}`;
            localStorage.removeItem(keyDismissed);
            localStorage.removeItem(keySnoozed);
            console.log('🗑️ [AutoCheckout] Cleared all WiFi markers for location');
        } catch (e) {
            console.error('❌ Failed to clear markers:', e);
        }
        
        // 🆕 ALWAYS reload after 2 seconds, regardless of API success/failure
        console.log('🔄 [AutoCheckout] Reloading page in 2 seconds...');
        showToast('🔄 Pagina wordt herladen...', 2000);
        
        setTimeout(() => {
            console.log('🔄 [AutoCheckout] RELOAD NOW!');
            location.reload(true); // Force reload from server
        }, 2000);
    }
    
    /**
     * 🆕 Check if employee is currently OUT and should be prompted again
     */
    async function shouldPromptIfOut(locationId) {
        const key = `wifiLastPrompt_${locationId}`;
        const lastPrompt = localStorage.getItem(key);
        
        if (!lastPrompt) return true; // Never prompted
        
        const elapsed = Date.now() - parseInt(lastPrompt);
        const minutesElapsed = elapsed / 1000 / 60;
        
        // If already checked-in today, don't prompt
        if (await isCheckedInToday(locationId)) {
            return false;
        }
        
        // If dismissed for today, don't prompt
        if (isPromptDismissedToday(locationId)) {
            return false;
        }
        
        // If less than recheckIfOutMinutes, don't prompt yet
        if (minutesElapsed < CONFIG.recheckIfOutMinutes) {
            return false;
        }
        
        // Check if employee is still OUT
        const employee = await getLinkedEmployee();
        if (!employee) return false;
        
        // Fetch current status
        try {
            const response = await fetch(`/api/get_employee.php?id=${employee.employee_id}`);
            const data = await response.json();
            
            if (data.success && data.employee) {
                const isOut = data.employee.status === 'OUT';
                console.log(`📊 [WiFi] Employee status: ${data.employee.status}`);
                return isOut; // Only prompt if OUT
            }
        } catch (error) {
            console.error('❌ [WiFi] Status check failed:', error);
        }
        
        return false;
    }
    
    /**
     * Should this module run?
     */
    function shouldRun() {
        // Check if disabled
        const disabled = localStorage.getItem('wifiAutoCheckinEnabled') === 'false';
        if (disabled) {
            console.log('ℹ️ [WiFi] Disabled in localStorage');
            return false;
        }
        
        // Check if mobile PWA
        const isMobilePWA = window.matchMedia('(display-mode: standalone)').matches ||
                           window.navigator.standalone === true;
        
        if (!isMobilePWA) {
            console.log('ℹ️ [WiFi] Not a mobile PWA');
            return false;
        }
        
        // Check if employee is linked
        const linkedID = localStorage.getItem('linkedEmployeeID');
        if (!linkedID) {
            console.log('ℹ️ [WiFi] No employee linked');
            return false;
        }
        
        return true;
    }
    
    /**
     * Get linked employee data
     */
    async function getLinkedEmployee() {
        const employeeID = localStorage.getItem('linkedEmployeeID');
        if (!employeeID) return null;
        
        // 🆕 Check for mock employee (for testing)
        const mockEmployee = localStorage.getItem('mockEmployee');
        if (mockEmployee) {
            try {
                const parsed = JSON.parse(mockEmployee);
                console.log('🧪 [TEST] Using mock employee:', parsed);
                return parsed;
            } catch (e) {
                // Not valid JSON, continue to API
            }
        }
        
        try {
            const response = await fetch(`/api/get_employee.php?id=${employeeID}`);
            const data = await response.json();
            
            if (data.success && data.employee) {
                return data.employee;
            }
        } catch (error) {
            console.error('❌ [WiFi] Employee fetch failed:', error);
        }
        
        return null;
    }
    
    /**
     * 🆕 Detect connection type (WiFi, Cellular, Ethernet, etc)
     */
    function getConnectionType() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        
        if (!connection) {
            console.log('ℹ️ [WiFi] Connection API not available');
            return 'unknown';
        }
        
        // effectiveType: slow-2g, 2g, 3g, 4g, 5g
        // type: wifi, cellular, ethernet, none, unknown
        const type = connection.type || connection.effectiveType || 'unknown';
        
        console.log(`📡 [Connection] Type: ${type}, Effective: ${connection.effectiveType || 'unknown'}`);
        
        return type;
    }
    
    /**
     * 🆕 Check if on WiFi connection
     */
    function isOnWiFi() {
        const type = getConnectionType();
        
        // WiFi = good
        if (type === 'wifi') return true;
        
        // Cellular/5G/4G = NOT WiFi
        if (type === 'cellular' || type.includes('g')) return false;
        
        // Ethernet = treat as WiFi (desktop/fixed connection)
        if (type === 'ethernet') return true;
        
        // Unknown = assume WiFi (legacy browsers)
        return true;
    }
    
    /**
     * Detect external IP
     */
    async function detectExternalIP() {
        try {
            const response = await fetch('https://api.ipify.org?format=json');
            const data = await response.json();
            return data.ip || null;
        } catch (error) {
            console.error('❌ [WiFi] IP detection failed:', error);
            return null;
        }
    }
    
    /**
     * Match IP to location
     */
    async function matchIPToLocation(ip) {
        try {
            const response = await fetch(`/api/check_location_by_ip.php?ip=${encodeURIComponent(ip)}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('❌ [WiFi] Location match failed:', error);
            return null;
        }
    }
    
    /**
     * Check WiFi status
     */
    async function checkWiFi() {
        if (!shouldRun()) return;
        
        try {
            // Detect IP with timeout
            const ip = await Promise.race([
                detectExternalIP(),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), 10000))
            ]);
            
            if (!ip) {
                console.log('⚠️ [WiFi] No IP detected');
                // Don't update lastSuccessfulCheck - WiFi might be gone
                return;
            }
            
            // IP detected successfully
            lastSuccessfulCheck = Date.now();  // 🆕 Mark successful check
            
            // Same IP as before?
            if (ip === lastDetectedIP && promptShown) {
                // WiFi is back - cancel grace period
                if (wifiLostTime) {
                    console.log('✅ [WiFi] WiFi restored - grace period cancelled');
                    wifiLostTime = null;
                    hideGracePeriodModal();
                    showToast('✅ WiFi hersteld');
                }
                // ✅ DON'T return - continue to check for auto check-in!
                // Employee might need to check-in again
            }
            
            lastDetectedIP = ip;
            console.log('📡 [WiFi] Detected IP:', ip);
            
            // Check location
            const location = await Promise.race([
                matchIPToLocation(ip),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), 5000))
            ]);
            
            if (location && location.found) {
                console.log('✅ [WiFi] Location matched:', location.location.name);
                currentLocation = location.location;
                
                // Store as last known location
                lastKnownLocation = location.location;
                
                // 🆕 Check if already checked-in today
                if (await isCheckedInToday(currentLocation.id)) {
                    console.log('✅ [WiFi] Already checked-in today');
                    return;
                }
                
                // Check dismissals
                if (isPromptDismissedToday(currentLocation.id)) {
                    console.log('ℹ️ [WiFi] Dismissed for today');
                    return;
                }
                
                if (isPromptSnoozed(currentLocation.id)) {
                    return;
                }
                
                // 🆕 Check if should prompt (based on OUT status)
                const shouldPrompt = await shouldPromptIfOut(currentLocation.id);
                if (!shouldPrompt) {
                    console.log('ℹ️ [WiFi] Not prompting (recently asked or not OUT)');
                    return;
                }
                
                // Show prompt
                if (!promptShown) {
                    // Mark that we prompted
                    const key = `wifiLastPrompt_${currentLocation.id}`;
                    localStorage.setItem(key, Date.now().toString());
                    
                    showCheckInPrompt(location.location);
                }
            } else {
                console.log('ℹ️ [WiFi] No location match');
                currentLocation = null;
                promptShown = false;
            }
            
        } catch (error) {
            console.error('❌ [WiFi] Check failed:', error.message);
            if (error.message === 'Timeout') {
                console.warn('⚠️ [WiFi] Disabling due to timeouts');
                stop();
            }
        }
    }
    
    /**
     * Show check-in prompt
     */
    async function showCheckInPrompt(location) {
        const employee = await getLinkedEmployee();
        if (!employee) {
            console.error('❌ [WiFi] No employee data');
            return;
        }
        
        promptShown = true;
        
        const prompt = document.createElement('div');
        prompt.id = 'wifi-checkin-prompt';
        prompt.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.75); z-index: 999999;
            display: flex; align-items: center; justify-content: center; padding: 20px;
            animation: fadeIn 0.3s ease;
        `;
        
        prompt.innerHTML = `
            <style>
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            </style>
            <div style="background: white; border-radius: 20px; max-width: 400px; width: 100%; padding: 30px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.4); animation: slideUp 0.4s ease;">
                <div style="font-size: 64px; margin-bottom: 15px;">📍</div>
                <h2 style="margin: 0 0 8px 0; color: #2c3e50; font-size: 24px; font-weight: 700;">Locatie Gedetecteerd!</h2>
                <p style="color: #7f8c8d; margin: 0 0 8px 0; font-size: 14px;">Je bent verbonden met</p>
                <p style="color: #667eea; font-size: 22px; font-weight: 700; margin: 0 0 20px 0;">${location.name}</p>
                <div style="background: #f8f9fa; border-radius: 12px; padding: 15px; margin-bottom: 25px;">
                    <p style="color: #495057; font-size: 14px; margin: 0;">Inchecken als</p>
                    <p style="color: #2c3e50; font-size: 18px; font-weight: 600; margin: 5px 0 0 0;">${employee.naam}</p>
                    ${employee.functie ? `<p style="color: #7f8c8d; font-size: 13px; margin: 3px 0 0 0;">${employee.functie}</p>` : ''}
                </div>
                <button id="wifi-checkin-accept" style="width: 100%; padding: 16px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; margin-bottom: 10px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">✓ Ja, Check In</button>
                <button id="wifi-checkin-snooze" style="width: 100%; padding: 14px; background: #f8f9fa; color: #495057; border: 2px solid #e9ecef; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; margin-bottom: 10px;">✕ Niet Nu (over 30 min)</button>
                <button id="wifi-checkin-dismiss-today" style="width: 100%; padding: 12px; background: transparent; color: #e74c3c; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;">🚫 Vandaag Niet Meer</button>
            </div>
        `;
        
        document.body.appendChild(prompt);
        
        document.getElementById('wifi-checkin-accept').addEventListener('click', acceptCheckIn);
        document.getElementById('wifi-checkin-snooze').addEventListener('click', () => {
            snoozePrompt(currentLocation.id);
            dismissPrompt();
            showToast('⏰ Vraag over 30 minuten');
        });
        document.getElementById('wifi-checkin-dismiss-today').addEventListener('click', () => {
            dismissForToday(currentLocation.id);
            dismissPrompt();
            showToast('🚫 Vandaag niet meer');
        });
        
        // Auto-dismiss after timeout
        setTimeout(() => {
            if (document.getElementById('wifi-checkin-prompt')) {
                snoozePrompt(currentLocation.id);
                dismissPrompt();
                showToast('⏰ Automatisch uitgesteld');
            }
        }, CONFIG.promptTimeout);
    }
    
    /**
     * Accept check-in
     */
    async function acceptCheckIn() {
        if (isCheckingIn) return;
        
        const employee = await getLinkedEmployee();
        if (!employee || !currentLocation) {
            dismissPrompt();
            return;
        }
        
        isCheckingIn = true;
        stop(); // Stop checking while we process
        
        try {
            const response = await fetch('/api/update_employee_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    employee_id: employee.employee_id,
                    status: 'IN',
                    locatie: currentLocation.name
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // 🆕 Mark as checked-in today - prevents popup from coming back!
                markCheckedInToday(currentLocation.id);
                
                dismissPrompt();
                showToast(`✅ Ingecheckt bij ${currentLocation.name}`);
                setTimeout(() => location.reload(), 2000);
            } else {
                throw new Error(data.error || 'Failed');
            }
        } catch (error) {
            console.error('❌ Check-in failed:', error);
            showToast('❌ Check-in mislukt');
            isCheckingIn = false;
            start(); // Resume checking
        }
    }
    
    function dismissPrompt() {
        const prompt = document.getElementById('wifi-checkin-prompt');
        if (prompt) prompt.remove();
        promptShown = false;
    }
    
    function showToast(message, duration = 3000) {
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = `position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%); background: #2c3e50; color: white; padding: 14px 24px; border-radius: 12px; font-size: 15px; font-weight: 600; box-shadow: 0 6px 20px rgba(0,0,0,0.3); z-index: 9999999; animation: slideUp 0.3s ease; max-width: 90%; text-align: center;`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), duration);
    }
    
    function showPersistentToast(message) {
        // Remove any existing persistent toast
        const existing = document.getElementById('persistent-toast');
        if (existing) existing.remove();
        
        const toast = document.createElement('div');
        toast.id = 'persistent-toast';
        toast.innerHTML = `
            <div style="font-size: 18px; margin-bottom: 10px;">${message}</div>
            <button onclick="this.parentElement.remove()" style="padding: 8px 16px; background: white; color: #2c3e50; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Sluiten</button>
        `;
        toast.style.cssText = `position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 16px; font-size: 16px; font-weight: 600; box-shadow: 0 20px 60px rgba(0,0,0,0.5); z-index: 99999999; max-width: 90%; text-align: center; animation: slideUp 0.4s ease;`;
        document.body.appendChild(toast);
    }
    
    function start() {
        if (checkInterval) return;
        console.log('✅ [WiFi v5.1] Started');
        
        // Log initial connection type
        const connType = getConnectionType();
        console.log(`📡 [WiFi] Initial connection: ${connType}`);
        
        // Main WiFi check
        checkWiFi();
        checkInterval = setInterval(checkWiFi, CONFIG.checkInterval);
        
        // Separate grace period checker (runs every 30 seconds)
        checkGracePeriod();
        gracePeriodInterval = setInterval(checkGracePeriod, 30000);
    }
    
    function stop() {
        if (checkInterval) {
            clearInterval(checkInterval);
            checkInterval = null;
        }
        if (gracePeriodInterval) {
            clearInterval(gracePeriodInterval);
            gracePeriodInterval = null;
        }
        hideGracePeriodModal();  // 🆕 Clean up modal
        console.log('🛑 [WiFi] Stopped');
    }
    
    async function init() {
        console.log('🌐 [WiFi v6.7 - 2 MIN] Initializing...');
        console.log('⚡ [WiFi] Grace period: 2 MINUTES (testing)');
        console.log('🆕 [WiFi] API skip during grace: ENABLED');
        console.log('⚙️ [WiFi] Instant WiFi detection: ENABLED');
        console.log('⚙️ [WiFi] Fallback auto-checkout: ENABLED');
        console.log('⚙️ [WiFi] Page reload after checkout: ENABLED');
        
        if (!shouldRun()) {
            console.log('ℹ️ [WiFi] Not running (see above for reason)');
            return;
        }
        
        console.log('✅ [WiFi] All requirements met - starting');
        start();
    }
    
    // Export API
    window.WiFiAutoCheckin = {
        init,
        start,
        stop,
        checkNow: checkWiFi,
        getGracePeriodStatus: () => ({
            active: !!wifiLostTime,
            startedAt: wifiLostTime,
            minutesLeft: wifiLostTime ? CONFIG.gracePeriodMinutes - ((Date.now() - wifiLostTime) / 1000 / 60) : 0,
            lastLocation: lastKnownLocation
        }),
        getCurrentLocation: () => currentLocation,
        getLinkedEmployee: getLinkedEmployee,
        isMobilePWA: () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
        shouldRun: shouldRun,
        // 🆕 Test functions - FIXED to modify internal state
        TEST: {
            triggerGracePeriod: async () => {
                console.log('🧪 [TEST] Triggering grace period (5 min)...');
                
                // Get mock employee
                const employee = await getLinkedEmployee();
                if (!employee) {
                    console.error('❌ [TEST] No employee linked!');
                    return;
                }
                
                // Set internal state variables
                wifiLostTime = Date.now();
                lastKnownLocation = { 
                    id: 'test-location', 
                    name: employee.locatie || 'Test Location' 
                };
                
                console.log('✅ [TEST] Grace period started');
                console.log('   wifiLostTime:', new Date(wifiLostTime).toLocaleTimeString());
                console.log('   lastKnownLocation:', lastKnownLocation);
                
                // Show modal
                showGracePeriodModal();
            },
            triggerQuickGracePeriod: async () => {
                console.log('🧪 [TEST] Triggering QUICK grace period (30 sec)...');
                
                // Get mock employee
                const employee = await getLinkedEmployee();
                if (!employee) {
                    console.error('❌ [TEST] No employee linked!');
                    return;
                }
                
                // Start 4.5 minutes ago (30 seconds remaining)
                wifiLostTime = Date.now() - (4.5 * 60 * 1000);
                lastKnownLocation = { 
                    id: 'test-location', 
                    name: employee.locatie || 'Test Location' 
                };
                
                console.log('✅ [TEST] Quick grace period started (30s remaining)');
                console.log('   wifiLostTime:', new Date(wifiLostTime).toLocaleTimeString());
                console.log('   Remaining:', Math.floor((Date.now() - wifiLostTime) / 1000), 'seconds elapsed');
                
                // Show modal
                showGracePeriodModal();
            },
            cancelGracePeriod: () => {
                console.log('🧪 [TEST] Cancelling grace period...');
                wifiLostTime = null;
                lastKnownLocation = null;
                hideGracePeriodModal();
                console.log('✅ [TEST] Grace period cancelled');
            },
            getInternalState: () => ({
                wifiLostTime,
                lastKnownLocation,
                countdownModal: !!countdownModal,
                countdownInterval: !!countdownInterval
            })
        }
    };
    
    // ═══════════════════════════════════════════════════════════════════
    // 🔔 SOUND EFFECTS
    // ═══════════════════════════════════════════════════════════════════
    
    /**
     * Play warning sounds
     */
    function playWarningSound(type) {
        try {
            if (type === 'start') {
                // Initial warning - LOUD double beep (1200Hz)
                playBeep(250, 1200, 0.6);
                setTimeout(() => playBeep(250, 1200, 0.6), 350);
                console.log('🔔 [Sound] START - LOUD double beep');
                
            } else if (type === 'warning') {
                // One minute warning - LOUD triple beep (1400Hz)
                playBeep(300, 1400, 0.7);
                setTimeout(() => playBeep(300, 1400, 0.7), 400);
                setTimeout(() => playBeep(300, 1400, 0.7), 800);
                console.log('🔔 [Sound] WARNING - LOUD triple beep (1 minute left)');
            }
        } catch (error) {
            console.log('🔕 [Sound] Error:', error);
        }
    }
    
    /**
     * Generate beep sound using Web Audio API
     */
    function playBeep(duration = 200, frequency = 800, volume = 0.3) {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = frequency;
            oscillator.type = 'square'; // ✅ Square wave is LOUDER than sine
            
            gainNode.gain.setValueAtTime(volume, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration / 1000);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + duration / 1000);
            
        } catch (error) {
            console.log('🔕 [Beep] Error:', error);
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════
    
    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
