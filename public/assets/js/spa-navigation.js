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
            const cards = document.querySelectorAll('.section-card');
            console.log(`📌 SPA: Attaching listeners to ${cards.length} section cards`);

            cards.forEach(card => {
                card.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = card.getAttribute('href');
                    console.log(`🖱️ SPA: Card clicked - URL: ${url}, isLoading: ${this.isLoading}`);

                    if (url && !this.isLoading) {
                        this.loadContent(url, true);
                    }
                });
            });
        },

        async loadContent(url, pushState = true) {
            console.log(`🚀 SPA: loadContent called - URL: ${url}`);

            if (this.isLoading) {
                console.log('⏸️ SPA: Already loading, ignoring request');
                return;
            }

            this.isLoading = true;
            console.log('🔄 SPA: Setting isLoading = true');

            const mainContent = document.querySelector('.main-content') || document.querySelector('.hub-content');
            console.log('📍 SPA: Main content element:', mainContent ? 'Found' : 'NOT FOUND');

            // Show loading state
            console.log('⏳ SPA: Checking for TheHub.showLoading...');
            console.log('   - TheHub exists?', typeof TheHub !== 'undefined');
            console.log('   - TheHub.showLoading exists?', typeof TheHub !== 'undefined' && typeof TheHub.showLoading === 'function');

            if (typeof TheHub !== 'undefined' && TheHub.showLoading) {
                console.log('✅ SPA: Calling TheHub.showLoading()');
                TheHub.showLoading('Loading...');
            } else {
                console.log('❌ SPA: TheHub.showLoading NOT available');
            }

            // Add transition class
            if (mainContent) {
                mainContent.style.opacity = '0';
                mainContent.style.transform = 'translateY(20px)';
                mainContent.style.transition = 'all 0.3s ease';
            }

            try {
                // Fetch new content
                console.log('📡 SPA: Fetching URL:', url);
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-SPA-Request': 'true'
                    }
                });
                console.log(`📨 SPA: Response status: ${response.status} ${response.statusText}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const html = await response.text();
                console.log(`📄 SPA: Received HTML (${html.length} bytes)`);

                // Parse response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                console.log('🔍 SPA: Parsed HTML document');

                // Extract new content
                const newContent = doc.querySelector('.main-content') || doc.querySelector('.hub-content');
                console.log('🎯 SPA: New content element:', newContent ? 'Found' : 'NOT FOUND');

                if (newContent && mainContent) {
                    console.log('✅ SPA: Both elements found, replacing content...');

                    // Wait for fade out
                    await new Promise(resolve => setTimeout(resolve, 300));

                    // Replace content
                    mainContent.innerHTML = newContent.innerHTML;
                    console.log('✅ SPA: Content replaced');

                    // Update page title
                    const newTitle = doc.querySelector('title');
                    if (newTitle) {
                        document.title = newTitle.textContent;
                        console.log('✅ SPA: Title updated:', newTitle.textContent);
                    }

                    // Fade in new content
                    setTimeout(() => {
                        mainContent.style.opacity = '1';
                        mainContent.style.transform = 'translateY(0)';
                        console.log('✅ SPA: Content faded in');
                    }, 50);

                    // Update URL if needed
                    if (pushState) {
                        history.pushState({ url: url }, '', url);
                        console.log('✅ SPA: URL updated:', url);
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
                        console.log('✅ SPA: Success notification shown');
                    }

                } else {
                    console.log('⚠️ SPA: Elements not found, falling back to full page load');
                    console.log('   - newContent:', newContent ? 'exists' : 'null');
                    console.log('   - mainContent:', mainContent ? 'exists' : 'null');
                    // Fallback to full page load
                    window.location.href = url;
                }

            } catch (error) {
                console.error('❌ SPA navigation error:', error);

                // Show error and fallback to full page load
                if (typeof Notyf !== 'undefined') {
                    const notyf = new Notyf();
                    notyf.error('Loading failed, redirecting...');
                }

                setTimeout(() => {
                    window.location.href = url;
                }, 1000);
            } finally {
                console.log('🏁 SPA: Finally block reached');
                console.log('   - isLoading:', this.isLoading, '→ false');
                this.isLoading = false;

                // Hide loading
                console.log('🔍 SPA: Checking for TheHub.closeLoading...');
                console.log('   - TheHub exists?', typeof TheHub !== 'undefined');
                console.log('   - TheHub.closeLoading exists?', typeof TheHub !== 'undefined' && typeof TheHub.closeLoading === 'function');

                if (typeof TheHub !== 'undefined' && TheHub.closeLoading) {
                    console.log('✅ SPA: Calling TheHub.closeLoading()');
                    TheHub.closeLoading();
                } else {
                    console.log('❌ SPA: TheHub.closeLoading NOT available');
                    console.log('   - TheHub object:', TheHub);
                }
            }
        },

        reinitializeScripts() {
            console.log('🔄 SPA: Reinitializing scripts...');

            // Re-initialize any dynamic scripts here
            // For example: AOS, Vanilla Tilt, etc.

            if (typeof AOS !== 'undefined') {
                AOS.refresh();
                console.log('✅ SPA: AOS refreshed');
            }

            if (typeof VanillaTilt !== 'undefined') {
                const tiltElements = document.querySelectorAll('[data-tilt]');
                VanillaTilt.init(tiltElements);
                console.log('✅ SPA: VanillaTilt initialized');
            }

            // Re-attach event listeners if needed
            this.attachCardListeners();
            console.log('✅ SPA: Card listeners reattached');
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
