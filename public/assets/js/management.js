/**
 * Management Console JavaScript
 * Handles interactions for the management section/submission system
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize DataTables if on section page
    if (typeof $ !== 'undefined' && $('#submissions-table').length) {
        initializeSubmissionsTable();
    }
    
    // Initialize comment system if on submission page
    if (document.getElementById('comment-form')) {
        initializeCommentSystem();
    }
    
    // Initialize bulk actions
    if (document.getElementById('bulk-actions-bar')) {
        initializeBulkActions();
    }
    
    // Initialize filters
    if (document.getElementById('btn-apply-filters')) {
        initializeFilters();
    }
    
    // Initialize status change handlers
    initializeStatusHandlers();
    
    // Initialize assignment handlers
    initializeAssignmentHandlers();
    
});

/**
 * Initialize submissions DataTable
 */
function initializeSubmissionsTable() {
    // Table initialization is handled inline in section.php
    // This function can be used for additional customization
    console.log('Submissions table initialized');
}

/**
 * Initialize comment system
 */
function initializeCommentSystem() {
    const commentForm = document.getElementById('comment-form');
    if (!commentForm) return;
    
    commentForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(commentForm);
        const submitBtn = commentForm.querySelector('button[type="submit"]');
        
        // Disable button during submission
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Posting...';
        
        try {
            const response = await fetch('/management/api/comments.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Clear form
                commentForm.reset();
                
                // Reload comments section
                location.reload();
            } else {
                alert('Error posting comment: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error posting comment:', error);
            alert('Error posting comment. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send"></i> Post Comment';
        }
    });
}

/**
 * Initialize bulk actions
 */
function initializeBulkActions() {
    const selectAllCheckbox = document.getElementById('select-all');
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    const selectedCountSpan = document.getElementById('selected-count');
    const applyBulkBtn = document.getElementById('btn-apply-bulk');
    const cancelBulkBtn = document.getElementById('btn-cancel-bulk');
    
    if (!selectAllCheckbox) return;
    
    // Handle select all
    selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.row-select');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkActionsBar();
    });
    
    // Handle individual checkbox changes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('row-select')) {
            updateBulkActionsBar();
        }
    });
    
    // Apply bulk action
    if (applyBulkBtn) {
        applyBulkBtn.addEventListener('click', function() {
            const action = document.getElementById('bulk-action').value;
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            const selectedIds = Array.from(document.querySelectorAll('.row-select:checked'))
                .map(cb => cb.value);
            
            if (selectedIds.length === 0) {
                alert('No items selected');
                return;
            }
            
            // Handle different bulk actions
            switch (action) {
                case 'assign':
                    showAssignModal(selectedIds);
                    break;
                case 'status':
                    showStatusChangeModal(selectedIds);
                    break;
                case 'delete':
                    if (confirm(`Are you sure you want to delete ${selectedIds.length} item(s)?`)) {
                        performBulkDelete(selectedIds);
                    }
                    break;
            }
        });
    }
    
    // Cancel bulk selection
    if (cancelBulkBtn) {
        cancelBulkBtn.addEventListener('click', function() {
            document.querySelectorAll('.row-select').forEach(cb => cb.checked = false);
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
            updateBulkActionsBar();
        });
    }
    
    function updateBulkActionsBar() {
        const selectedCount = document.querySelectorAll('.row-select:checked').length;
        selectedCountSpan.textContent = selectedCount;
        bulkActionsBar.style.display = selectedCount > 0 ? 'flex' : 'none';
    }
}

/**
 * Initialize filter handlers
 */
function initializeFilters() {
    const applyBtn = document.getElementById('btn-apply-filters');
    const clearBtn = document.getElementById('btn-clear-filters');
    
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            // Reload DataTable with new filters
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                $('#submissions-table').DataTable().ajax.reload();
            }
        });
    }
    
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            // Clear all filter inputs
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-priority').value = '';
            document.getElementById('filter-date-from').value = '';
            document.getElementById('filter-date-to').value = '';
            
            // Reload table
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                $('#submissions-table').DataTable().ajax.reload();
            }
        });
    }
}

/**
 * Initialize status change handlers
 */
function initializeStatusHandlers() {
    // Handle status change buttons/dropdowns
    const statusSelects = document.querySelectorAll('.status-change-select');
    
    statusSelects.forEach(select => {
        select.addEventListener('change', async function() {
            const submissionId = this.dataset.submissionId;
            const newStatusId = this.value;
            
            if (!confirm('Change status for this submission?')) {
                // Revert select
                this.value = this.dataset.originalValue || '';
                return;
            }
            
            try {
                const response = await fetch('/management/api/submissions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'change_status',
                        submission_id: submissionId,
                        status_id: newStatusId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error changing status: ' + (data.message || 'Unknown error'));
                    this.value = this.dataset.originalValue || '';
                }
            } catch (error) {
                console.error('Error changing status:', error);
                alert('Error changing status. Please try again.');
                this.value = this.dataset.originalValue || '';
            }
        });
    });
}

/**
 * Initialize assignment handlers
 */
function initializeAssignmentHandlers() {
    // Handle assign buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-assign')) {
            const btn = e.target.closest('.btn-assign');
            const submissionId = btn.dataset.id;
            showAssignModal([submissionId]);
        }
    });
}

/**
 * Show assignment modal
 */
function showAssignModal(submissionIds) {
    // TODO: Implement assignment modal
    // This would show a modal to select a user to assign submissions to
    alert('Assignment feature coming soon. IDs: ' + submissionIds.join(', '));
}

/**
 * Show status change modal
 */
function showStatusChangeModal(submissionIds) {
    // TODO: Implement status change modal
    // This would show a modal to select a new status for bulk update
    alert('Bulk status change coming soon. IDs: ' + submissionIds.join(', '));
}

/**
 * Perform bulk delete
 */
async function performBulkDelete(submissionIds) {
    try {
        const response = await fetch('/management/api/submissions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'bulk_delete',
                submission_ids: submissionIds
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Reload table
            if (typeof $ !== 'undefined' && $.fn.DataTable) {
                $('#submissions-table').DataTable().ajax.reload();
            } else {
                location.reload();
            }
        } else {
            alert('Error deleting items: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error deleting items:', error);
        alert('Error deleting items. Please try again.');
    }
}

/**
 * Helper: Show toast notification
 */
function showToast(message, type = 'info') {
    // TODO: Implement toast notifications
    // For now, use alert
    alert(message);
}

/**
 * Helper: Format date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

/**
 * Export functions for use in inline scripts
 */
window.ManagementConsole = {
    showAssignModal,
    showStatusChangeModal,
    performBulkDelete,
    showToast,
    formatDate
};
