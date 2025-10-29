/**
 * Modern UI Helpers - SweetAlert2 & Notyf Integration
 * Replaces ugly browser alert() and confirm() with beautiful modals
 */

// Initialize Notyf for toast notifications
window.notyf = new Notyf({
    duration: 4000,
    position: { x: 'center', y: 'bottom' },
    ripple: true,
    dismissible: true,
    types: [
        {
            type: 'success',
            background: 'linear-gradient(135deg, #C99700 0%, #FFD700 100%)',
            icon: {
                className: 'notyf__icon--success',
                tagName: 'i'
            }
        },
        {
            type: 'error',
            background: 'linear-gradient(135deg, #d32f2f 0%, #f44336 100%)',
            icon: {
                className: 'notyf__icon--error',
                tagName: 'i'
            }
        },
        {
            type: 'warning',
            background: 'linear-gradient(135deg, #f57c00 0%, #ff9800 100%)',
            icon: {
                className: 'notyf__icon--warning',
                tagName: 'i'
            }
        },
        {
            type: 'info',
            background: 'linear-gradient(135deg, #0288d1 0%, #03a9f4 100%)',
            icon: {
                className: 'notyf__icon--info',
                tagName: 'i'
            }
        }
    ]
});

// Modern confirm dialog using SweetAlert2
window.modernConfirm = async function(title, text, confirmText = 'Confirm', icon = 'question') {
    const result = await Swal.fire({
        title: title,
        html: text.replace(/\n/g, '<br>'),
        icon: icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#C99700',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: 'modern-confirm-popup',
            confirmButton: 'btn-modern-confirm',
            cancelButton: 'btn-modern-cancel'
        },
        buttonsStyling: true,
        showClass: {
            popup: 'animate__animated animate__zoomIn animate__faster'
        },
        hideClass: {
            popup: 'animate__animated animate__zoomOut animate__faster'
        }
    });
    return result.isConfirmed;
};

// Modern alert dialog using SweetAlert2
window.modernAlert = async function(title, text, icon = 'info') {
    await Swal.fire({
        title: title,
        html: text.replace(/\n/g, '<br>'),
        icon: icon,
        confirmButtonText: 'OK',
        confirmButtonColor: '#C99700',
        customClass: {
            popup: 'modern-alert-popup',
            confirmButton: 'btn-modern-confirm'
        },
        buttonsStyling: true,
        showClass: {
            popup: 'animate__animated animate__zoomIn animate__faster'
        },
        hideClass: {
            popup: 'animate__animated animate__zoomOut animate__faster'
        }
    });
};

// Modern toast notification using Notyf
window.showToast = function(message, type = 'success') {
    window.notyf.open({
        type: type,
        message: message
    });
};

// DO NOT override native alert/confirm - they need to be synchronous
// Instead, we'll replace individual calls in admin.js manually or use the modern functions directly

console.log('✨ Modern UI Helpers loaded - SweetAlert2 & Notyf ready!');
