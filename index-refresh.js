/**
 * ═══════════════════════════════════════════════════════════════════
 * BESTANDSNAAM: index-refresh.js
 * LOCATIE:      ROOT (/)
 * VERSIE:       2.0 - Met configureerbare refresh interval
 * ═══════════════════════════════════════════════════════════════════
 * 
 * Auto-refresh systeem voor employee data
 * Bewaart filter state tijdens refresh
 * 
 * ═══════════════════════════════════════════════════════════════════
 */

(function() {
    'use strict';
    
    // ============================================================================
    // ⚙️ CONFIGURATIE - PAS DEZE WAARDEN AAN
    // ============================================================================
    
    /**
     * Auto-refresh interval in SECONDEN
     * 
     * VOORBEELDEN:
     * - 30  = refresh elke 30 seconden (snel)
     * - 60  = refresh elke minuut (standaard)
     * - 120 = refresh elke 2 minuten
     * - 300 = refresh elke 5 minuten (langzaam)
     */
    const AUTO_REFRESH_INTERVAL_SECONDS = 60; // ← PAS DIT AAN!
    
    // ============================================================================
    
    const AUTO_REFRESH_INTERVAL = AUTO_REFRESH_INTERVAL_SECONDS * 1000; // Converteer naar milliseconden
    
    let refreshTimer = null;
    let isRefreshing = false;
    
    /**
     * Start auto-refresh
     */
    function startAutoRefresh() {
        console.log(`🔄 Auto-refresh gestart (interval: ${AUTO_REFRESH_INTERVAL_SECONDS} seconden)`);
        
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        
        refreshTimer = setInterval(() => {
            if (!isRefreshing) {
                refreshData();
            }
        }, AUTO_REFRESH_INTERVAL);
    }
    
    /**
     * Refresh employee data
     */
    async function refreshData() {
        if (isRefreshing) return;
        
        console.log('🔄 Refreshing employee data...');
        isRefreshing = true;
        
        try {
            // Roep fetchEmployees aan als die bestaat
            if (typeof fetchEmployees === 'function') {
                await fetchEmployees();
            } else if (window.labeeApp && typeof window.labeeApp.fetchEmployees === 'function') {
                await window.labeeApp.fetchEmployees();
            } else {
                console.warn('⚠️ fetchEmployees function not found');
            }
            
            // Re-apply filters after refresh
            if (window.FilterPersistence && window.FilterPersistence.hasActive()) {
                console.log('🔍 Re-applying filters after refresh');
                if (typeof applyCurrentFilters === 'function') {
                    setTimeout(() => applyCurrentFilters(), 300);
                }
            }
            
            console.log('✅ Data refresh completed');
            
            // Update timestamp
            updateTimestamp();
            
        } catch (error) {
            console.error('❌ Refresh error:', error);
        } finally {
            isRefreshing = false;
        }
    }
    
    /**
     * Update timestamp in footer
     */
    function updateTimestamp() {
        const timestampEl = document.querySelector('.footer-refresh');
        if (timestampEl) {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            timestampEl.textContent = `Laatst ververst om ${hours}:${minutes}`;
        }
    }
    
    /**
     * Stop auto-refresh
     */
    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
            console.log('🛑 Auto-refresh gestopt');
        }
    }
    
    /**
     * Manual refresh trigger
     */
    function manualRefresh() {
        console.log('🔄 Manual refresh triggered');
        stopAutoRefresh();
        refreshData();
        startAutoRefresh();
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            startAutoRefresh();
            updateTimestamp();
        });
    } else {
        startAutoRefresh();
        updateTimestamp();
    }
    
    // Stop refresh when page is hidden (tab switched)
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
            refreshData(); // Immediate refresh when tab becomes visible
        }
    });
    
    // Export for external use
    window.IndexRefresh = {
        start: startAutoRefresh,
        stop: stopAutoRefresh,
        refresh: manualRefresh,
        getInterval: () => AUTO_REFRESH_INTERVAL_SECONDS
    };
    
    console.log(`✅ index-refresh.js loaded (interval: ${AUTO_REFRESH_INTERVAL_SECONDS}s)`);
    
})();
