@extends('layouts.admin')

@section('title', 'Configure Packages')

@section('content')
<div class="admin-main-content">
    <div class="content-header">
        <div>
            <h1><i class="fas fa-cog"></i> Configure Packages</h1>
            <p class="text-muted">Manage settings, role mappings, and permissions for installed packages</p>
        </div>
    </div>

    <div class="content-body">
        {{-- Package List --}}
        <div id="packageListView">
            <div id="configurePackagesBody" class="pkg-grid">
                <div class="pkg-grid-loading">
                    <i class="fas fa-spinner fa-spin"></i> Loading packages...
                </div>
            </div>
        </div>

        {{-- Package Detail Config (hidden initially) --}}
        <div id="packageConfigView" style="display:none;">
            <div class="config-back-bar">
                <button class="btn btn-sm btn-secondary" onclick="showPackageList()">
                    <i class="fas fa-arrow-left"></i> All Packages
                </button>
            </div>

            {{-- Package Hero Header --}}
            <div class="pkg-hero">
                <div class="pkg-hero-icon" id="pkgIcon"></div>
                <div class="pkg-hero-body">
                    <h2 id="pkgName"></h2>
                    <p id="pkgDesc"></p>
                </div>
                <div class="pkg-hero-actions">
                    <span id="pkgVersion" class="pkg-version-badge"></span>
                    <div class="pkg-toggle-row">
                        <label class="toggle-switch" title="Enable/Disable Package">
                            <input type="checkbox" id="pkgActiveToggle" onchange="togglePackageActive()">
                            <span class="toggle-slider"></span>
                        </label>
                        <span id="pkgStatusLabel" class="pkg-status-label"></span>
                    </div>
                </div>
            </div>

            {{-- Stats Row --}}
            <div class="pkg-stats-row" id="pkgStatsRow"></div>

            {{-- Two-column layout for config sections --}}
            <div class="config-layout">
                {{-- Left column: Role mapping + capabilities --}}
                <div class="config-col-main">
                    {{-- Role Mapping Section --}}
                    <div class="config-section">
                        <div class="config-section-header" onclick="toggleConfigSection(this)">
                            <div>
                                <h3><i class="fas fa-user-shield"></i> Role Mapping</h3>
                                <p>Map package roles to your organization roles</p>
                            </div>
                            <i class="fas fa-chevron-down config-section-toggle"></i>
                        </div>
                        <div class="config-section-body">
                            <div id="roleMappingContainer">
                                <p class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading roles...</p>
                            </div>
                            <div id="roleMappingActions" class="config-section-actions d-none">
                                <button class="btn btn-sm btn-primary" onclick="saveRoleMappings()">
                                    <i class="fas fa-save"></i> Save Mappings
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Database & Capabilities --}}
                    <div class="config-section" id="capabilitiesCard" style="display:none;">
                        <div class="config-section-header" onclick="toggleConfigSection(this)">
                            <div>
                                <h3><i class="fas fa-puzzle-piece"></i> Capabilities</h3>
                                <p>Database, pages, queries, and integrations</p>
                            </div>
                            <i class="fas fa-chevron-down config-section-toggle"></i>
                        </div>
                        <div class="config-section-body">
                            <div id="capabilitiesDetail"></div>
                        </div>
                    </div>
                </div>

                {{-- Right column: Role definitions --}}
                <div class="config-col-side">
                    <div class="config-section">
                        <div class="config-section-header" onclick="toggleConfigSection(this)">
                            <div>
                                <h3><i class="fas fa-key"></i> Role Definitions</h3>
                                <p>Permissions per package role</p>
                            </div>
                            <i class="fas fa-chevron-down config-section-toggle"></i>
                        </div>
                        <div class="config-section-body">
                            <div id="packageRolesDetail">
                                <p class="text-center text-muted">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Package Grid (list view) ────────────────────────── */
