/**
 * Modal Renderer Utility
 * 
 * Provides a consistent way to render dynamic modal content across The Hub.
 * All modals should be defined in public/admin/partials/modals.php and populated via this utility.
 * 
 * Philosophy:
 * - Modals are pre-defined templates in modals.php (structure)
 * - JavaScript fills them with dynamic content (data)
 * - Bootstrap handles the display/hide logic (behavior)
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
     * Show a modal with dynamic content
     * @param {string} modalId - ID of the modal element (without #)
     * @param {object} options - Content and configuration options
     * @param {string} options.title - HTML content for modal title
     * @param {string} options.body - HTML content for modal body
     * @param {string} [options.footer] - HTML content for modal footer (optional)
     * @param {boolean} [options.backdrop] - 'static' to prevent closing on outside click
     * @param {boolean} [options.keyboard] - false to prevent closing with ESC key
     * @param {function} [options.onShow] - Callback when modal is shown
     * @param {function} [options.onHide] - Callback when modal is hidden
     */
    show(modalId, options = {}) {
        const modalElement = document.getElementById(modalId);
        
        if (!modalElement) {
            console.error(`❌ Modal not found: #${modalId}`);
            console.warn('💡 Make sure the modal is defined in public/admin/partials/modals.php');
            return null;
        }

        // Populate title
        if (options.title) {
            const titleElement = modalElement.querySelector('.modal-title');
            if (titleElement) {
                titleElement.innerHTML = options.title;
            }
        }

        // Populate body
        if (options.body) {
            const bodyElement = modalElement.querySelector('.modal-body');
            if (bodyElement) {
                bodyElement.innerHTML = options.body;
            }
        }

        // Populate footer (optional)
        if (options.footer) {
            const footerElement = modalElement.querySelector('.modal-footer');
            if (footerElement) {
                footerElement.innerHTML = options.footer;
            }
        }

        // Get or create Bootstrap modal instance
        let modalInstance = bootstrap.Modal.getInstance(modalElement);
        
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalElement, {
                backdrop: options.backdrop || true,
                keyboard: options.keyboard !== false
            });
        }

        // Attach event listeners if provided
        if (options.onShow) {
            modalElement.addEventListener('shown.bs.modal', options.onShow, { once: true });
        }

        if (options.onHide) {
            modalElement.addEventListener('hidden.bs.modal', options.onHide, { once: true });
        }

        // Show the modal
        modalInstance.show();

        return modalInstance;
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

        const modalInstance = bootstrap.Modal.getInstance(modalElement);
        
        if (modalInstance) {
            modalInstance.hide();
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
            const titleElement = modalElement.querySelector('.modal-title');
            if (titleElement) titleElement.innerHTML = options.title;
        }

        if (options.body) {
            const bodyElement = modalElement.querySelector('.modal-body');
            if (bodyElement) bodyElement.innerHTML = options.body;
        }

        if (options.footer) {
            const footerElement = modalElement.querySelector('.modal-footer');
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
        return modalElement && modalElement.classList.contains('show');
    },

    /**
     * Remove all modal backdrops (cleanup for dynamic modals)
     */
    cleanupBackdrops() {
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
};

// Export for use in modules (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModalRenderer;
}
