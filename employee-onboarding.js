/**
 * ═══════════════════════════════════════════════════════════════════
 * EMPLOYEE ONBOARDING v2.1
 * Werkt in: Standalone PWA + Mobiel Browser (voor testing)
 * ═══════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';
    
    const STORAGE_KEY = 'linkedEmployeeID';
    
    /**
     * Check if employee is already linked
     */
    function isEmployeeLinked() {
        return localStorage.getItem(STORAGE_KEY) !== null;
    }
    
    /**
     * Get linked employee ID
     */
    function getLinkedEmployeeID() {
        return localStorage.getItem(STORAGE_KEY);
    }
    
    /**
     * Get linked employee data
     */
    async function getLinkedEmployee() {
        const employeeID = getLinkedEmployeeID();
        if (!employeeID) return null;
        
        try {
            const response = await fetch(`/api/get_employee.php?id=${employeeID}`);
            const data = await response.json();
            return data.success ? data.employee : null;
        } catch (error) {
            console.error('Error fetching employee:', error);
            return null;
        }
    }
    
    /**
     * Check if should show onboarding
     */
    function shouldShowOnboarding() {
        // Already linked? Skip
        if (isEmployeeLinked()) {
            return false;
        }
        
        // Check if dismissed recently
        const dismissed = localStorage.getItem('onboardingDismissed');
        if (dismissed) {
            const dismissedTime = parseInt(dismissed);
            const hoursSince = (Date.now() - dismissedTime) / 1000 / 60 / 60;
            
            // Don't show again for 24 hours if dismissed
            if (hoursSince < 24) {
                console.log('ℹ️ [Onboarding] Dismissed recently, showing again in', Math.round(24 - hoursSince), 'hours');
                return false;
            }
        }
        
        // RELAXED CHECK: Show in standalone PWA OR mobile browser
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                           window.navigator.standalone ||
                           document.referrer.includes('android-app://');
        
        const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        
        // Show if: Mobile (standalone OR browser) AND not desktop
        const isSmallScreen = window.innerWidth <= 768;
        
        if (isStandalone && isMobile) {
            // Standalone PWA - always show if not linked
            return true;
        }
        
        if (isMobile && isSmallScreen) {
            // Mobile browser - show if not linked
            console.log('ℹ️ [Onboarding] Mobile browser detected - showing onboarding');
            return true;
        }
        
        // Desktop - don't show
        console.log('ℹ️ [Onboarding] Desktop detected - skipping');
        return false;
    }
    
    /**
     * Show onboarding screen
     */
    async function showOnboarding() {
        // Fetch all employees
        let employees = [];
        try {
            const response = await fetch('/api/get_all_employees.php');
            const data = await response.json();
            employees = data.employees || [];
        } catch (error) {
            console.error('Error loading employees:', error);
        }
        
        if (employees.length === 0) {
            console.error('❌ [Onboarding] No employees found!');
            alert('Geen medewerkers gevonden in database. Neem contact op met beheerder.');
            return;
        }
        
        // Create onboarding overlay
        const overlay = document.createElement('div');
        overlay.id = 'employee-onboarding';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.9);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        `;
        
        overlay.innerHTML = `
            <div style="
                background: white;
                border-radius: 16px;
                max-width: 500px;
                width: 100%;
                padding: 30px;
                max-height: 90vh;
                overflow-y: auto;
            ">
                <h1 style="
                    margin: 0 0 10px 0;
                    font-size: 28px;
                    color: #2c3e50;
                    text-align: center;
                ">👋 Welkom!</h1>
                
                <p style="
                    color: #666;
                    text-align: center;
                    margin: 0 0 30px 0;
                    font-size: 16px;
                    line-height: 1.5;
                ">
                    Koppel deze app aan jouw medewerker profiel voor automatische WiFi check-in
                </p>
                
                <div id="onboarding-method-choice" style="margin-bottom: 20px;">
                    <button onclick="window.EmployeeOnboarding.showEmployeeList()" style="
                        width: 100%;
                        padding: 15px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        margin-bottom: 10px;
                    ">📋 Kies uit lijst</button>
                    
                    <button onclick="window.EmployeeOnboarding.showIDInput()" style="
                        width: 100%;
                        padding: 15px;
                        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                        color: white;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        margin-bottom: 10px;
                    ">🔢 Vul Employee ID in</button>
                    
                    <button onclick="window.EmployeeOnboarding.dismissOnboarding()" style="
                        width: 100%;
                        padding: 12px;
                        background: #f5f5f5;
                        color: #666;
                        border: none;
                        border-radius: 8px;
                        font-size: 14px;
                        cursor: pointer;
                    ">Later koppelen</button>
                </div>
                
                <div id="onboarding-content"></div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        // Store employees for later use
        window._onboardingEmployees = employees;
    }
    
    /**
     * Show employee list
     */
    function showEmployeeList() {
        const content = document.getElementById('onboarding-content');
        const employees = window._onboardingEmployees || [];
        
        if (employees.length === 0) {
            content.innerHTML = `
                <div style="text-align: center; padding: 20px; color: #999;">
                    Geen medewerkers gevonden
                </div>
            `;
            return;
        }
        
        // Sort by name
        employees.sort((a, b) => a.naam.localeCompare(b.naam));
        
        // Search input
        let html = `
            <input 
                type="text" 
                id="employee-search"
                placeholder="🔍 Zoek medewerker..."
                style="
                    width: 100%;
                    padding: 12px;
                    border: 2px solid #ddd;
                    border-radius: 8px;
                    font-size: 16px;
                    margin-bottom: 15px;
                    box-sizing: border-box;
                "
            >
            <div id="employee-list-container" style="max-height: 400px; overflow-y: auto;">
        `;
        
        // Employee cards
        employees.forEach(emp => {
            html += `
                <div class="employee-option" data-name="${emp.naam.toLowerCase()}" style="
                    padding: 15px;
                    border: 2px solid #eee;
                    border-radius: 8px;
                    margin-bottom: 10px;
                    cursor: pointer;
                    transition: all 0.2s;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                " onclick="window.EmployeeOnboarding.linkEmployee('${emp.employee_id}', '${emp.naam.replace(/'/g, "\\'")}')"
                onmouseover="this.style.borderColor='#667eea'; this.style.background='#f8f9ff'"
                onmouseout="this.style.borderColor='#eee'; this.style.background='white'">
                    ${emp.foto_url ? 
                        `<img src="${emp.foto_url}" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">` :
                        `<div style="width: 50px; height: 50px; border-radius: 50%; background: #667eea; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: bold;">${emp.naam.charAt(0)}</div>`
                    }
                    <div style="flex: 1;">
                        <div style="font-weight: 600; color: #2c3e50;">${emp.naam}</div>
                        ${emp.functie ? `<div style="font-size: 14px; color: #999;">${emp.functie}</div>` : ''}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        content.innerHTML = html;
        
        // Add search functionality
        document.getElementById('employee-search').addEventListener('input', function(e) {
            const search = e.target.value.toLowerCase();
            const options = document.querySelectorAll('.employee-option');
            
            options.forEach(option => {
                const name = option.getAttribute('data-name');
                if (name.includes(search)) {
                    option.style.display = 'flex';
                } else {
                    option.style.display = 'none';
                }
            });
        });
    }
    
    /**
     * Show ID input
     */
    function showIDInput() {
        const content = document.getElementById('onboarding-content');
        
        content.innerHTML = `
            <div style="text-align: center;">
                <p style="color: #666; margin-bottom: 20px;">
                    Vul je Employee ID in (staat op je badge of vraag aan leidinggevende)
                </p>
                
                <input 
                    type="text" 
                    id="employee-id-input"
                    placeholder="Bijv: EMP12345"
                    style="
                        width: 100%;
                        padding: 15px;
                        border: 2px solid #ddd;
                        border-radius: 8px;
                        font-size: 18px;
                        text-align: center;
                        margin-bottom: 20px;
                        box-sizing: border-box;
                    "
                >
                
                <button onclick="window.EmployeeOnboarding.linkEmployeeByID()" style="
                    width: 100%;
                    padding: 15px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                ">✓ Koppelen</button>
                
                <button onclick="window.EmployeeOnboarding.showEmployeeList()" style="
                    width: 100%;
                    padding: 12px;
                    background: transparent;
                    color: #667eea;
                    border: none;
                    font-size: 14px;
                    cursor: pointer;
                    margin-top: 10px;
                ">← Terug naar lijst</button>
            </div>
        `;
        
        // Focus input
        setTimeout(() => {
            document.getElementById('employee-id-input').focus();
        }, 100);
    }
    
    /**
     * Link employee by ID input
     */
    async function linkEmployeeByID() {
        const input = document.getElementById('employee-id-input');
        const employeeID = input.value.trim();
        
        if (!employeeID) {
            alert('Vul een Employee ID in');
            return;
        }
        
        // Verify employee exists
        try {
            const response = await fetch(`/api/get_employee.php?id=${encodeURIComponent(employeeID)}`);
            const data = await response.json();
            
            if (data.success && data.employee) {
                linkEmployee(employeeID, data.employee.naam);
            } else {
                alert('Employee ID niet gevonden. Controleer of het correct is.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Fout bij controleren Employee ID');
        }
    }
    
    /**
     * Link employee to this device
     */
    function linkEmployee(employeeID, employeeName) {
        // Save to localStorage
        localStorage.setItem(STORAGE_KEY, employeeID);
        
        // Show success
        const overlay = document.getElementById('employee-onboarding');
        overlay.innerHTML = `
            <div style="
                background: white;
                border-radius: 16px;
                max-width: 400px;
                width: 100%;
                padding: 40px;
                text-align: center;
            ">
                <div style="font-size: 60px; margin-bottom: 20px;">✅</div>
                <h2 style="margin: 0 0 15px 0; color: #2c3e50;">Gekoppeld!</h2>
                <p style="color: #666; font-size: 16px; margin: 0 0 30px 0;">
                    Deze app is nu gekoppeld aan:<br>
                    <strong style="color: #2c3e50; font-size: 20px;">${employeeName}</strong>
                </p>
                <p style="color: #999; font-size: 14px;">
                    De app wordt nu opnieuw geladen...
                </p>
            </div>
        `;
        
        // Reload after 2 seconds
        setTimeout(() => {
            location.reload();
        }, 2000);
    }
    
    /**
     * Dismiss onboarding (show again tomorrow)
     */
    function dismissOnboarding() {
        localStorage.setItem('onboardingDismissed', Date.now().toString());
        
        const overlay = document.getElementById('employee-onboarding');
        if (overlay) overlay.remove();
        
        console.log('ℹ️ [Onboarding] Dismissed - will show again in 24 hours');
    }
    
    /**
     * Unlink employee
     */
    function unlinkEmployee() {
        if (confirm('Weet je zeker dat je deze app wilt ontkoppelen?')) {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem('onboardingDismissed');
            location.reload();
        }
    }
    
    /**
     * Initialize
     */
    function init() {
        // Wait for DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // Check if should show onboarding
        if (shouldShowOnboarding()) {
            // Wait a moment for page to load
            setTimeout(showOnboarding, 1000);
        } else {
            const linkedID = getLinkedEmployeeID();
            if (linkedID) {
                console.log('✅ [Onboarding] Employee linked:', linkedID);
            } else {
                console.log('ℹ️ [Onboarding] Skipped (not mobile or dismissed)');
            }
        }
    }
    
    // Public API
    window.EmployeeOnboarding = {
        isLinked: isEmployeeLinked,
        getEmployeeID: getLinkedEmployeeID,
        getEmployee: getLinkedEmployee,
        showOnboarding: showOnboarding,
        showEmployeeList: showEmployeeList,
        showIDInput: showIDInput,
        linkEmployee: linkEmployee,
        linkEmployeeByID: linkEmployeeByID,
        dismissOnboarding: dismissOnboarding,
        unlink: unlinkEmployee,
        init: init
    };
    
    // Auto-init
    init();
    
})();
