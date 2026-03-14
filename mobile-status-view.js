/**
 * ═══════════════════════════════════════════════════════════════════
 * MOBILE STATUS VIEW
 * Toont huidige status + check-out button voor mobiel PWA
 * ═══════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';
    
    let currentEmployee = null;
    let statusCheckInterval = null;
    
    /**
     * Check if mobile PWA
     */
    function isMobilePWA() {
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                           window.navigator.standalone;
        const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        return isStandalone && isMobile;
    }
    
    /**
     * Get linked employee
     */
    async function getLinkedEmployee() {
        const employeeID = localStorage.getItem('linkedEmployeeID');
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
     * Update employee status
     */
    async function updateStatus(status) {
        if (!currentEmployee) return;
        
        try {
            const response = await fetch('/api/update_employee_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    employee_id: currentEmployee.employee_id,
                    status: status,
                    locatie: currentEmployee.locatie || ''
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('✅ Status updated:', status);
                currentEmployee.status = status;
                updateStatusDisplay();
                showToast(`✅ Status: ${status}`);
            } else {
                throw new Error(data.error || 'Update failed');
            }
        } catch (error) {
            console.error('❌ Status update failed:', error);
            showToast('❌ Update mislukt');
        }
    }
    
    /**
     * Show floating status button
     */
    function showStatusButton() {
        if (!currentEmployee) return;
        
        // Remove existing
        const existing = document.getElementById('mobile-status-btn');
        if (existing) existing.remove();
        
        // Create button
        const button = document.createElement('button');
        button.id = 'mobile-status-btn';
        button.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 9999;
            transition: all 0.3s;
        `;
        
        updateButtonStyle(button);
        
        button.addEventListener('click', showStatusMenu);
        document.body.appendChild(button);
    }
    
    /**
     * Update button style based on status
     */
    function updateButtonStyle(button) {
        const status = currentEmployee?.status || 'OUT';
        
        const styles = {
            'IN': { bg: '#48bb78', icon: '✓' },
            'OUT': { bg: '#e53e3e', icon: '✗' },
            'PAUZE': { bg: '#ed8936', icon: '☕' },
            'THUISWERKEN': { bg: '#9f7aea', icon: '🏠' },
            'VAKANTIE': { bg: '#38b2ac', icon: '🏖️' }
        };
        
        const style = styles[status] || styles['OUT'];
        button.style.background = style.bg;
        button.textContent = style.icon;
    }
    
    /**
     * Show status menu
     */
    function showStatusMenu() {
        // Remove existing
        const existing = document.getElementById('status-menu-overlay');
        if (existing) existing.remove();
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.id = 'status-menu-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.7);
            z-index: 99999;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s;
        `;
        
        overlay.innerHTML = `
            <style>
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
            </style>
            <div style="
                background: white;
                border-radius: 16px 16px 0 0;
                width: 100%;
                max-width: 400px;
                padding: 20px;
                animation: slideUp 0.3s;
            ">
                <h3 style="margin: 0 0 15px 0; text-align: center; color: #2d3748;">
                    ${currentEmployee.naam}
                </h3>
                <p style="text-align: center; color: #718096; margin: 0 0 20px 0; font-size: 14px;">
                    ${currentEmployee.functie || 'Medewerker'}
                </p>
                
                <div style="display: grid; gap: 10px;">
                    <button onclick="MobileStatus.setStatus('IN')" style="
                        padding: 15px;
                        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
                        color: white;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                    ">✓ IN Checken</button>
                    
                    <button onclick="MobileStatus.setStatus('OUT')" style="
                        padding: 15px;
                        background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
                        color: white;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                    ">✗ UIT Checken</button>
                    
                    <button onclick="MobileStatus.setStatus('PAUZE')" style="
                        padding: 15px;
                        background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
                        color: white;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                    ">☕ Pauze</button>
                    
                    <button onclick="MobileStatus.closeMenu()" style="
                        padding: 12px;
                        background: #f5f5f5;
                        color: #666;
                        border: none;
                        border-radius: 8px;
                        font-size: 14px;
                        cursor: pointer;
                        margin-top: 10px;
                    ">Annuleren</button>
                </div>
                
                <div style="
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid #e2e8f0;
                    text-align: center;
                ">
                    <button onclick="MobileStatus.unlink()" style="
                        background: none;
                        border: none;
                        color: #e53e3e;
                        font-size: 12px;
                        cursor: pointer;
                        text-decoration: underline;
                    ">🔓 App ontkoppelen</button>
                </div>
            </div>
        `;
        
        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeMenu();
        });
        
        document.body.appendChild(overlay);
    }
    
    /**
     * Close menu
     */
    function closeMenu() {
        const overlay = document.getElementById('status-menu-overlay');
        if (overlay) overlay.remove();
    }
    
    /**
     * Set status
     */
    async function setStatus(status) {
        closeMenu();
        await updateStatus(status);
    }
    
    /**
     * Unlink employee
     */
    function unlink() {
        if (confirm('Weet je zeker dat je deze app wilt ontkoppelen?')) {
            localStorage.removeItem('linkedEmployeeID');
            location.reload();
        }
    }
    
    /**
     * Update status display
     */
    function updateStatusDisplay() {
        const button = document.getElementById('mobile-status-btn');
        if (button) updateButtonStyle(button);
    }
    
    /**
     * Show toast
     */
    function showToast(message) {
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #2d3748;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 99999;
            font-size: 14px;
            font-weight: 600;
        `;
        
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    /**
     * Check status periodically
     */
    async function checkStatus() {
        const employee = await getLinkedEmployee();
        if (employee) {
            currentEmployee = employee;
            updateStatusDisplay();
        }
    }
    
    /**
     * Initialize
     */
    async function init() {
        // Only in mobile PWA
        if (!isMobilePWA()) {
            console.log('ℹ️ [MobileStatus] Not mobile PWA - skipping');
            return;
        }
        
        // Get employee
        const employee = await getLinkedEmployee();
        if (!employee) {
            console.log('ℹ️ [MobileStatus] No employee linked');
            return;
        }
        
        currentEmployee = employee;
        console.log('✅ [MobileStatus] Initialized for:', employee.naam);
        
        // Show floating button
        showStatusButton();
        
        // Check status every 30 seconds
        statusCheckInterval = setInterval(checkStatus, 30000);
    }
    
    // Public API
    window.MobileStatus = {
        init,
        setStatus,
        closeMenu,
        unlink
    };
    
    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
