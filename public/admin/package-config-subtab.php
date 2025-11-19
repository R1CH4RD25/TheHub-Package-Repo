<!-- Package Configuration Subtab -->
<div class="package-config-container">
    <p class="info-text">
        <strong>⚙️ Package Configuration:</strong> Configure category, notifications, guidelines, and settings for each package.
        Permissions are managed in the <strong>Permissions</strong> subtab.
    </p>

    <div class="package-selector-section" style="margin-bottom: 2rem;">
        <label for="config-package-selector"><strong>Select Package:</strong></label>
        <select id="config-package-selector" class="form-control" style="max-width: 400px;">
            <option value="">-- Choose a package --</option>
        </select>
    </div>

    <div id="package-config-form" style="display: none;">
        <!-- Configuration form will be loaded dynamically -->
        <div style="text-align: center; padding: 2rem; color: #666;">
            <i class="fas fa-spinner fa-spin"></i> Loading configuration...
        </div>
    </div>
</div>

<style>
.package-config-container {
    padding: 1rem 0;
}

.config-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.config-section h4 {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.capability-summary {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.capability-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.capability-summary-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
    border: 2px solid #e5e7eb;
    transition: all 0.2s;
}

.capability-summary-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.capability-summary-item.no-access {
    border-color: #fbbf24;
    background: #fffbeb;
}

.capability-summary-item.has-access {
    border-color: #10b981;
    background: #f0fdf4;
}

.capability-icon {
    font-size: 1.5rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    border-radius: 8px;
    flex-shrink: 0;
}

.capability-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
}

.capability-info strong {
    font-size: 0.9rem;
    color: #333;
}

.capability-info .role-count {
    font-size: 0.8rem;
    color: #666;
}

.capability-warnings {
    margin-top: 1rem;
}

.capability-warnings .alert {
    margin-bottom: 0.5rem;
}

.info-box {
    padding: 1rem;
    border-radius: 6px;
}

.info-box.warning {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
}

.info-box.warning strong {
    display: block;
    margin-bottom: 0.5rem;
    color: #92400e;
}

.info-box.warning p {
    margin: 0;
    color: #78350f;
    font-size: 0.9rem;
}
</style>