.pkg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}
.pkg-grid-loading { grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-secondary, #605E5C); }

.pkg-card {
    border: 1px solid var(--border-primary, var(--border-color, #EDEBE9));
    border-radius: var(--radius-lg, 8px);
    background: var(--gray-50, #fff);
    padding: 1.5rem;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
}
.pkg-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--ms-blue, #0078D4), var(--nd-gold, #C99700));
    opacity: 0;
    transition: opacity 0.2s;
}
.pkg-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--elevation-2, 0 2px 8px rgba(0,0,0,0.08));
    border-color: var(--ms-blue, #0078D4);
}
.pkg-card:hover::before { opacity: 1; }

.pkg-card-top {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}
.pkg-card .pkg-icon {
    font-size: 1.3rem;
    width: 48px; height: 48px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--radius-lg, 8px);
    background: var(--ms-blue-light, #E3F2FD);
    color: var(--ms-blue, #0078D4);
    flex-shrink: 0;
}
.pkg-card .pkg-title { margin: 0; font-size: var(--text-md, 15px); font-weight: var(--font-semibold, 600); color: var(--text-primary, #323130); }
.pkg-card .pkg-slug { margin: 0.2rem 0 0; font-size: var(--text-sm, 13px); color: var(--text-muted, #8A8886); font-family: monospace; }

.pkg-card-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 0.75rem;
    border-top: 1px solid var(--gray-300, #EDEBE9);
    margin-top: auto;
}
.pkg-card .pkg-status {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.25rem 0.65rem; border-radius: 12px;
    font-size: var(--text-xs, 12px); font-weight: var(--font-semibold, 600);
}
.pkg-status.active { background: var(--success-light, #E8F5E9); color: var(--success, #107C10); }
.pkg-status.inactive { background: var(--warning-light, #FFF4E5); color: var(--warning, #F7630C); }
.pkg-card .pkg-ver { font-size: var(--text-xs, 12px); color: var(--text-muted, #8A8886); font-family: monospace; }

/* ── Package Hero (detail header) ────────────────────── */
.config-back-bar { margin-bottom: 1rem; }

.pkg-hero {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.5rem;
    background: var(--gray-50, #fff);
    border: 1px solid var(--border-primary, #EDEBE9);
    border-radius: var(--radius-lg, 8px);
    margin-bottom: 1rem;
    position: relative;
    overflow: hidden;
}
.pkg-hero::after {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, var(--ms-blue, #0078D4), var(--nd-gold, #C99700));
}
.pkg-hero-icon {
    font-size: 2rem;
    width: 64px; height: 64px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--radius-lg, 8px);
    background: var(--ms-blue-light, #E3F2FD);
    color: var(--ms-blue, #0078D4);
    flex-shrink: 0;
}
.pkg-hero-body { flex: 1; }
.pkg-hero-body h2 { margin: 0; font-size: var(--text-xl, 22px); font-weight: var(--font-bold, 700); }
.pkg-hero-body p { margin: 0.25rem 0 0; color: var(--text-secondary, #605E5C); font-size: var(--text-base, 14px); }
.pkg-hero-actions { text-align: right; flex-shrink: 0; }
.pkg-version-badge {
    display: inline-block;
    padding: 0.3rem 0.75rem; border-radius: 12px;
    background: var(--gray-200, #F3F2F1); color: var(--text-secondary, #605E5C);
    font-size: var(--text-sm, 13px); font-weight: var(--font-semibold, 600);
    font-family: monospace;
}
.pkg-toggle-row { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.75rem; justify-content: flex-end; }
.pkg-status-label { font-size: var(--text-sm, 13px); color: var(--text-secondary, #605E5C); font-weight: var(--font-medium, 500); }

/* ── Stats Row ───────────────────────────────────────── */
.pkg-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}
.stat-card {
    background: var(--gray-50, #fff);
    border: 1px solid var(--border-primary, #EDEBE9);
    border-radius: var(--radius-lg, 8px);
    padding: 1rem 1.25rem;
    text-align: center;
}
.stat-card .stat-value { font-size: var(--text-xl, 22px); font-weight: var(--font-bold, 700); color: var(--text-primary, #323130); }
.stat-card .stat-label { font-size: var(--text-xs, 12px); color: var(--text-muted, #8A8886); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.15rem; }
.stat-card .stat-icon { font-size: 1.1rem; margin-bottom: 0.35rem; }
.stat-card .stat-icon.blue { color: var(--ms-blue, #0078D4); }
.stat-card .stat-icon.green { color: var(--success, #107C10); }
.stat-card .stat-icon.orange { color: var(--warning, #F7630C); }
.stat-card .stat-icon.purple { color: #7B1FA2; }
.stat-card .stat-icon.gold { color: var(--nd-gold, #C99700); }

/* ── Config Layout (two-column) ──────────────────────── */
.config-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 1024px) {
    .config-layout { grid-template-columns: 1fr; }
}
.config-col-main { display: flex; flex-direction: column; gap: 1.25rem; }
.config-col-side { display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Config Sections (collapsible cards) ─────────────── */
.config-section {
    background: var(--gray-50, #fff);
    border: 1px solid var(--border-primary, #EDEBE9);
    border-radius: var(--radius-lg, 8px);
    overflow: hidden;
}
.config-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.15s;
}
.config-section-header:hover { background: var(--gray-100, #FAF9F8); }
.config-section-header h3 { margin: 0; font-size: var(--text-md, 15px); font-weight: var(--font-semibold, 600); }
.config-section-header h3 i { margin-right: 0.5rem; color: var(--ms-blue, #0078D4); }
.config-section-header p { margin: 0.15rem 0 0; font-size: var(--text-sm, 13px); color: var(--text-muted, #8A8886); }
.config-section-toggle { color: var(--text-muted, #8A8886); transition: transform 0.2s; font-size: 0.8rem; }
.config-section.collapsed .config-section-toggle { transform: rotate(-90deg); }
.config-section.collapsed .config-section-body { display: none; }
.config-section-body { padding: 0 1.25rem 1.25rem; }
.config-section-actions {
    display: flex; gap: 0.5rem; justify-content: flex-end;
    padding-top: 1rem; margin-top: 1rem;
    border-top: 1px solid var(--gray-300, #EDEBE9);
}

/* ── Role Mapping ────────────────────────────────────── */
.role-mapping-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.role-mapping-table th {
    font-size: var(--text-xs, 12px); font-weight: var(--font-semibold, 600);
    color: var(--text-muted, #8A8886); text-transform: uppercase; letter-spacing: 0.5px;
    padding: 0.5rem 0.75rem; border-bottom: 2px solid var(--gray-300, #EDEBE9);
    text-align: left;
}
.role-mapping-table td {
    padding: 0.75rem; border-bottom: 1px solid var(--gray-300, #EDEBE9);
    vertical-align: top;
}
.role-mapping-table tr:last-child td { border-bottom: none; }
.role-mapping-table .arrow-cell { text-align: center; color: var(--text-muted, #8A8886); vertical-align: middle; width: 40px; }

.role-pill {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.35rem 0.75rem; border-radius: 8px;
    font-size: var(--text-sm, 13px); font-weight: var(--font-medium, 500);
    white-space: nowrap;
}
.role-pill.tier-1 { background: var(--success-light, #E8F5E9); color: #2e7d32; }
.role-pill.tier-2 { background: var(--info-light, #E3F2FD); color: #1565c0; }
.role-pill.tier-3 { background: var(--warning-light, #FFF4E5); color: #e65100; }
.role-pill.tier-4 { background: var(--error-light, #FFEBEE); color: #c62828; }

.role-desc { font-size: var(--text-xs, 12px); color: var(--text-muted, #8A8886); margin-top: 0.25rem; }

.org-role-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 0.25rem 1rem;
}
.org-role-checkbox {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.4rem 0.5rem; font-size: var(--text-sm, 13px);
    cursor: pointer; border-radius: var(--radius-sm, 4px);
    transition: background-color 0.15s;
    white-space: nowrap;
}
.org-role-checkbox:hover { background: var(--gray-100, #FAF9F8); }
.org-role-checkbox input[type="checkbox"] {
    accent-color: var(--ms-blue, #0078D4);
    width: 16px; height: 16px; cursor: pointer; flex-shrink: 0;
}

/* ── Permission Badges ───────────────────────────────── */
.perm-badge {
    display: inline-flex; align-items: center; gap: 0.25rem;
    padding: 0.2rem 0.55rem; border-radius: 4px;
    font-size: var(--text-xs, 12px); margin: 0.15rem;
    background: var(--gray-100, #FAF9F8); color: var(--text-secondary, #605E5C);
    font-family: monospace;
}
.perm-badge.query { border-left: 3px solid var(--success, #107C10); }
.perm-badge.mutation { border-left: 3px solid var(--warning, #F7630C); }

/* ── Role Definition Cards ───────────────────────────── */
.role-def-card {
    border: 1px solid var(--gray-300, #EDEBE9);
    border-radius: var(--radius-md, 6px);
    padding: 0.875rem;
    margin-bottom: 0.75rem;
    background: var(--gray-100, #FAF9F8);
    transition: border-color 0.15s;
}
.role-def-card:last-child { margin-bottom: 0; }
.role-def-card:hover { border-color: var(--ms-blue, #0078D4); }
.role-def-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.role-def-desc { font-size: var(--text-sm, 13px); color: var(--text-secondary, #605E5C); margin: 0 0 0.5rem; }
.role-def-inherits {
    display: inline-flex; align-items: center; gap: 0.25rem;
    font-size: var(--text-xs, 12px); color: var(--text-muted, #8A8886);
}
.perm-group { margin-bottom: 0.35rem; }
.perm-group:last-child { margin-bottom: 0; }
.perm-group-label {
    font-size: var(--text-xs, 12px); font-weight: var(--font-semibold, 600);
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.2rem;
}
.perm-group-label.queries { color: var(--success, #107C10); }
.perm-group-label.mutations { color: var(--warning, #F7630C); }

/* ── Capabilities Cards Grid ─────────────────────────── */
.cap-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}
.cap-card {
    border: 1px solid var(--gray-300, #EDEBE9);
    border-radius: var(--radius-md, 6px);
    padding: 1rem;
    background: var(--gray-100, #FAF9F8);
}
.cap-card-header {
    display: flex; align-items: center; gap: 0.5rem;
    margin-bottom: 0.75rem; padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--gray-300, #EDEBE9);
}
.cap-card-header i { color: var(--ms-blue, #0078D4); font-size: 1rem; }
.cap-card-header h4 { margin: 0; font-size: var(--text-base, 14px); font-weight: var(--font-semibold, 600); }
.cap-card-header .cap-count {
    margin-left: auto; font-size: var(--text-xs, 12px);
    background: var(--ms-blue-light, #E3F2FD); color: var(--ms-blue, #0078D4);
    padding: 0.1rem 0.5rem; border-radius: 10px; font-weight: var(--font-semibold, 600);
}
.cap-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.4rem 0; font-size: var(--text-sm, 13px);
}
.cap-row + .cap-row { border-top: 1px solid var(--gray-200, #F3F2F1); }
.cap-row-label { color: var(--text-muted, #8A8886); font-weight: var(--font-medium, 500); }
.cap-row-value { font-family: monospace; color: var(--text-primary, #323130); font-size: var(--text-sm, 13px); }
.cap-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.25rem; }
.cap-gg-row {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.45rem 0; font-size: var(--text-sm, 13px);
}
.cap-gg-row + .cap-gg-row { border-top: 1px solid var(--gray-200, #F3F2F1); }
.cap-gg-email { color: var(--text-secondary, #605E5C); flex: 1; font-size: var(--text-sm, 13px); }
.cap-gg-arrow { color: var(--text-muted, #8A8886); }
.cap-gg-role { font-weight: var(--font-semibold, 600); color: var(--ms-blue, #0078D4); }

/* ── Capability Detail Rows (queries/mutations with descriptions) ── */
.cap-detail-row {
    display: flex; align-items: baseline; gap: 0.5rem;
    padding: 0.35rem 0; flex-wrap: wrap;
}
.cap-detail-row + .cap-detail-row { border-top: 1px solid var(--gray-200, #F3F2F1); }
.cap-detail-desc {
    font-size: var(--text-xs, 12px); color: var(--text-muted, #8A8886);
    flex: 1; min-width: 120px;
}

/* ── Toggle Switch ───────────────────────────────────── */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; cursor: pointer; inset: 0;
    background: var(--gray-400, #C8C6C4); border-radius: 24px; transition: 0.3s;
}
.toggle-slider:before {
    content: ""; position: absolute; height: 18px; width: 18px;
    left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s;
}
input:checked + .toggle-slider { background: var(--success, #107C10); }
input:checked + .toggle-slider:before { transform: translateX(20px); }

/* ── Empty / Loading States ──────────────────────────── */
.pkg-empty-state {
    grid-column: 1 / -1;
    text-align: center; padding: 3rem;
    color: var(--text-muted, #8A8886);
}
.pkg-empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.4; display: block; }
.pkg-empty-state p { margin: 0; }
.pkg-empty-state a { color: var(--ms-blue, #0078D4); text-decoration: underline; }

@media (max-width: 768px) {
    .pkg-grid { grid-template-columns: 1fr; }
    .pkg-hero { flex-direction: column; text-align: center; }
    .pkg-hero-actions { text-align: center; }
    .pkg-toggle-row { justify-content: center; }
    .pkg-stats-row { grid-template-columns: repeat(2, 1fr); }
    .cap-grid { grid-template-columns: 1fr; }
}
</style>

@push('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';
let currentSectionId = null;
let configData = null;

document.addEventListener('DOMContentLoaded', function() {
    @if(isset($packageId))
        loadPackageConfig('{{ $packageId }}');
    @else
        loadConfigurablePackages();
    @endif
});

function toggleConfigSection(header) {
    header.closest('.config-section').classList.toggle('collapsed');
}

async function loadConfigurablePackages() {
    try {
        const response = await fetch('/admin/packages/list?type=installed');
        const data = await response.json();
        const container = document.getElementById('configurePackagesBody');

        if (!data.success || !data.packages || data.packages.length === 0) {
            container.innerHTML = `
                <div class="pkg-empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No installed packages found.<br>
                    Install packages from the <a href="/admin/packages/available">Available</a> tab first.</p>
                </div>`;
            return;
        }

        container.innerHTML = data.packages.map(pkg => `
            <div class="pkg-card" onclick="loadPackageConfigBySectionId(${pkg.section_id})">
                <div class="pkg-card-top">
                    <div class="pkg-icon"><i class="${getIconClass(pkg.icon)}"></i></div>
                    <div>
                        <h4 class="pkg-title">${pkg.display_name || pkg.package_id}</h4>
                        <p class="pkg-slug">${pkg.slug || pkg.package_id}</p>
                    </div>
                </div>
                <div class="pkg-card-bottom">
                    <span class="pkg-status ${pkg.is_active ? 'active' : 'inactive'}">
                        <i class="fas fa-${pkg.is_active ? 'check-circle' : 'pause-circle'}"></i>
                        ${pkg.is_active ? 'Active' : 'Inactive'}
                    </span>
                    <span class="pkg-ver">v${pkg.installed_version}</span>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading packages:', error);
        document.getElementById('configurePackagesBody').innerHTML =
            '<div class="pkg-empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading packages</p></div>';
    }
}

async function loadPackageConfigBySectionId(sectionId) {
    currentSectionId = sectionId;
    document.getElementById('packageListView').style.display = 'none';
    document.getElementById('packageConfigView').style.display = 'block';

    try {
        const response = await fetch(`/admin/packages/${sectionId}/config-data`);
        const data = await response.json();

        if (!data.success) throw new Error(data.error || 'Failed to load config');

        configData = data;
        renderPackageHeader(data.section);
        renderStatsRow(data);
        renderRoleMappings(data.packageRoles, data.roleMappings, data.orgRoles);
        renderPackageRolesDetail(data.packageRoles);
        renderCapabilities(data.capabilities, data.section);

    } catch (error) {
        console.error('Config load error:', error);
        document.getElementById('roleMappingContainer').innerHTML =
            `<p class="text-danger">Error: ${error.message}</p>`;
    }
}

async function loadPackageConfig(packageId) {
    try {
        const response = await fetch('/admin/packages/list?type=installed');
        const data = await response.json();
        if (data.success && data.packages) {
            const pkg = data.packages.find(p => p.package_id === packageId);
            if (pkg) { loadPackageConfigBySectionId(pkg.section_id); return; }
        }
        loadConfigurablePackages();
    } catch (e) { loadConfigurablePackages(); }
}

function showPackageList() {
    document.getElementById('packageConfigView').style.display = 'none';
    document.getElementById('packageListView').style.display = 'block';
    currentSectionId = null;
    configData = null;
}

function renderPackageHeader(section) {
    document.getElementById('pkgIcon').innerHTML = `<i class="${getIconClass(section.icon)}"></i>`;
    document.getElementById('pkgName').textContent = section.display_name;
    document.getElementById('pkgDesc').textContent = section.description || 'No description';
    document.getElementById('pkgVersion').textContent = `v${section.installed_version}`;
    document.getElementById('pkgActiveToggle').checked = section.is_active;
    document.getElementById('pkgStatusLabel').textContent = section.is_active ? 'Enabled' : 'Disabled';
}

function renderStatsRow(data) {
    const container = document.getElementById('pkgStatsRow');
    const caps = data.capabilities || {};
    const pages = caps.presentation?.pages ? Object.keys(caps.presentation.pages).length : 0;
    const queries = caps.data?.queries ? Object.keys(caps.data.queries).length : 0;
    const mutations = caps.data?.mutations ? Object.keys(caps.data.mutations).length : 0;
    const roles = (data.packageRoles || []).length;
    const ggm = caps.policy?.googleGroupMapping ? Object.keys(caps.policy.googleGroupMapping).length : 0;

    const stats = [];
    if (roles > 0) stats.push({ icon: 'fas fa-shield-alt', cls: 'blue', value: roles, label: 'Roles' });
    if (pages > 0) stats.push({ icon: 'fas fa-desktop', cls: 'purple', value: pages, label: 'Pages' });
    if (queries > 0) stats.push({ icon: 'fas fa-search', cls: 'green', value: queries, label: 'Queries' });
    if (mutations > 0) stats.push({ icon: 'fas fa-edit', cls: 'orange', value: mutations, label: 'Mutations' });
    if (ggm > 0) stats.push({ icon: 'fab fa-google', cls: 'gold', value: ggm, label: 'Group Maps' });

    if (stats.length === 0) { container.style.display = 'none'; return; }
    container.style.display = '';
    container.innerHTML = stats.map(s => `
        <div class="stat-card">
            <div class="stat-icon ${s.cls}"><i class="${s.icon}"></i></div>
            <div class="stat-value">${s.value}</div>
            <div class="stat-label">${s.label}</div>
        </div>
    `).join('');
}

function renderRoleMappings(packageRoles, currentMappings, orgRoles) {
    const container = document.getElementById('roleMappingContainer');
    if (!packageRoles || packageRoles.length === 0) {
        container.innerHTML = '<p class="text-muted" style="text-align:center; padding:1rem;">This package does not define any roles.</p>';
        return;
    }

    const mappingLookup = {};
    currentMappings.forEach(m => {
        if (!mappingLookup[m.package_role_id]) mappingLookup[m.package_role_id] = [];
        mappingLookup[m.package_role_id].push(m.org_role_id);
    });

    let html = `<table class="role-mapping-table">
        <thead><tr>
            <th>Package Role</th>
            <th></th>
            <th>Organization Roles</th>
        </tr></thead><tbody>`;

    packageRoles.forEach(role => {
        const selectedIds = mappingLookup[role.id] || [];
        const tierClass = `tier-${Math.min(role.tier_level, 4)}`;
        const orgOptions = orgRoles.map(org => {
            const checked = selectedIds.includes(org.id) ? 'checked' : '';
            return `<label class="org-role-checkbox">
                <input type="checkbox" class="role-map-cb" data-pkg-role="${role.id}" data-org-role="${org.id}" ${checked}>
                ${org.name}
            </label>`;
        }).join('');
        const orgOptionsWrapped = `<div class="org-role-checkboxes">${orgOptions}</div>`;

        html += `<tr>
            <td>
                <span class="role-pill ${tierClass}"><i class="fas fa-shield-alt"></i> ${role.role_name}</span>
                ${role.description ? `<div class="role-desc">${role.description}</div>` : ''}
            </td>
            <td class="arrow-cell"><i class="fas fa-arrow-right"></i></td>
            <td>${orgOptionsWrapped}</td>
        </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
    document.getElementById('roleMappingActions').classList.remove('d-none');
}

function renderPackageRolesDetail(packageRoles) {
    const container = document.getElementById('packageRolesDetail');
    if (!packageRoles || packageRoles.length === 0) {
        container.innerHTML = '<p class="text-muted" style="text-align:center; padding:1rem;">No roles defined.</p>';
        return;
    }

    container.innerHTML = packageRoles.map(role => {
        const perms = role.permissions || {};
        const queries = (perms.queries || []).map(q => `<span class="perm-badge query">${q}</span>`).join('');
        const mutations = (perms.mutations || []).map(m => `<span class="perm-badge mutation">${m}</span>`).join('');
        const inherits = perms.inherits
            ? `<span class="role-def-inherits"><i class="fas fa-level-up-alt"></i> Inherits: ${perms.inherits}</span>`
            : '';
        const tierClass = `tier-${Math.min(role.tier_level, 4)}`;

        return `
            <div class="role-def-card">
                <div class="role-def-header">
                    <span class="role-pill ${tierClass}">
                        <i class="fas fa-shield-alt"></i> ${role.role_name}
                        <span style="opacity:0.6; font-size:11px;">(${role.role_key})</span>
                    </span>
                    ${inherits}
                </div>
                ${role.description ? `<p class="role-def-desc">${role.description}</p>` : ''}
                ${queries ? `<div class="perm-group"><div class="perm-group-label queries">Queries</div>${queries}</div>` : ''}
                ${mutations ? `<div class="perm-group"><div class="perm-group-label mutations">Mutations</div>${mutations}</div>` : ''}
            </div>`;
    }).join('');
}

function renderCapabilities(capabilities, section) {
    const card = document.getElementById('capabilitiesCard');
    const container = document.getElementById('capabilitiesDetail');
    if (!capabilities) { card.style.display = 'none'; return; }
    card.style.display = '';

    let cards = [];

    if (capabilities.database) {
        const db = capabilities.database;
        let rows = `<div class="cap-row"><span class="cap-row-label">Connection</span><span class="cap-row-value">${db.connection || 'default'}</span></div>`;
        rows += `<div class="cap-row"><span class="cap-row-label">Primary Table</span><span class="cap-row-value">${db.primaryTable || '—'}</span></div>`;
        if (db.auditTable) rows += `<div class="cap-row"><span class="cap-row-label">Audit Table</span><span class="cap-row-value">${db.auditTable}</span></div>`;
        cards.push(`<div class="cap-card">
            <div class="cap-card-header"><i class="fas fa-database"></i><h4>Database</h4></div>
            ${rows}
        </div>`);
    }

    if (capabilities.presentation?.pages) {
        const pages = Object.keys(capabilities.presentation.pages);
        cards.push(`<div class="cap-card">
            <div class="cap-card-header"><i class="fas fa-desktop"></i><h4>Pages</h4><span class="cap-count">${pages.length}</span></div>
            <div class="cap-tags">${pages.map(p => `<span class="perm-badge">${p}</span>`).join('')}</div>
        </div>`);
    }

    if (capabilities.data?.queries) {
        const queries = capabilities.data.queries;
        const qKeys = Object.keys(queries);
        const qRows = qKeys.map(q => {
            const desc = queries[q]?.description || '';
            return `<div class="cap-detail-row">
                <span class="perm-badge query">${q}</span>
                ${desc ? `<span class="cap-detail-desc">${desc}</span>` : ''}
            </div>`;
        }).join('');
        cards.push(`<div class="cap-card">
            <div class="cap-card-header"><i class="fas fa-search"></i><h4>Queries</h4><span class="cap-count">${qKeys.length}</span></div>
            ${qRows}
        </div>`);
    }

    if (capabilities.data?.mutations) {
        const mutations = capabilities.data.mutations;
        const mKeys = Object.keys(mutations);
        const mRows = mKeys.map(m => {
            const desc = mutations[m]?.description || '';
            return `<div class="cap-detail-row">
                <span class="perm-badge mutation">${m}</span>
                ${desc ? `<span class="cap-detail-desc">${desc}</span>` : ''}
            </div>`;
        }).join('');
        cards.push(`<div class="cap-card">
            <div class="cap-card-header"><i class="fas fa-edit"></i><h4>Mutations</h4><span class="cap-count">${mKeys.length}</span></div>
            ${mRows}
        </div>`);
    }

    if (capabilities.policy?.googleGroupMapping) {
        const ggm = capabilities.policy.googleGroupMapping;
        const rows = Object.entries(ggm).map(([email, role]) =>
            `<div class="cap-gg-row">
                <span class="cap-gg-email">${email}</span>
                <span class="cap-gg-arrow"><i class="fas fa-arrow-right"></i></span>
                <span class="cap-gg-role">${role}</span>
            </div>`
        ).join('');
        cards.push(`<div class="cap-card">
            <div class="cap-card-header"><i class="fab fa-google"></i><h4>Google Group Mapping</h4><span class="cap-count">${Object.keys(ggm).length}</span></div>
            <p style="font-size: var(--text-xs, 12px); color: var(--text-muted, #8A8886); margin: 0 0 0.5rem;">These mappings are defined in the package manifest. To configure which Google Groups map to your organization roles, use the Org Roles manager.</p>
            ${rows}
            <div style="margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px solid var(--gray-200, #F3F2F1);">
                <a href="/admin/users?tab=roles" class="btn btn-sm btn-secondary" style="font-size: 12px; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fas fa-external-link-alt"></i> Configure in Org Roles
                </a>
            </div>
        </div>`);
    }

    container.innerHTML = cards.length
        ? `<div class="cap-grid">${cards.join('')}</div>`
        : '<p class="text-muted" style="text-align:center; padding:1rem;">No capabilities data available.</p>';
}

async function saveRoleMappings() {
    if (!currentSectionId) return;
    const checkboxes = document.querySelectorAll('.role-map-cb:checked');
    const mappings = Array.from(checkboxes).map(cb => ({
        package_role_id: parseInt(cb.dataset.pkgRole),
        org_role_id: parseInt(cb.dataset.orgRole)
    }));

    try {
        const response = await fetch(`/admin/packages/${currentSectionId}/role-mappings`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ mappings })
        });
        const data = await response.json();
        if (data.success) {
            if (typeof notyf !== 'undefined') notyf.success(data.message);
            else if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2000 });
        } else throw new Error(data.error);
    } catch (error) {
        console.error('Save mappings error:', error);
        if (typeof Swal !== 'undefined') Swal.fire('Error', error.message, 'error');
    }
}

async function togglePackageActive() {
    if (!currentSectionId) return;
    try {
        const response = await fetch(`/admin/packages/${currentSectionId}/toggle-active`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });
        const data = await response.json();
        if (data.success) {
            document.getElementById('pkgStatusLabel').textContent = data.is_active ? 'Enabled' : 'Disabled';
            if (typeof notyf !== 'undefined') notyf.success(data.message);
            else if (typeof Swal !== 'undefined') Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 2000 });
        } else {
            document.getElementById('pkgActiveToggle').checked = !document.getElementById('pkgActiveToggle').checked;
            throw new Error(data.error);
        }
    } catch (error) {
        console.error('Toggle error:', error);
        if (typeof Swal !== 'undefined') Swal.fire('Error', error.message, 'error');
    }
}

function getIconClass(icon) {
    if (!icon) return 'fas fa-box';
    if (icon.startsWith('lucide-')) return 'fas fa-' + icon.replace('lucide-', '');
    if (icon.startsWith('fa-') || icon.startsWith('fas ') || icon.startsWith('bi-')) return icon.startsWith('fa-') ? 'fas ' + icon : icon;
    return 'fas fa-box';
}
</script>
@endpush
@endsection
