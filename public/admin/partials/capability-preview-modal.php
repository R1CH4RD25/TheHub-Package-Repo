<!-- Capability Preview Modal -->
<div class="modal fade" id="capabilityPreviewModal" tabindex="-1" aria-labelledby="capabilityPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="capabilityPreviewTitle">
                    <i class="fas fa-eye"></i> Preview Access for <span id="previewRoleName">Role</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Package Name -->
                <div class="preview-package-info" style="margin-bottom: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                    <strong>Package:</strong> <span id="previewPackageName">Loading...</span>
                </div>

                <!-- Role Selector -->
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="previewRoleSelector"><strong>Select Role to Preview:</strong></label>
                    <select id="previewRoleSelector" class="form-control" onchange="capabilityPreview.loadPreview()">
                        <option value="">-- Select Role --</option>
                        <!-- Populated dynamically -->
                    </select>
                </div>

                <!-- Capabilities Grid -->
                <div id="previewCapabilitiesContainer">
                    <div class="text-center" style="padding: 2rem; color: #999;">
                        Select a role to preview capabilities
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.capability-preview-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    transition: background 0.2s;
}

.capability-preview-item.granted {
    background: #e8f5e9;
    border-color: #c8e6c9;
}

.capability-preview-item.denied {
    background: #ffebee;
    border-color: #ffcdd2;
}

.capability-preview-icon {
    font-size: 1.5rem;
    margin-right: 1rem;
    flex-shrink: 0;
}

.capability-preview-icon.granted {
    color: #2e7d32;
}

.capability-preview-icon.denied {
    color: #c62828;
}

.capability-preview-content {
    flex-grow: 1;
}

.capability-preview-label {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.capability-preview-description {
    font-size: 0.85rem;
    color: #666;
    margin: 0;
}

.capability-preview-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    margin-left: 0.5rem;
}

.badge-action { background: #e8f5e9; color: #2e7d32; }
.badge-read { background: #e3f2fd; color: #1565c0; }
.badge-admin { background: #fff3e0; color: #e65100; }
.badge-data { background: #fce4ec; color: #c2185b; }

.preview-summary {
    display: flex;
    gap: 2rem;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.preview-summary-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.preview-summary-count {
    font-size: 1.5rem;
    font-weight: 700;
}

.preview-summary-label {
    font-size: 0.85rem;
    color: #666;
}

.preview-granted-count {
    color: #2e7d32;
}

.preview-denied-count {
    color: #c62828;
}
</style>

<script>
const capabilityPreview = {
    packageSlug: null,
    packageName: null,
    capabilities: [],
    roleCapabilities: {},

    async open(packageSlug, packageName) {
        this.packageSlug = packageSlug;
        this.packageName = packageName;

        // Update modal title
        document.getElementById('previewPackageName').textContent = packageName;

        // Load roles
        await this.loadRoles();

        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('capabilityPreviewModal'));
        modal.show();
    },

    async loadRoles() {
        try {
            const response = await fetch('/api/user-roles.php');
            const roles = await response.json();

            const selector = document.getElementById('previewRoleSelector');
            selector.innerHTML = '<option value="">-- Select Role --</option>';

            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.name;
                selector.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading roles:', error);
            showMessage('Failed to load roles', 'error');
        }
    },

    async loadPreview() {
        const roleId = document.getElementById('previewRoleSelector').value;
        if (!roleId) {
            document.getElementById('previewCapabilitiesContainer').innerHTML = `
                <div class="text-center" style="padding: 2rem; color: #999;">
                    Select a role to preview capabilities
                </div>
            `;
            return;
        }

        const roleName = document.getElementById('previewRoleSelector').selectedOptions[0].textContent;
        document.getElementById('previewRoleName').textContent = roleName;

        // Show loading
        document.getElementById('previewCapabilitiesContainer').innerHTML = `
            <div class="text-center" style="padding: 2rem;">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top: 1rem; color: #666;">Loading capabilities...</p>
            </div>
        `;

        try {
            // Fetch package capabilities and role assignments in parallel
            const [capResponse, assignResponse] = await Promise.all([
                fetch(`/api/package-permissions.php?action=get_capabilities&package=${this.packageSlug}`),
                fetch(`/api/package-permissions.php?action=get_role_capabilities&package=${this.packageSlug}&role=${roleId}`)
            ]);

            const capabilities = await capResponse.json();
            const assignments = await assignResponse.json();

            this.capabilities = capabilities;
            this.roleCapabilities = assignments;

            this.renderPreview();
        } catch (error) {
            console.error('Error loading preview:', error);
            document.getElementById('previewCapabilitiesContainer').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Failed to load capabilities
                </div>
            `;
        }
    },

    renderPreview() {
        const grantedCaps = [];
        const deniedCaps = [];

        this.capabilities.forEach(cap => {
            if (this.roleCapabilities.includes(cap.key)) {
                grantedCaps.push(cap);
            } else {
                deniedCaps.push(cap);
            }
        });

        let html = `
            <!-- Summary -->
            <div class="preview-summary">
                <div class="preview-summary-item">
                    <div class="preview-summary-count preview-granted-count">${grantedCaps.length}</div>
                    <div class="preview-summary-label">Granted</div>
                </div>
                <div class="preview-summary-item">
                    <div class="preview-summary-count preview-denied-count">${deniedCaps.length}</div>
                    <div class="preview-summary-label">Denied</div>
                </div>
            </div>

            <!-- Granted Capabilities -->
            ${grantedCaps.length > 0 ? `
                <h6 style="margin-bottom: 1rem; color: #2e7d32;">
                    <i class="fas fa-check-circle"></i> Granted Capabilities
                </h6>
                ${grantedCaps.map(cap => this.renderCapability(cap, true)).join('')}
            ` : ''}

            <!-- Denied Capabilities -->
            ${deniedCaps.length > 0 ? `
                <h6 style="margin-top: 2rem; margin-bottom: 1rem; color: #c62828;">
                    <i class="fas fa-times-circle"></i> Denied Capabilities
                </h6>
                ${deniedCaps.map(cap => this.renderCapability(cap, false)).join('')}
            ` : ''}

            ${grantedCaps.length === 0 && deniedCaps.length === 0 ? `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No capabilities defined for this package yet.
                </div>
            ` : ''}
        `;

        document.getElementById('previewCapabilitiesContainer').innerHTML = html;
    },

    renderCapability(cap, isGranted) {
        return `
            <div class="capability-preview-item ${isGranted ? 'granted' : 'denied'}">
                <div class="capability-preview-icon ${isGranted ? 'granted' : 'denied'}">
                    ${isGranted ? '✅' : '❌'}
                </div>
                <div class="capability-preview-content">
                    <div class="capability-preview-label">
                        ${cap.label}
                        <span class="capability-preview-type-badge badge-${cap.type}">${cap.type}</span>
                    </div>
                    <p class="capability-preview-description">${cap.description || 'No description'}</p>
                </div>
            </div>
        `;
    }
};
</script>
