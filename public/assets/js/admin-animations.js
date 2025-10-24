/**
 * Admin Dashboard Animation Controller
 * Triggers animations for tables, tabs, and UI elements
 */

(function() {
    'use strict';

    const AdminAnimations = {
        
        observerTimeout: null,
        
        // Animate table rows with accordion down effect
        animateTableRows(tableSelector, delay = 60) {
            const table = document.querySelector(tableSelector);
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                // Remove any existing animation class
                row.classList.remove('animate-in');
                // Force reflow
                void row.offsetWidth;
                // Add animation class with delay
                setTimeout(() => {
                    row.classList.add('animate-in');
                }, index * delay);
            });
        },
        
        // Animate all tables on the page
        animateAllTables(delay = 60) {
            const tables = document.querySelectorAll('.data-table');
            tables.forEach(table => {
                const rows = table.querySelectorAll('tbody tr');
                let animationIndex = 0; // Track which rows need animation
                
                rows.forEach((row) => {
                    // Skip if already animated (prevents disappearing/re-animation)
                    if (row.classList.contains('animate-in')) {
                        return;
                    }
                    
                    // Add animation class with staggered delay
                    setTimeout(() => {
                        row.classList.add('animate-in');
                    }, animationIndex * delay);
                    
                    animationIndex++; // Increment for next unanimate row
                });
            });
        },
        
        // Watch for table content changes (for dynamically loaded tables)
        observeTableChanges() {
            const tableContainers = document.querySelectorAll('.data-table-container');
            
            tableContainers.forEach(container => {
                // Track if this container has been animated
                let hasBeenAnimated = false;
                
                const observer = new MutationObserver((mutations) => {
                    // Ignore mutations if we've already animated this container
                    if (hasBeenAnimated) {
                        return;
                    }
                    
                    // Debounce: clear previous timeout
                    if (this.observerTimeout) {
                        clearTimeout(this.observerTimeout);
                    }
                    
                    // Only animate after changes have settled (500ms for large tables)
                    this.observerTimeout = setTimeout(() => {
                        const table = container.querySelector('.data-table');
                        if (table && table.querySelectorAll('tbody tr').length > 0) {
                            this.animateAllTables(60);
                            hasBeenAnimated = true; // Mark as animated to prevent re-animation
                        }
                    }, 500);
                });
                
                observer.observe(container, {
                    childList: true,
                    subtree: true
                });
            });
        },
        
        // Animate cards with flip-zoom effect
        animateCards(containerSelector, delay = 100) {
            const container = document.querySelector(containerSelector);
            if (!container) return;
            
            const cards = container.querySelectorAll('.admin-card, .section-card, .package-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('animate-in');
                }, index * delay);
            });
        },
        
        // Animate sidebar menu (now using pure CSS with nth-child delays)
        animateSidebar() {
            const menuItems = document.querySelectorAll('.admin-menu li');
            menuItems.forEach(item => {
                item.classList.add('animate-in');
            });
        },
        
        // Animate subtabs with slide-up stack
        animateSubtabs(delay = 50) {
            const subtabs = document.querySelectorAll('.subtab-btn');
            subtabs.forEach((tab, index) => {
                setTimeout(() => {
                    tab.classList.add('animate-in');
                }, index * delay);
            });
        },
        
        // Animate stat cards with bounce-up
        animateStats(delay = 100) {
            const stats = document.querySelectorAll('.stat-card');
            stats.forEach((stat, index) => {
                setTimeout(() => {
                    stat.classList.add('animate-in');
                }, index * delay);
            });
        },
        
        // Re-animate tables when tab changes (called externally, not hooked to clicks)
        onTabChange(tabId) {
            // Clear any pending animations
            if (this.animationTimeout) {
                clearTimeout(this.animationTimeout);
            }
            
            // Wait for content to be visible (longer delay for data fetching)
            this.animationTimeout = setTimeout(() => {
                const activeTab = document.querySelector(`#tab-${tabId}`);
                if (!activeTab) return;
                
                // Animate tables in this tab
                const tables = activeTab.querySelectorAll('.data-table');
                tables.forEach(table => {
                    const rows = table.querySelectorAll('tbody tr');
                    
                    // Skip if no rows (data still loading)
                    if (rows.length === 0) return;
                    
                    let animationIndex = 0; // Track which rows need animation
                    
                    // Only animate rows that haven't been animated yet
                    rows.forEach((row) => {
                        if (!row.classList.contains('animate-in')) {
                            setTimeout(() => {
                                row.classList.add('animate-in');
                            }, animationIndex * 60);
                            animationIndex++; // Increment only for rows being animated
                        }
                    });
                });
                
                // Animate cards (only new ones)
                const cards = activeTab.querySelectorAll('.admin-card, .section-card, .package-card');
                let cardIndex = 0;
                cards.forEach((card) => {
                    if (!card.classList.contains('animate-in')) {
                        setTimeout(() => {
                            card.classList.add('animate-in');
                        }, cardIndex * 100);
                        cardIndex++;
                    }
                });
                
                // Animate subtabs (only new ones)
                const subtabs = activeTab.querySelectorAll('.subtab-btn');
                let subtabIndex = 0;
                subtabs.forEach((tab) => {
                    if (!tab.classList.contains('animate-in')) {
                        setTimeout(() => {
                            tab.classList.add('animate-in');
                        }, subtabIndex * 50);
                        subtabIndex++;
                    }
                });
                
            }, 300);
        },
        
        // Initialize all animations
        init() {
            console.log('🎨 Admin Animations initialized');
            
            // Animate sidebar menu on load
            this.animateSidebar();
            
            // Animate initial subtabs
            setTimeout(() => {
                this.animateSubtabs();
            }, 300);
            
            // Animate initial tables
            setTimeout(() => {
                this.animateAllTables();
            }, 400);
            
            // Watch for dynamic table changes
            this.observeTableChanges();
            
            // Animate initial cards
            setTimeout(() => {
                this.animateCards('.admin-content', 100);
            }, 500);
            
            // Animate initial stats
            setTimeout(() => {
                this.animateStats();
            }, 600);
            
            // NOTE: Tab clicks are handled in admin.js - DO NOT add listeners here
            // to avoid double-loading tabs. admin.js will call onTabChange() when needed.
            
            // Hook into subtab change events for animation only
            document.querySelectorAll('[data-subtab]').forEach(subtab => {
                subtab.addEventListener('click', () => {
                    setTimeout(() => {
                        this.animateAllTables(60);
                    }, 150);
                });
            });
        },
        
        // Trigger animation for dynamically loaded content
        refresh() {
            this.animateAllTables(60);
            this.animateCards('.admin-content', 100);
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => AdminAnimations.init());
    } else {
        AdminAnimations.init();
    }
    
    // Expose globally for external triggers
    window.AdminAnimations = AdminAnimations;
    
})();
