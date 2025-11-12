/**
 * Modal Renderer Utility - MicroModal Edition
 *
 * Provides a consistent way to render dynamic modal content across The Hub.
 * All modals should be defined in public/admin/partials/modals.php and populated via this utility.
 *
 * Migration from Bootstrap 5 to MicroModal.js:
 * - Lightweight: 1.9KB gzipped vs Bootstrap's 80KB+ modal system
 * - No CSS conflicts: Full control over modal styling
 * - Accessibility-first: WAI-ARIA compliant by default
 * - Simple API: Same interface as before, but cleaner implementation
 *
 * Philosophy:
 * - Modals are pre-defined templates in modals.php (structure)
 * - JavaScript fills them with dynamic content (data)
 * - MicroModal handles display/hide/accessibility (behavior)
 *
 * Usage:
 * ```javascript
 * ModalRenderer.show('packageValidationModal', {
 *     title: '<i class="bi bi-check"></i> Package Validated',
 *     body: '<div>Your content here</div>',
 *     footer: '<button class="btn btn-primary">Action</button>'
 * });
 * ```
 */

const ModalRenderer = {
    /**
     * Initialize MicroModal with default config
     * Called automatically when first modal is shown
     */
    _initialized: false,

    _init() {
        if (this._initialized) return;

        // Initialize MicroModal with global config
        if (typeof MicroModal !== 'undefined') {
            MicroModal.init({
                disableScroll: true,          // Prevent body scroll when modal open
                disableFocus: false,          // Keep focus management
                awaitCloseAnimation: true,    // Wait for CSS animations
                debugMode: false              // Set to true for development
            });
            this._initialized = true;
        } else {
            console.error('❌ MicroModal not loaded! Include it in Layout.php');
        }
    },

    /**
     * Show a modal with dynamic content
     * @param {string} modalId - ID of the modal element (without #)
     * @param {object} options - Content and configuration options
     * @param {string} options.title - HTML content for modal title
     * @param {string} options.body - HTML content for modal body
     * @param {string} [options.footer] - HTML content for modal footer (optional)
     * @param {boolean} [options.disableScroll] - Prevent body scroll (default: true)
     * @param {boolean} [options.disableFocus] - Disable focus trap (default: false)
     * @param {boolean} [options.awaitCloseAnimation] - Wait for close animation (default: true)
     * @param {function} [options.onShow] - Callback when modal is shown
     * @param {function} [options.onClose] - Callback when modal is closed
     */
    show(modalId, options = {}) {
        this._init();

        const modalElement = document.getElementById(modalId);

        if (!modalElement) {
            console.error(`❌ Modal not found: #${modalId}`);
            console.warn('💡 Make sure the modal is defined in public/admin/partials/modals.php');
            return null;
        }

                // Populate title (optional)
        if (options.title) {
            // Try MicroModal structure first, fall back to Bootstrap
            const titleElement = modalElement.querySelector('.modal-title, .modal__title');
            if (titleElement) {
                titleElement.innerHTML = options.title;
            }
        }

        // Populate body
        if (options.body) {
            // Try MicroModal structure first, fall back to Bootstrap
            const bodyElement = modalElement.querySelector('.modal__content, .modal-body');
            if (bodyElement) {
                bodyElement.innerHTML = options.body;
            }
        }

        // Populate footer (optional)
        if (options.footer) {
            // Try MicroModal structure first, fall back to Bootstrap
            const footerElement = modalElement.querySelector('.modal__footer, .modal-footer');
            if (footerElement) {
                footerElement.innerHTML = options.footer;
            }
        }

        // MicroModal configuration for this specific modal
        const config = {
            disableScroll: options.disableScroll !== false,
            disableFocus: options.disableFocus || false,
            awaitCloseAnimation: options.awaitCloseAnimation !== false,
            onShow: (modal) => {
                console.log(`✅ Modal opened: #${modalId}`);
                if (options.onShow) options.onShow(modal);
            },
            onClose: (modal) => {
                console.log(`🔒 Modal closed: #${modalId}`);
                if (options.onClose) options.onClose(modal);
            }
        };

        // Show the modal using MicroModal
        if (typeof MicroModal !== 'undefined') {
            MicroModal.show(modalId, config);
        } else {
            console.error('❌ MicroModal not available');
        }

        return modalElement;
    },

    /**
     * Hide a modal
     * @param {string} modalId - ID of the modal element (without #)
     */
    hide(modalId) {
        const modalElement = document.getElementById(modalId);

        if (!modalElement) {
            console.error(`❌ Modal not found: #${modalId}`);
            return;
        }

        if (typeof MicroModal !== 'undefined') {
            MicroModal.close(modalId);
        } else {
            // Fallback: manually hide
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.classList.remove('is-open');
        }
    },

    /**
     * Update modal content without hiding/showing
     * @param {string} modalId - ID of the modal element
     * @param {object} options - Content to update
     */
    update(modalId, options = {}) {
        const modalElement = document.getElementById(modalId);

        if (!modalElement) {
            console.error(`❌ Modal not found: #${modalId}`);
            return;
        }

        if (options.title) {
            // Try MicroModal structure first, fall back to Bootstrap
            const titleElement = modalElement.querySelector('.modal-title, .modal__title');
            if (titleElement) titleElement.innerHTML = options.title;
        }

        if (options.body) {
            // Try MicroModal structure first, fall back to Bootstrap
            const bodyElement = modalElement.querySelector('.modal__content, .modal-body');
            if (bodyElement) bodyElement.innerHTML = options.body;
        }

        if (options.footer) {
            // Try MicroModal structure first, fall back to Bootstrap
            const footerElement = modalElement.querySelector('.modal__footer, .modal-footer');
            if (footerElement) footerElement.innerHTML = options.footer;
        }
    },

    /**
     * Check if a modal is currently visible
     * @param {string} modalId - ID of the modal element
     * @returns {boolean}
     */
    isVisible(modalId) {
        const modalElement = document.getElementById(modalId);
        return modalElement && (
            modalElement.classList.contains('is-open') ||
            modalElement.getAttribute('aria-hidden') === 'false'
        );
    },

    /**
     * Close all open modals
     */
    closeAll() {
        document.querySelectorAll('.modal.is-open').forEach(modal => {
            if (modal.id) {
                this.hide(modal.id);
            }
        });
    },

    /**
     * Remove all modal backdrops (cleanup for dynamic modals)
     * Note: MicroModal handles this automatically, but kept for compatibility
     */
    cleanupBackdrops() {
        // MicroModal manages its own overlays, but we'll clean up any strays
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            if (!overlay.closest('.modal.is-open')) {
                overlay.remove();
            }
        });

        // Restore body scroll if no modals are open
        if (!document.querySelector('.modal.is-open')) {
            document.body.style.overflow = '';
            document.body.classList.remove('modal-open');
        }
    }
};

// Export for use in modules (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModalRenderer;
}
