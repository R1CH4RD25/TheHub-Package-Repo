/**
 * SPA (Single Page Application) Navigation
 * Dynamically load content without full page reloads
 * Keeps header/footer in place for smooth, modern experience
 */

(function() {
    'use strict';

    const SPANav = {
        currentPage: null,
        isLoading: false,

        init() {
            // Only run on pages with section cards
            if (!document.querySelector('.section-card')) {
                console.log('⚠️ SPA Navigation: No section cards found');
                return;
            }

            console.log('🚀 SPA Navigation initialized');

            // Mark body as SPA-loaded to prevent header/footer re-animations
            document.body.classList.add('spa-loaded');

            // Intercept section card clicks
            this.attachCardListeners();

            // Handle browser back/forward buttons
            window.addEventListener('popstate', (e) => {
                if (e.state && e.state.url) {
                    this.loadContent(e.state.url, false); // Don't push state again
                }
            });

            // Mark initial page in history
            history.replaceState({ url: window.location.href }, '', window.location.href);
        },

        attachCardListeners() {
            document.querySelectorAll('.section-card').forEach(card => {
                card.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = card.getAttribute('href');

                    if (url && !this.isLoading) {
                        this.loadContent(url, true);
                    }
                });
            });
        },

        async loadContent(url, pushState = true) {
            if (this.isLoading) return;

            this.isLoading = true;
            const mainContent = document.querySelector('.main-content') || document.querySelector('.hub-content');

            // Show loading state
            if (typeof TheHub !== 'undefined' && TheHub.showLoading) {
                TheHub.showLoading('Loading...');
            }

            // Add transition class
            if (mainContent) {
                mainContent.style.opacity = '0';
                mainContent.style.transform = 'translateY(20px)';
                mainContent.style.transition = 'all 0.3s ease';
            }

            try {
                // Fetch new content
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-SPA-Request': 'true'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const html = await response.text();

                // Parse response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Extract new content
                const newContent = doc.querySelector('.main-content') || doc.querySelector('.hub-content');

                if (newContent && mainContent) {
                    // Wait for fade out
                    await new Promise(resolve => setTimeout(resolve, 300));

                    // Replace content
                    mainContent.innerHTML = newContent.innerHTML;

                    // Update page title
                    const newTitle = doc.querySelector('title');
                    if (newTitle) {
                        document.title = newTitle.textContent;
                    }

                    // Fade in new content
                    setTimeout(() => {
                        mainContent.style.opacity = '1';
                        mainContent.style.transform = 'translateY(0)';
                    }, 50);

                    // Update URL if needed
                    if (pushState) {
                        history.pushState({ url: url }, '', url);
                    }

                    // Re-initialize any scripts needed
                    this.reinitializeScripts();

                    // Scroll to top smoothly
                    window.scrollTo({ top: 0, behavior: 'smooth' });

                    // Success notification
                    if (typeof Notyf !== 'undefined') {
                        const notyf = new Notyf({
                            duration: 2000,
                            position: { x: 'right', y: 'top' }
                        });
                        notyf.success('Page loaded!');
                    }

                } else {
                    // Fallback to full page load
                    window.location.href = url;
                }

            } catch (error) {
                console.error('SPA navigation error:', error);

                // Show error and fallback to full page load
                if (typeof Notyf !== 'undefined') {
                    const notyf = new Notyf();
                    notyf.error('Loading failed, redirecting...');
                }

                setTimeout(() => {
                    window.location.href = url;
                }, 1000);
            } finally {
                this.isLoading = false;

                // Hide loading
                if (typeof TheHub !== 'undefined' && TheHub.closeLoading) {
                    TheHub.closeLoading();
                }
            }
        },

        reinitializeScripts() {
            // Re-initialize any dynamic scripts here
            // For example: AOS, Vanilla Tilt, etc.

            if (typeof AOS !== 'undefined') {
                AOS.refresh();
            }

            if (typeof VanillaTilt !== 'undefined') {
                const tiltElements = document.querySelectorAll('[data-tilt]');
                VanillaTilt.init(tiltElements);
            }

            // Re-attach event listeners if needed
            this.attachCardListeners();
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SPANav.init());
    } else {
        SPANav.init();
    }

    // Expose globally
    window.SPANav = SPANav;

})();
