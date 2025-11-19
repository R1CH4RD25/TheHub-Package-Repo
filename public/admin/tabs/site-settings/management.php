                        <div id="subtab-management" class="user-subtab">
                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Customize how the Management system appears to users. Change the display name, icon, and description.
                            </p>

                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Management System Branding
                                        <span class="color-section-badge">3</span>
                                    </h3>
                                    <span class="color-section-toggle">▼</span>
                                </div>
                                <div class="color-section-body">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="mgmt_display_name">
                                                    Display Name
                                                    <span class="info-tooltip" title="The public-facing name shown in navigation and page titles">ⓘ</span>
                                                </label>
                                                <input type="text"
                                                       id="mgmt_display_name"
                                                       name="mgmt_display_name"
                                                       value="<?php echo e(SiteSettings::get('mgmt_display_name', 'Management')); ?>"
                                                       class="form-control"
                                                       placeholder="e.g., Management, Administration, Operations">
                                                <small class="form-text text-muted">Examples: Management, Administration, Operations, Control Center</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="mgmt_icon">
                                                    Navigation Icon
                                                    <span class="info-tooltip" title="Bootstrap icon class for navigation links">ⓘ</span>
                                                </label>
                                                <div style="display: flex; gap: 0.75rem; align-items: center;">
                                                    <!-- Compact Live Preview -->
                                                    <div style="width: 40px; height: 40px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); display: flex; align-items: center; justify-content: center; background: var(--card-bg); font-size: 20px; flex-shrink: 0;">
                                                        <i id="icon-preview" class="<?php echo e(SiteSettings::get('mgmt_icon', 'bi-kanban')); ?>"></i>
                                                    </div>

                                                    <!-- Input Field -->
                                                    <div style="flex: 1;">
                                                        <input type="text"
                                                               id="mgmt_icon"
                                                               name="mgmt_icon"
                                                               value="<?php echo e(SiteSettings::get('mgmt_icon', 'bi-kanban')); ?>"
                                                               class="form-control"
                                                               placeholder="e.g., bi-kanban, bi-gear, bi-building"
                                                               oninput="updateIconPreview(this.value)">
                                                    </div>

                                                    <!-- Dropdown Toggle Button -->
                                                    <button type="button"
                                                            class="btn btn-sm btn-secondary"
                                                            onclick="toggleIconDropdown()"
                                                            style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                                        <i class="bi bi-grid-3x3-gap"></i>
                                                        <span id="icon-dropdown-text">Browse Icons</span>
                                                        <i id="icon-dropdown-arrow" class="bi bi-chevron-down" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                </div>

                                                <small class="form-text text-muted">
                                                    Click "Browse Icons" to select from popular options, or
                                                    <a href="https://icons.getbootstrap.com/" target="_blank">browse all Bootstrap Icons</a>
                                                </small>

                                                <!-- Collapsible Icon Selector -->
                                                <div id="icon-selector-dropdown" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: var(--hover-bg); border: 1px solid var(--border-color); border-radius: var(--border-radius); max-height: 200px; overflow-y: auto;">
                                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 0.5rem;">
                                                        <?php
                                                        $quickIcons = [
                                                            ['icon' => 'bi-kanban', 'label' => 'Kanban'],
                                                            ['icon' => 'bi-gear-fill', 'label' => 'Settings'],
                                                            ['icon' => 'bi-building', 'label' => 'Building'],
                                                            ['icon' => 'bi-clipboard-data', 'label' => 'Data'],
                                                            ['icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
                                                            ['icon' => 'bi-list-check', 'label' => 'Checklist'],
                                                            ['icon' => 'bi-folder', 'label' => 'Folder'],
                                                            ['icon' => 'bi-graph-up', 'label' => 'Analytics'],
                                                            ['icon' => 'bi-pencil-square', 'label' => 'Edit'],
                                                            ['icon' => 'bi-shield-check', 'label' => 'Security'],
                                                            ['icon' => 'bi-people', 'label' => 'People'],
                                                            ['icon' => 'bi-file-earmark-text', 'label' => 'Document'],
                                                            ['icon' => 'bi-briefcase', 'label' => 'Briefcase'],
                                                            ['icon' => 'bi-box', 'label' => 'Box'],
                                                            ['icon' => 'bi-calendar', 'label' => 'Calendar'],
                                                            ['icon' => 'bi-chat', 'label' => 'Chat']
                                                        ];
                                                        foreach ($quickIcons as $qIcon): ?>
                                                            <button type="button"
                                                                    class="icon-selector-btn-compact"
                                                                    onclick="selectIcon('<?php echo $qIcon['icon']; ?>')"
                                                                    title="<?php echo $qIcon['label']; ?>">
                                                                <i class="<?php echo $qIcon['icon']; ?>"></i>
                                                                <span><?php echo $qIcon['label']; ?></span>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group" style="grid-column: span 2;">
                                                <label for="mgmt_description">
                                                    Description
                                                    <span class="info-tooltip" title="Brief description shown to users">ⓘ</span>
                                                </label>
                                                <textarea id="mgmt_description"
                                                          name="mgmt_description"
                                                          class="form-control"
                                                          rows="3"
                                                          placeholder="Brief description of what this system does..."><?php echo e(SiteSettings::get('mgmt_description', 'Centralized management system for tracking and processing submissions')); ?></textarea>
                                                <small class="form-text text-muted">Optional: Shown in selector page and help documentation</small>
                                            </div>
                                        </div>

                                        <div class="form-actions">
                                            <button type="button" class="btn btn-primary" onclick="saveManagementSettings()">
                                                <i class="bi bi-check-circle"></i> Save Management Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- end subtab-management -->

                        <!-- Advanced Subtab -->
