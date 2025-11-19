<?php
/**
 * Reusable Permission Matrix Component
 * Shows Role × Capability grid with smart grouping
 * 
 * Usage:
 *   $packageSlug = 'travel-requests';
 *   $readonly = false;
 *   include __DIR__ . '/permission-matrix.php';
 */

$packageSlug = $packageSlug ?? '';
$readonly = $readonly ?? false;

require_once __DIR__ . '/../../../src/bootstrap.php';

use Hub\PackageCapability;
use Hub\Database;

$capHelper = new PackageCapability();
$capabilities = $capHelper->getPackageCapabilities($packageSlug);

// Get all roles - using role ENUM
$db = Database::getInstance()->getConnection();
$rolesStmt = $db->query("
    SELECT 
        role,
        COUNT(DISTINCT u.id) as user_count
    FROM (
        SELECT 'staff' as role UNION SELECT 'student' UNION SELECT 'maintenance_staff' UNION 
        SELECT 'custodial' UNION SELECT 'cafeteria' UNION SELECT 'custodial_manager' UNION 
        SELECT 'maintenance_director' UNION SELECT 'business_manager' UNION 
        SELECT 'substitute_manager' UNION SELECT 'counselor' UNION 
        SELECT 'principal' UNION SELECT 'admin' UNION SELECT 'super_admin'
    ) all_roles
    LEFT JOIN users u ON u.role = all_roles.role AND u.is_active = 1
    GROUP BY all_roles.role
    ORDER BY user_count DESC, all_roles.role
");
$allRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Separate into common and specialized
$commonRoles = [];
$specializedRoles = [];
$thresholdUsers = 5; // Roles with 5+ users = common
$forceCommon = ['admin', 'principal', 'counselor', 'staff']; // Always visible

foreach ($allRoles as $role) {
    if ($role['user_count'] >= $thresholdUsers || in_array($role['role'], $forceCommon)) {
        $commonRoles[] = $role;
    } else {
        $specializedRoles[] = $role;
    }
}

// Get current assignments
$assignmentsStmt = $db->prepare("
    SELECT role, capability_key
    FROM package_role_capabilities
    WHERE package_slug = ?
");
$assignmentsStmt->execute([$packageSlug]);
$assignments = [];
while ($row = $assignmentsStmt->fetch(PDO::FETCH_ASSOC)) {
    $assignments[$row['role']][$row['capability_key']] = true;
}

// Check for security issues
$securityIssues = $capHelper->detectSecurityIssues($packageSlug);
$dependencyWarnings = $capHelper->validateDependencies($packageSlug);
?>

<div class="permission-matrix-container" data-package="<?= htmlspecialchars($packageSlug) ?>">

    <!-- Security Warnings -->
    <?php if (!empty($securityIssues) || !empty($dependencyWarnings)): ?>
    <div class="security-warnings">
        <?php foreach ($securityIssues as $issue): ?>
            <div class="alert alert-<?= $issue['severity'] ?>">
                <strong><?= ucfirst($issue['severity']) ?>:</strong> <?= htmlspecialchars($issue['message']) ?>
            </div>
        <?php endforeach; ?>
        
        <?php foreach ($dependencyWarnings as $warning): ?>
            <div class="alert alert-warning">
                <strong>Dependency Issue:</strong> <?= htmlspecialchars($warning['risk']) ?>
                <button type="button" class="btn-link" 
                        onclick="permissionMatrix.autoFixDependency('<?= htmlspecialchars($warning['role']) ?>', '<?= htmlspecialchars($warning['missing_dependency']) ?>')">
                    Fix Automatically
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="matrix-quick-actions">
        <button type="button" class="btn btn-sm btn-secondary" onclick="permissionMatrix.applyPreset('teacher')">
            ⚡ Typical Teacher Access
        </button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="permissionMatrix.applyPreset('admin')">
            ⚡ Admin Full Control
        </button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="permissionMatrix.applyPreset('readonly')">
            ⚡ View-Only Access
        </button>
        <button type="button" class="btn btn-sm btn-outline" onclick="permissionMatrix.clearAll()">
            🗑️ Clear All
        </button>
    </div>

    <!-- Capability Type Legend -->
    <div class="capability-legend">
        <span class="badge badge-action">Action</span> Create, submit, approve
        <span class="badge badge-read">Read</span> View, search
        <span class="badge badge-admin">Admin</span> Configure, manage
        <span class="badge badge-data">Data</span> Export, import
    </div>

    <!-- Common Roles Matrix (Always Visible) -->
    <div class="matrix-section matrix-common">
        <h3>Common Roles <span class="role-count">(<?= count($commonRoles) ?> roles)</span></h3>

        <table class="permission-matrix">
            <thead>
                <tr>
                    <th class="role-column">Role</th>
                    <?php foreach ($capabilities as $cap): ?>
                        <th class="cap-column cap-<?= $cap['type'] ?>"
                            title="<?= htmlspecialchars($cap['description']) ?>">
                            <span class="cap-label"><?= htmlspecialchars($cap['label']) ?></span>
                            <?php if (!empty($cap['dependencies'])): ?>
                                <span class="cap-dependency-badge" title="Requires: <?= implode(', ', $cap['dependencies']) ?>">🔗</span>
                            <?php endif; ?>
                            <span class="cap-type-badge"><?= $cap['type'] ?></span>
                        </th>
                    <?php endforeach; ?>
                    <th class="actions-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commonRoles as $role): ?>
                <tr data-role="<?= htmlspecialchars($role['role']) ?>">
                    <td class="role-info">
                        <strong><?= htmlspecialchars(str_replace('_', ' ', ucwords($role['role'], '_'))) ?></strong>
                        <span class="user-count"><?= $role['user_count'] ?> users</span>
                    </td>
                    <?php foreach ($capabilities as $cap): ?>
                        <td class="cap-cell">
                            <input type="checkbox"
                                   name="perm[<?= $role['role'] ?>][<?= $cap['key'] ?>]"
                                   value="1"
                                   <?= isset($assignments[$role['role']][$cap['key']]) ? 'checked' : '' ?>
                                   <?= $readonly ? 'disabled' : '' ?>
                                   data-role="<?= $role['role'] ?>"
                                   data-capability="<?= $cap['key'] ?>"
                                   onchange="permissionMatrix.trackChange(this)">
                        </td>
                    <?php endforeach; ?>
                    <td class="actions">
                        <button type="button" class="btn-icon"
                                onclick="permissionMatrix.selectAll('<?= $role['role'] ?>')"
                                title="Select all">☑️</button>
                        <button type="button" class="btn-icon"
                                onclick="permissionMatrix.clearRole('<?= $role['role'] ?>')"
                                title="Clear all">⬜</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Specialized Roles (Collapsible) -->
    <?php if (count($specializedRoles) > 0): ?>
    <div class="matrix-section matrix-specialized">
        <details>
            <summary>
                <h3>Specialized Roles <span class="role-count">(<?= count($specializedRoles) ?> roles, <?= array_sum(array_column($specializedRoles, 'user_count')) ?> users)</span></h3>
            </summary>

            <table class="permission-matrix">
                <thead>
                    <tr>
                        <th class="role-column">Role</th>
                        <?php foreach ($capabilities as $cap): ?>
                            <th class="cap-column cap-<?= $cap['type'] ?>"
                                title="<?= htmlspecialchars($cap['description']) ?>">
                                <span class="cap-label"><?= htmlspecialchars($cap['label']) ?></span>
                                <?php if (!empty($cap['dependencies'])): ?>
                                    <span class="cap-dependency-badge" title="Requires: <?= implode(', ', $cap['dependencies']) ?>">🔗</span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($specializedRoles as $role): ?>
                    <tr data-role="<?= htmlspecialchars($role['role']) ?>">
                        <td class="role-info">
                            <strong><?= htmlspecialchars(str_replace('_', ' ', ucwords($role['role'], '_'))) ?></strong>
                            <span class="user-count"><?= $role['user_count'] ?> users</span>
                        </td>
                        <?php foreach ($capabilities as $cap): ?>
                            <td class="cap-cell">
                                <input type="checkbox"
                                       name="perm[<?= $role['role'] ?>][<?= $cap['key'] ?>]"
                                       value="1"
                                       <?= isset($assignments[$role['role']][$cap['key']]) ? 'checked' : '' ?>
                                       <?= $readonly ? 'disabled' : '' ?>
                                       data-role="<?= $role['role'] ?>"
                                       data-capability="<?= $cap['key'] ?>"
                                       onchange="permissionMatrix.trackChange(this)">
                            </td>
                        <?php endforeach; ?>
                        <td class="actions">
                            <button type="button" class="btn-icon"
                                    onclick="permissionMatrix.selectAll('<?= $role['role'] ?>')"
                                    title="Select all">☑️</button>
                            <button type="button" class="btn-icon"
                                    onclick="permissionMatrix.clearRole('<?= $role['role'] ?>')"
                                    title="Clear all">⬜</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    </div>
    <?php endif; ?>

    <!-- Save Button (if not readonly) -->
    <?php if (!$readonly): ?>
    <div class="matrix-footer">
        <button type="button" class="btn btn-primary"
                onclick="permissionMatrix.save()"
                id="saveMatrixBtn">
            💾 Save Permissions
        </button>
        <span class="changes-indicator" style="display: none;">
            <span id="changeCount">0</span> unsaved changes
        </span>
    </div>
    <?php endif; ?>

</div>

<style>
.permission-matrix-container {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.security-warnings {
    margin-bottom: 20px;
}

.security-warnings .alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 10px;
}

.alert-error {
    background: #ffebee;
    border-left: 4px solid #c62828;
    color: #b71c1c;
}

.alert-warning {
    background: #fff8e1;
    border-left: 4px solid #f57c00;
    color: #e65100;
}

.alert .btn-link {
    background: none;
    border: none;
    color: inherit;
    text-decoration: underline;
    cursor: pointer;
    font-weight: 600;
    margin-left: 10px;
}

.matrix-quick-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
}

.capability-legend {
    margin-bottom: 15px;
    font-size: 0.9em;
    color: #666;
}

.capability-legend .badge {
    margin-right: 15px;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.badge-action { background: #e8f5e9; color: #2e7d32; }
.badge-read { background: #e3f2fd; color: #1565c0; }
.badge-admin { background: #fff3e0; color: #e65100; }
.badge-data { background: #fce4ec; color: #c2185b; }

.matrix-section {
    margin-bottom: 30px;
}

.matrix-section h3 {
    margin-bottom: 10px;
    font-size: 1.1em;
}

.role-count {
    font-size: 0.85em;
    color: #666;
    font-weight: normal;
}

.matrix-specialized details summary {
    cursor: pointer;
    user-select: none;
}

.matrix-specialized details summary h3 {
    display: inline-block;
}

.permission-matrix {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.permission-matrix th {
    background: #f5f5f5;
    padding: 12px 8px;
    text-align: center;
    font-weight: 600;
    border: 1px solid #ddd;
}

.permission-matrix th.role-column {
    text-align: left;
    min-width: 150px;
}

.permission-matrix th.cap-column {
    min-width: 80px;
    font-size: 0.85em;
}

.cap-label {
    display: block;
    margin-bottom: 2px;
}

.cap-dependency-badge {
    display: inline-block;
    margin-left: 4px;
    font-size: 0.9em;
    cursor: help;
}

.cap-type-badge {
    display: inline-block;
    font-size: 0.7em;
    padding: 2px 4px;
    border-radius: 3px;
    opacity: 0.7;
}

.permission-matrix td {
    padding: 10px 8px;
    border: 1px solid #ddd;
}

.role-info {
    display: flex;
    flex-direction: column;
}

.role-info strong {
    display: block;
}

.user-count {
    font-size: 0.75em;
    color: #999;
}

.cap-cell {
    text-align: center;
}

.cap-cell input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.actions-column {
    width: 80px;
}

.actions {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.btn-icon {
    background: none;
    border: none;
    font-size: 1.2em;
    cursor: pointer;
    padding: 2px;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.btn-icon:hover {
    opacity: 1;
}

.matrix-footer {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 2px solid #e0e0e0;
    display: flex;
    gap: 15px;
    align-items: center;
}

.changes-indicator {
    color: #ff9800;
    font-weight: 500;
}
</style>

<script>
const permissionMatrix = {
    changes: new Set(),
    packageSlug: '<?= $packageSlug ?>',

    trackChange(checkbox) {
        const key = `${checkbox.dataset.role}-${checkbox.dataset.capability}`;
        if (checkbox.dataset.original === undefined) {
            checkbox.dataset.original = checkbox.checked ? 'unchecked' : 'checked';
        }

        const isChanged = (checkbox.checked && checkbox.dataset.original === 'unchecked') ||
                         (!checkbox.checked && checkbox.dataset.original === 'checked');

        if (isChanged) {
            this.changes.add(key);
        } else {
            this.changes.delete(key);
        }

        this.updateChangeIndicator();
    },

    updateChangeIndicator() {
        const indicator = document.querySelector('.changes-indicator');
        const countElem = document.getElementById('changeCount');

        if (this.changes.size > 0) {
            indicator.style.display = 'inline';
            countElem.textContent = this.changes.size;
        } else {
            indicator.style.display = 'none';
        }
    },

    applyPreset(preset) {
        const checkboxes = document.querySelectorAll('.permission-matrix input[type="checkbox"]');

        // Preset definitions
        const presets = {
            teacher: {
                capabilities: ['view', 'submit', 'view_own'],
                roles: ['staff', 'counselor']
            },
            admin: {
                capabilities: '*', // all
                roles: ['admin', 'principal']
            },
            readonly: {
                capabilities: ['view', 'view_own'],
                roles: '*' // all roles
            }
        };

        const config = presets[preset];
        if (!config) return;

        checkboxes.forEach(cb => {
            const roleName = cb.closest('tr').querySelector('.role-info strong').textContent.toLowerCase().replace(/\s+/g, '_');
            const shouldCheck = (
                (config.capabilities === '*' || config.capabilities.includes(cb.dataset.capability)) &&
                (config.roles === '*' || config.roles.includes(cb.dataset.role))
            );

            if (cb.checked !== shouldCheck) {
                cb.checked = shouldCheck;
                this.trackChange(cb);
            }
        });
    },

    selectAll(roleName) {
        const checkboxes = document.querySelectorAll(`tr[data-role="${roleName}"] input[type="checkbox"]`);
        checkboxes.forEach(cb => {
            if (!cb.checked) {
                cb.checked = true;
                this.trackChange(cb);
            }
        });
    },

    clearRole(roleName) {
        const checkboxes = document.querySelectorAll(`tr[data-role="${roleName}"] input[type="checkbox"]`);
        checkboxes.forEach(cb => {
            if (cb.checked) {
                cb.checked = false;
                this.trackChange(cb);
            }
        });
    },

    clearAll() {
        if (!confirm('Clear all permissions? This cannot be undone.')) return;

        const checkboxes = document.querySelectorAll('.permission-matrix input[type="checkbox"]');
        checkboxes.forEach(cb => {
            if (cb.checked) {
                cb.checked = false;
                this.trackChange(cb);
            }
        });
    },

    autoFixDependency(roleName, missingCapability) {
        // Find the role row
        const rows = document.querySelectorAll('.permission-matrix tbody tr');
        for (const row of rows) {
            if (row.dataset.role === roleName) {
                // Find checkbox for missing capability
                const checkbox = row.querySelector(`input[data-capability="${missingCapability}"]`);
                if (checkbox && !checkbox.checked) {
                    checkbox.checked = true;
                    this.trackChange(checkbox);
                    showMessage(`✅ Auto-fixed: Added "${missingCapability}" to ${roleName.replace(/_/g, ' ')}`, 'success');
                }
                break;
            }
        }
    },

    async save() {
        const btn = document.getElementById('saveMatrixBtn');
        btn.disabled = true;
        btn.textContent = '💾 Saving...';

        // Collect all checked permissions
        const permissions = {};
        document.querySelectorAll('.permission-matrix input[type="checkbox"]:checked').forEach(cb => {
            const role = cb.dataset.role;
            if (!permissions[role]) permissions[role] = [];
            permissions[role].push(cb.dataset.capability);
        });

        try {
            const response = await fetch('/api/package-permissions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    package: this.packageSlug,
                    permissions: permissions
                })
            });

            const result = await response.json();

            if (result.success) {
                this.changes.clear();
                this.updateChangeIndicator();
                showMessage('✅ Permissions saved successfully', 'success');

                // Reset original states
                document.querySelectorAll('.permission-matrix input[type="checkbox"]').forEach(cb => {
                    delete cb.dataset.original;
                });
            } else {
                showMessage('❌ Error: ' + result.message, 'error');
            }
        } catch (error) {
            showMessage('❌ Network error: ' + error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = '💾 Save Permissions';
        }
    }
};
</script>
