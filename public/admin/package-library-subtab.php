<!-- Package Library Subtab (Install/Manage Packages) -->
<div class="package-library-container">
    <p class="info-text">
        <strong>📦 Package Library:</strong> Install, activate, and manage packages.
        Configuration status shows whether each package is ready for users.
    </p>

    <div id="packageLibraryTable" style="margin-top: 1.5rem;">
        <div style="text-align: center; padding: 2rem; color: #666;">
            <i class="fas fa-spinner fa-spin"></i> Loading package library...
        </div>
    </div>
</div>

<style>
.package-library-container {
    padding: 1rem 0;
}

.config-status-error {
    background: #fee2e2;
}

.config-status-warning {
    background: #fef3c7;
}

.config-status-success {
    background: #f0fdf4;
}

.role-count-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    background: #e0e7ff;
    color: #3730a3;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.role-count-badge.empty {
    background: #fee2e2;
    color: #991b1b;
}

.badge-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge-inactive {
    background: #f3f4f6;
    color: #6b7280;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}
</style>
