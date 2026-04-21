/**
 * ============================================================
 * PEOPLE DISPLAY - INSTALLER JAVASCRIPT
 * ============================================================
 */

(function() {
    'use strict';
    
    // Utility functions
    const Installer = {
        
        /**
         * Show loading state on button
         */
        setButtonLoading: function(btn, loading, text = '') {
            if (loading) {
                btn.disabled = true;
                btn.dataset.originalText = btn.textContent;
                btn.innerHTML = '<span class="loading-text"><span class="spinner"></span> ' + (text || 'Loading...') + '</span>';
            } else {
                btn.disabled = false;
                btn.textContent = btn.dataset.originalText || text || 'Continue';
            }
        },
        
        /**
         * Show message
         */
        showMessage: function(container, type, message, icon = '') {
            if (!container) return;
            
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            container.className = 'message message-' + type;
            container.innerHTML = `
                <span class="message-icon">${icon || icons[type]}</span>
                <div>${message}</div>
            `;
            container.classList.remove('hidden');
        },
        
        /**
         * Hide message
         */
        hideMessage: function(container) {
            if (container) {
                container.classList.add('hidden');
            }
        },
        
        /**
         * Validate form
         */
        validateForm: function(form) {
            if (!form) return false;
            
            // Check HTML5 validation
            if (!form.checkValidity()) {
                form.reportValidity();
                return false;
            }
            
            return true;
        },
        
        /**
         * AJAX request helper
         */
        ajax: async function(url, formData) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                return result;
                
            } catch (error) {
                throw new Error(`Request failed: ${error.message}`);
            }
        }
    };
    
    // Make globally available
    window.Installer = Installer;
    
    // Page-specific initialization
    document.addEventListener('DOMContentLoaded', function() {
        
        // Add form validation feedback
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
        
        // Smooth scroll to messages
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    const target = mutation.target;
                    if (target.classList.contains('message') && !target.classList.contains('hidden')) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
            });
        });
        
        document.querySelectorAll('[class*="message"]').forEach(el => {
            observer.observe(el, { attributes: true });
        });
        
        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter to proceed to next step
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const nextBtn = document.getElementById('next-btn');
                if (nextBtn && !nextBtn.disabled) {
                    nextBtn.click();
                }
            }
        });
        
        console.log('🚀 People Display Installer initialized');
    });
    
})();
