<!-- Package Permissions Subtab (Capability Matrix) -->
<div class="package-permissions-container">
    <p class="info-text">
        <strong>🎯 Package Permissions:</strong> Manage granular capabilities per role for each package.
        Use templates for quick setup or configure individual capabilities.
    </p>

    <div class="package-selector-section" style="margin-bottom: 1rem;">
        <label for="permissions-package-selector"><strong>Select Package:</strong></label>
        <select id="permissions-package-selector" class="form-control" style="max-width: 400px; display: inline-block; margin-right: 1rem;">
            <option value="">-- Choose a package --</option>
        </select>

        <!-- Capability Templates Dropdown (NEW) -->
        <select id="capability-template-selector" class="form-control" style="max-width: 300px; display: inline-block;" disabled>
            <option value="">-- Apply Template --</option>
            <option value="teacher-standard">🍎 Teacher Standard Access</option>
            <option value="admin-full">👑 Admin Full Control</option>
            <option value="office-view-only">📋 Office Staff (View Only)</option>
            <option value="manager-approval">✅ Manager (Approval Rights)</option>
            <option value="staff-basic">👤 Staff Basic Access</option>
        </select>
    </div>

    <div id="permission-matrix-container" style="display: none;">
        <!-- Permission matrix will be loaded here via PHP include -->
    </div>

    <div id="template-applied-message" style="display: none; margin-top: 1rem; padding: 1rem; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 6px;">
        <strong>✅ Template Applied!</strong> Review the permissions below and click "Save Permissions" to confirm.
    </div>
</div>

<style>
.package-permissions-container {
    padding: 1rem 0;
}

.package-selector-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.package-selector-section label {
    margin: 0;
    font-weight: 600;
}

#capability-template-selector {
    transition: opacity 0.2s;
}

#capability-template-selector:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

#template-applied-message {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
// Capability Templates Configuration
const capabilityTemplates = {
    'teacher-standard': {
        label: 'Teacher Standard Access',
        description: 'View, submit own entries, view own submissions',
        assignments: {
            'teacher': ['view', 'submit', 'view_own']
        }
    },
    'admin-full': {
        label: 'Admin Full Control',
        description: 'Complete access to all package features',
        assignments: {
            'admin': ['view', 'submit', 'view_own', 'view_all', 'approve', 'manage'],
            'super_admin': ['view', 'submit', 'view_own', 'view_all', 'approve', 'manage']
        }
    },
    'office-view-only': {
        label: 'Office Staff (View Only)',
        description: 'Read-only access to all submissions',
        assignments: {
            'office_staff': ['view', 'view_all']
        }
    },
    'manager-approval': {
        label: 'Manager (Approval Rights)',
        description: 'View all, approve, but not manage settings',
        assignments: {
            'manager': ['view', 'view_all', 'approve']
        }
    },
    'staff-basic': {
        label: 'Staff Basic Access',
        description: 'Submit and view own entries only',
        assignments: {
            'staff': ['view', 'submit', 'view_own']
        }
    }
};

// Handle template selection
document.addEventListener('DOMContentLoaded', function() {
    const templateSelector = document.getElementById('capability-template-selector');
    
    if (templateSelector) {
        templateSelector.addEventListener('change', async function() {
            const templateKey = this.value;
            if (!templateKey) return;
            
            const template = capabilityTemplates[templateKey];
            if (!template) return;
            
            // Apply template to permission matrix
            await applyCapabilityTemplate(template);
            
            // Show confirmation message
            const message = document.getElementById('template-applied-message');
            if (message) {
                message.style.display = 'block';
                setTimeout(() => {
                    message.style.display = 'none';
                }, 5000);
            }
            
            // Reset selector
            this.value = '';
        });
    }
});

async function applyCapabilityTemplate(template) {
    // This function will interact with the permission matrix
    // It will be called after the matrix is loaded
    console.log('Applying template:', template.label);
    
    // First, uncheck all checkboxes
    document.querySelectorAll('.permission-matrix input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Then apply template assignments
    Object.entries(template.assignments).forEach(([role, capabilities]) => {
        capabilities.forEach(capability => {
            const checkbox = document.querySelector(`input[data-role="${role}"][data-capability="${capability}"]`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    });
    
    console.log('✅ Template applied:', template.label);
}
</script>
