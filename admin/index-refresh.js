/**
 * index-refresh.js
 * Smart auto-refresh for index.php (only refreshes data, not whole page)
 * 
 * FIXED VERSION - Now correctly waits for window.labeeApp
 */

(function() {
    'use strict';
    
    const REFRESH_INTERVAL = 30000; // 30 seconds
    const MAX_INIT_RETRIES = 10; // Max 10 pogingen (10 seconden)
    let autoRefreshInterval = null;
    let isRefreshing = false;
    let initRetries = 0;
    
    console.log('🔄 Smart auto-refresh initializing...');
    
    /**
     * Refresh employee data via API call
     */
    function refreshEmployeeData() {
        if (isRefreshing) {
            console.log('⏳ Refresh already in progress, skipping...');
            return;
        }
        
        if (!window.labeeApp || typeof window.labeeApp.fetchEmployees !== 'function') {
            console.error('❌ labeeApp not available for refresh!');
            return;
        }
        
        isRefreshing = true;
        console.log('🔄 Refreshing employee data...');
        
        try {
            // Call the app's fetchEmployees function
            window.labeeApp.fetchEmployees()
                .then(() => {
                    console.log('✅ Data refresh completed');
                    updateRefreshTimestamp();
                    isRefreshing = false;
                })
                .catch(err => {
                    console.error('❌ Refresh error:', err);
                    isRefreshing = false;
                });
        } catch (error) {
            console.error('❌ Error calling fetchEmployees:', error);
            isRefreshing = false;
        }
    }
    
    /**
     * Update the "last refreshed" timestamp in footer
     */
    function updateRefreshTimestamp() {
        const el = document.querySelector('.footer-refresh');
        if (el) {
            const now = new Date();
            el.textContent = 'Laatst ververst om ' + now.toLocaleTimeString('nl-NL', {
                hour: '2-digit',
                minute: '2-digit'
            });
            console.log('⏰ Timestamp updated:', el.textContent);
        } else {
            console.warn('⚠️ .footer-refresh element not found');
        }
    }
    
    /**
     * Start auto-refresh
     */
    function startAutoRefresh() {
        if (autoRefreshInterval) {
            console.log('⚠️ Auto-refresh already running');
            return;
        }
        
        console.log('✅ Auto-refresh started (every 30 seconds)');
        console.log('📊 labeeApp methods available:', Object.keys(window.labeeApp || {}));
        
        // Start interval
        autoRefreshInterval = setInterval(() => {
            refreshEmployeeData();
        }, REFRESH_INTERVAL);
        
        // Do an immediate first refresh after 5 seconds
        setTimeout(() => {
            console.log('🚀 Initial refresh after 5 seconds...');
            refreshEmployeeData();
        }, 5000);
    }
    
    /**
     * Initialize when labeeApp is ready
     */
    function init() {
        initRetries++;
        
        if (window.labeeApp && typeof window.labeeApp.fetchEmployees === 'function') {
            console.log('✅ labeeApp found after', initRetries, 'attempt(s)');
            console.log('   - fetchEmployees:', typeof window.labeeApp.fetchEmployees);
            console.log('   - renderEmployees:', typeof window.labeeApp.renderEmployees);
            console.log('   - updateDashboard:', typeof window.labeeApp.updateDashboard);
            startAutoRefresh();
            return;
        }
        
        if (initRetries >= MAX_INIT_RETRIES) {
            console.error('❌ labeeApp NOT found after', initRetries, 'attempts!');
            console.error('   window.labeeApp =', window.labeeApp);
            console.error('   Check if app.js loaded correctly');
            console.error('   Auto-refresh DISABLED');
            return;
        }
        
        console.log('⏳ Waiting for labeeApp... (attempt', initRetries + '/' + MAX_INIT_RETRIES + ')');
        setTimeout(init, 1000);
    }
    
    /**
     * Manual refresh trigger (optional - voor debugging)
     */
    window.manualRefresh = function() {
        console.log('🔄 Manual refresh triggered');
        refreshEmployeeData();
    };
    
    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Log when leaving page
    window.addEventListener('beforeunload', () => {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            console.log('🛑 Auto-refresh stopped (page unload)');
        }
    });
    
})();