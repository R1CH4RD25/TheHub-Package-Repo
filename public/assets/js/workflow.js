/**
 * Workflow Module JavaScript
 * 
 * Handles workflow transition modals and interactions
 */

/**
 * Show transition modal
 * 
 * @param {string} modalId Modal element ID
 */
function showTransitionModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.error('Modal not found:', modalId);
        return;
    }
    
    // Create Bootstrap modal instance
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

/**
 * Initialize workflow page
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add submission handlers to workflow forms
    const workflowForms = document.querySelectorAll('[id^="form_transition_"]');
    
    workflowForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            }
        });
    });
    
    // Timeline animations
    const timelineEntries = document.querySelectorAll('.timeline-entry');
    if (timelineEntries.length > 0) {
        // Fade in timeline entries
        timelineEntries.forEach((entry, index) => {
            setTimeout(() => {
                entry.style.opacity = '0';
                entry.style.transform = 'translateX(-20px)';
                entry.style.transition = 'all 0.3s ease';
                
                requestAnimationFrame(() => {
                    entry.style.opacity = '1';
                    entry.style.transform = 'translateX(0)';
                });
            }, index * 50);
        });
    }
});
