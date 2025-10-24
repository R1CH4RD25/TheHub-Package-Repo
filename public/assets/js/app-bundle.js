/**
 * Application Bundle Entry Point
 * Initializes The Hub's custom functionality
 */

// Import vendor bundle first
import './vendor-bundle.js';

/**
 * The Hub Global Application Object
 */
window.TheHub = {
  version: '2.0.0',
  
  /**
   * Initialize all modern components
   */
  init() {
    this.initNotifications();
    this.initTooltips();
    this.initAnimations();
    this.initAxios();
    this.initModals();
    console.log('🚀 The Hub initialized with modern components');
  },
  
  /**
   * Initialize Notyf notification system
   */
  initNotifications() {
    this.notyf = new window.Notyf({
      duration: 4000,
      position: { x: 'right', y: 'top' },
      types: [
        {
          type: 'success',
          background: '#28a745',
          icon: {
            className: 'bi bi-check-circle-fill',
            tagName: 'i'
          }
        },
        {
          type: 'error',
          background: '#dc3545',
          icon: {
            className: 'bi bi-exclamation-triangle-fill',
            tagName: 'i'
          }
        },
        {
          type: 'warning',
          background: '#ffc107',
          icon: {
            className: 'bi bi-exclamation-circle-fill',
            tagName: 'i'
          }
        },
        {
          type: 'info',
          background: '#17a2b8',
          icon: {
            className: 'bi bi-info-circle-fill',
            tagName: 'i'
          }
        }
      ]
    });
  },
  
  /**
   * Initialize Bootstrap tooltips
   */
  initTooltips() {
    if (typeof window.bootstrap !== 'undefined') {
      const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
      [...tooltipTriggerList].map(el => new window.bootstrap.Tooltip(el));
    }
  },
  
  /**
   * Initialize AOS (Animate On Scroll)
   */
  initAnimations() {
    window.AOS.init({
      duration: 800,
      easing: 'ease-in-out',
      once: true,
      offset: 100
    });
  },
  
  /**
   * Configure Axios defaults
   */
  initAxios() {
    // Add CSRF token to all requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken) {
      window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
    }
    
    // Add JSON content type
    window.axios.defaults.headers.common['Content-Type'] = 'application/json';
    
    // Response interceptor for error handling
    window.axios.interceptors.response.use(
      response => response,
      error => {
        if (error.response?.status === 401) {
          window.location.href = '/login.php';
        }
        return Promise.reject(error);
      }
    );
  },
  
  /**
   * Initialize SweetAlert2 modals
   */
  initModals() {
    // Set default configuration
    window.Swal.mixin({
      customClass: {
        confirmButton: 'btn btn-primary me-2',
        cancelButton: 'btn btn-secondary'
      },
      buttonsStyling: false
    });
  },
  
  /**
   * Show success notification
   */
  notify(message, type = 'success') {
    this.notyf.open({ type, message });
  },
  
  /**
   * Show confirmation dialog
   */
  async confirm(title, text, confirmText = 'Confirm') {
    const result = await window.Swal.fire({
      title,
      text,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText: 'Cancel'
    });
    return result.isConfirmed;
  },
  
  /**
   * Show loading dialog
   */
  showLoading(title = 'Processing...') {
    window.Swal.fire({
      title,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        window.Swal.showLoading();
      }
    });
  },
  
  /**
   * Close loading dialog
   */
  closeLoading() {
    window.Swal.close();
  }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => window.TheHub.init());
} else {
  window.TheHub.init();
}

export default window.TheHub;
