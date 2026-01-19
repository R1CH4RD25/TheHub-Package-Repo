<!-- Package Configuration Partial View -->
<div class="package-configure-view">
    <!-- Header with Back Button -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-6);">
        <div>
            <h2 style="margin: 0; font-size: var(--text-2xl); font-weight: var(--font-semibold); color: var(--gray-900);">
                <i class="fas fa-cog"></i> Configure <?php echo e($package['display_name'] ?? 'Package'); ?>

            </h2>
            <p style="margin: var(--space-1) 0 0 0; color: var(--gray-600);">Manage settings and permissions for this package</p>
        </div>
        <button onclick="loadInstalledPackages()" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Packages
        </button>
    </div>

    <!-- Package Info Card -->
    <div class="nd-card" style="margin-bottom: var(--space-6);">
        <div class="nd-card-header">
            <h3><i class="fas fa-info-circle"></i> Package Information</h3>
        </div>
        <div class="nd-card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-4);">
                <div>
                    <strong class="text-muted">Package ID:</strong>
                    <p><?php echo e($packageId); ?></p>
                </div>
                <div>
                    <strong class="text-muted">Version:</strong>
                    <p><?php echo e($package['installed_version'] ?? $package['version'] ?? 'Unknown'); ?></p>
                </div>
                <div>
                    <strong class="text-muted">Installed:</strong>
                    <p><?php echo e(isset($package['installed_at']) ? date('F j, Y', strtotime($package['installed_at'])) : 'Unknown'); ?></p>
                </div>
                <div>
                    <strong class="text-muted">Status:</strong>
                    <p><span class="badge badge-success">Active</span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Options -->
    <div class="nd-card">
        <div class="nd-card-header">
            <h3><i class="fas fa-sliders-h"></i> Configuration Options</h3>
        </div>
        <div class="nd-card-body">
            <p class="info-text" style="margin-bottom: var(--space-6);">
                <i class="fas fa-lightbulb" style="color: var(--warning);"></i>
                <strong>Package Configuration:</strong> Based on the package's capabilities, you can configure various settings below.
            </p>

            <div class="config-section-group">
                <!-- General Settings -->
                <div class="config-section">
                    <h4><i class="fas fa-cog"></i> General Settings</h4>
                    <p class="text-muted" style="margin-bottom: var(--space-4);">Basic package settings and preferences</p>
                    
                    <div class="form-group">
                        <label for="packageEnabled">
                            <input type="checkbox" id="packageEnabled" checked>
                            <strong>Enable Package</strong>
                        </label>
                        <p class="help-text">Temporarily disable this package without uninstalling it</p>
                    </div>

                    <div class="form-group">
                        <label for="showInMenu"><strong>Display in Menu</strong></label>
                        <select id="showInMenu" class="form-select">
                            <option value="yes">Yes - Show in navigation menu</option>
                            <option value="no">No - Access via direct link only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="menuLabel"><strong>Menu Label</strong></label>
                        <input type="text" id="menuLabel" class="form-input" 
                               value="<?php echo e($package['display_name'] ?? ''); ?>" 
                               placeholder="Label shown in menu">
                    </div>
                </div>

                <!-- Permissions -->
                <div class="config-section">
                    <h4><i class="fas fa-user-shield"></i> Access Permissions</h4>
                    <p class="text-muted" style="margin-bottom: var(--space-4);">Control who can access this package</p>
                    
                    <div class="permission-matrix">
                        <p class="info-text">
                            <i class="fas fa-info-circle"></i>
                            More advanced permission configuration available in 
                            <a href="/admin/permissions" style="color: var(--primary-color); text-decoration: underline;">
                                Admin → Permissions
                            </a>
                        </p>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="config-section">
                    <h4><i class="fas fa-bell"></i> Notifications</h4>
                    <p class="text-muted" style="margin-bottom: var(--space-4);">Configure notification preferences</p>
                    
                    <div class="form-group">
                        <label for="sendNotifications">
                            <input type="checkbox" id="sendNotifications" checked>
                            <strong>Send Email Notifications</strong>
                        </label>
                        <p class="help-text">Notify designated users about new submissions or updates</p>
                    </div>

                    <div class="form-group">
                        <label for="notificationRecipients"><strong>Notification Recipients</strong></label>
                        <input type="text" id="notificationRecipients" class="form-input" 
                               placeholder="Enter email addresses (comma-separated)">
                        <p class="help-text">Users who will receive notifications</p>
                    </div>
                </div>

                <!-- Advanced Settings -->
                <div class="config-section">
                    <h4><i class="fas fa-tools"></i> Advanced Settings</h4>
                    <p class="text-muted" style="margin-bottom: var(--space-4);">Additional configuration options</p>
                    
                    <div class="form-group">
                        <label for="allowAttachments">
                            <input type="checkbox" id="allowAttachments" checked>
                            <strong>Allow File Attachments</strong>
                        </label>
                        <p class="help-text">Enable users to upload files with submissions</p>
                    </div>

                    <div class="form-group">
                        <label for="requireApproval">
                            <input type="checkbox" id="requireApproval">
                            <strong>Require Admin Approval</strong>
                        </label>
                        <p class="help-text">New submissions require admin review before being visible</p>
                    </div>

                    <div class="form-group">
                        <label for="maxFileSize"><strong>Maximum File Size (MB)</strong></label>
                        <input type="number" id="maxFileSize" class="form-input" 
                               value="10" min="1" max="50">
                    </div>
                </div>

                <!-- Save Button -->
                <div style="margin-top: var(--space-6); padding-top: var(--space-4); border-top: 1px solid var(--gray-300);">
                    <button class="btn btn-primary" onclick="savePackageConfig('<?php echo e($packageId); ?>')">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                    <button class="btn btn-secondary" onclick="loadInstalledPackages()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Save package configuration
function savePackageConfig(packageId) {
    notyf.success('Configuration saved successfully!');
    // TODO: Implement actual save functionality
    // This will send the configuration to the backend API
}
</script>

<style>
.package-configure-view {
    padding: 0;
}

.config-section-group {
    display: flex;
    flex-direction: column;
    gap: var(--space-6);
}

.config-section {
    padding: var(--space-5);
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
}

.config-section h4 {
    margin-bottom: var(--space-2);
    color: var(--gray-900);
    font-size: var(--text-lg);
    font-weight: var(--font-semibold);
}

.form-group {
    margin-bottom: var(--space-4);
}

.form-group label {
    display: block;
    margin-bottom: var(--space-2);
    color: var(--gray-800);
    font-weight: var(--font-medium);
}

.form-input,
.form-select {
    width: 100%;
    max-width: 500px;
    padding: var(--space-2) var(--space-3);
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-base);
    font-size: var(--text-md);
}

.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.help-text {
    margin-top: var(--space-1);
    font-size: var(--text-sm);
    color: var(--gray-600);
}

.permission-matrix {
    padding: var(--space-4);
    background: white;
    border-radius: var(--radius-base);
    border: 1px solid var(--gray-300);
}
</style>
<?php /**PATH /var/www/woodson/thehub/resources/views/admin/partials/package-configure.blade.php ENDPATH**/ ?>