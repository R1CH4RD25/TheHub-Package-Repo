/**
 * Form Validation & Modal Animation Enhancements
 * Automatically applies modern animations to forms, modals, and validation states
 */

(function() {
    'use strict';

    const ValidationAnimations = {
        
        // Initialize all enhancements
        init() {
            console.log('✨ Form Validation Animations initialized');
            this.enhanceModals();
            this.enhanceFormValidation();
            this.enhanceButtonStates();
        },
        
        // Enhance modal open/close with smooth animations
        enhanceModals() {
            // Watch for modals being added to the DOM
            const bodyObserver = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            // Check if the added node is a modal or contains modals
                            if (node.classList && (node.classList.contains('modal') || node.classList.contains('modal-overlay'))) {
                                console.log('✨ New modal detected:', node.id);
                            }
                            // Check for modals inside the added node
                            node.querySelectorAll && node.querySelectorAll('.modal, .modal-overlay').forEach(modal => {
                                console.log('✨ New modal found inside added node:', modal.id);
                            });
                        }
                    });
                });
            });
            
            // Observe body for new modals being added
            bodyObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Override closeModal function to add exit animation
            if (window.closeModal) {
                const originalCloseModal = window.closeModal;
                window.closeModal = function() {
                    // Add hiding class for exit animation
                    document.querySelectorAll('.modal.show, .modal-overlay.show').forEach(modal => {
                        modal.classList.add('hiding');
                        modal.classList.remove('show');
                    });
                    
                    // Wait for animation before actually closing
                    setTimeout(() => {
                        document.querySelectorAll('.modal, .modal-overlay').forEach(modal => {
                            modal.classList.remove('hiding');
                        });
                        originalCloseModal();
                    }, 250);
                };
            }
        },
        
        // Enhance form inputs with validation animations
        enhanceFormValidation() {
            // Real-time validation on blur
            document.addEventListener('blur', (e) => {
                const input = e.target;
                
                if (input.tagName === 'INPUT' || input.tagName === 'SELECT' || input.tagName === 'TEXTAREA') {
                    // Skip checkboxes and radios
                    if (input.type === 'checkbox' || input.type === 'radio') return;
                    
                    // Check if required and empty
                    if (input.hasAttribute('required') && !input.value.trim()) {
                        this.markInvalid(input, 'This field is required');
                    }
                    // Check email format
                    else if (input.type === 'email' && input.value && !this.isValidEmail(input.value)) {
                        this.markInvalid(input, 'Please enter a valid email address');
                    }
                    // Valid
                    else if (input.value.trim()) {
                        this.markValid(input);
                    }
                    // Empty but not required - clear state
                    else {
                        this.clearValidation(input);
                    }
                }
            }, true);
            
            // Form submission validation
            document.addEventListener('submit', (e) => {
                const form = e.target;
                let isValid = true;
                
                // Check all required fields
                form.querySelectorAll('[required]').forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') return;
                    
                    if (!input.value.trim()) {
                        this.markInvalid(input, 'This field is required');
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    // Focus first invalid field
                    const firstInvalid = form.querySelector('.invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }, true);
        },
        
        // Mark input as invalid with animation
        markInvalid(input, message) {
            input.classList.remove('valid');
            input.classList.add('invalid');
            
            // Remove existing error message
            const existingError = input.parentElement.querySelector('.error-message');
            if (existingError) existingError.remove();
            
            // Add error message if provided
            if (message) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = message;
                input.parentElement.appendChild(errorDiv);
            }
        },
        
        // Mark input as valid with animation
        markValid(input) {
            input.classList.remove('invalid');
            input.classList.add('valid');
            
            // Remove error message
            const existingError = input.parentElement.querySelector('.error-message');
            if (existingError) existingError.remove();
        },
        
        // Clear validation state
        clearValidation(input) {
            input.classList.remove('valid', 'invalid');
            const existingError = input.parentElement.querySelector('.error-message');
            if (existingError) existingError.remove();
        },
        
        // Email validation helper
        isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        // Enhance button states (loading, success, error)
        enhanceButtonStates() {
            // Expose helper functions globally
            window.buttonLoading = (button) => {
                if (typeof button === 'string') {
                    button = document.querySelector(button);
                }
                if (button) {
                    button.classList.add('loading');
                    button.disabled = true;
                }
            };
            
            window.buttonSuccess = (button, duration = 2000) => {
                if (typeof button === 'string') {
                    button = document.querySelector(button);
                }
                if (button) {
                    button.classList.remove('loading', 'error');
                    button.classList.add('success');
                    button.disabled = false;
                    
                    if (duration) {
                        setTimeout(() => {
                            button.classList.remove('success');
                        }, duration);
                    }
                }
            };
            
            window.buttonError = (button, duration = 2000) => {
                if (typeof button === 'string') {
                    button = document.querySelector(button);
                }
                if (button) {
                    button.classList.remove('loading', 'success');
                    button.classList.add('error');
                    button.disabled = false;
                    
                    if (duration) {
                        setTimeout(() => {
                            button.classList.remove('error');
                        }, duration);
                    }
                }
            };
            
            window.buttonReset = (button) => {
                if (typeof button === 'string') {
                    button = document.querySelector(button);
                }
                if (button) {
                    button.classList.remove('loading', 'success', 'error');
                    button.disabled = false;
                }
            };
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ValidationAnimations.init());
    } else {
        ValidationAnimations.init();
    }
    
    // Expose globally
    window.ValidationAnimations = ValidationAnimations;
    
})();
