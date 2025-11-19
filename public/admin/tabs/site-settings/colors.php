                        <div id="subtab-colors" class="user-subtab">

                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Click sections to expand and customize colors. Changes apply immediately to the site preview.
                            </p>

                            <!-- Main Theme Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Main Theme Colors
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="color-grid">
                                            <div class="color-item">
                                                <label>Primary Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="primaryColor" value="#C99700">
                                                    <input type="text" id="primaryColorHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Links, active states, focus rings</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Navbar Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="navbarColor" value="#000000">
                                                    <input type="text" id="navbarColorHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Top navigation bar</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Page Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="backgroundColor" value="#FFFFFF">
                                                    <input type="text" id="backgroundColorHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                                <small>Main content area</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Accent Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="accentColor" value="#FFD700">
                                                    <input type="text" id="accentColorHex" value="#FFD700" maxlength="7">
                                                </div>
                                                <small>Hover highlights</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Hub Page Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubPageBg" value="#FFFFFF">
                                                    <input type="text" id="hubPageBgHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                                <small>Hub landing page background</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hub Tiles & Effects -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Hub Tiles & Effects
                                        <span class="color-section-badge">23</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem; font-size: 0.9rem;">
                                            Customize the visual effects for section tiles on the Hub landing page.
                                        </p>
                                        <div class="color-grid">
                                            <!-- Base Hub Colors -->
                                            <div class="color-item">
                                                <label>Hub Tile Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubTileBg" value="#FFFFFF">
                                                    <input type="text" id="hubTileBgHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                                <small>Section tile card background color</small>
                                            </div>

                                            <!-- Hover Effects -->
                                            <div class="color-item">
                                                <label>Card Hover Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverShadow" value="#C99700">
                                                    <input type="text" id="hubCardHoverShadowHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Glow shadow on tile hover (with opacity)</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Hover Border</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverBorder" value="#C99700">
                                                    <input type="text" id="hubCardHoverBorderHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Border color on tile hover</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Hover Title</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverTitle" value="#C99700">
                                                    <input type="text" id="hubCardHoverTitleHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Title text color on hover</small>
                                            </div>

                                            <!-- Particle/Background Effects -->
                                            <div class="color-item">
                                                <label>Particle Glow 1</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubParticleGlow1" value="#C99700">
                                                    <input type="text" id="hubParticleGlow1Hex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>First animated background glow color</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Particle Glow 2</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubParticleGlow2" value="#FFD700">
                                                    <input type="text" id="hubParticleGlow2Hex" value="#FFD700" maxlength="7">
                                                </div>
                                                <small>Second animated background glow color</small>
                                            </div>

                                            <!-- Card Hover Overlay Effects -->
                                            <div class="color-item">
                                                <label>Card Hover Overlay Center</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardGlowCenter" value="#C99700">
                                                    <input type="text" id="hubCardGlowCenterHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Center color of hover gradient overlay</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Hover Overlay Edge</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardGlowEdge" value="#000000">
                                                    <input type="text" id="hubCardGlowEdgeHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Edge color of hover gradient (creates dark effect)</small>
                                            </div>

                                            <!-- Icon Effects -->
                                            <div class="color-item">
                                                <label>Icon Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubIconColor" value="#C99700">
                                                    <input type="text" id="hubIconColorHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Default tile icon color</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Icon Hover Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubIconHoverColor" value="#FFD700">
                                                    <input type="text" id="hubIconHoverColorHex" value="#FFD700" maxlength="7">
                                                </div>
                                                <small>Icon color on tile hover</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Icon Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubIconShadow" value="#C99700">
                                                    <input type="text" id="hubIconShadowHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Glow shadow around tile icons</small>
                                            </div>

                                            <!-- Card Content -->
                                            <div class="color-item">
                                                <label>Card Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardShadow" value="#000000">
                                                    <input type="text" id="hubCardShadowHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Default card shadow (with opacity)</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Border</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardBorder" value="#E5E7EB">
                                                    <input type="text" id="hubCardBorderHex" value="#E5E7EB" maxlength="7">
                                                </div>
                                                <small>Default card border color</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Description</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardDescription" value="#6B7280">
                                                    <input type="text" id="hubCardDescriptionHex" value="#6B7280" maxlength="7">
                                                </div>
                                                <small>Description text color (normal state)</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Description Hover</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverDescription" value="#374151">
                                                    <input type="text" id="hubCardHoverDescriptionHex" value="#374151" maxlength="7">
                                                </div>
                                                <small>Description text color on hover</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Description Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverDescriptionShadow" value="#000000">
                                                    <input type="text" id="hubCardHoverDescriptionShadowHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Text shadow on description hover</small>
                                            </div>

                                            <!-- No Sections Message -->
                                            <div class="color-item">
                                                <label>No Sections Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubNoSectionsBg" value="#F9FAFB">
                                                    <input type="text" id="hubNoSectionsBgHex" value="#F9FAFB" maxlength="7">
                                                </div>
                                                <small>"No sections available" message background</small>
                                            </div>
                                            <div class="color-item">
                                                <label>No Sections Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubNoSectionsShadow" value="#000000">
                                                    <input type="text" id="hubNoSectionsShadowHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Shadow for "no sections" message</small>
                                            </div>

                                            <!-- Particle Effect Controls Section -->
                                            <div style="grid-column: 1 / -1;">
                                                <h4 style="margin: 2rem 0 1rem; color: var(--text-secondary); font-size: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                                                    🌟 Particle Effect Settings
                                                </h4>
                                            </div>

                            <!-- 5-column grid for particle controls -->
                            <div style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem;">
                                <div class="form-group" style="margin: 0; text-align: center;">
                                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; justify-content: center;">
                                        <input type="checkbox" id="hubParticleEnabled" checked style="margin: 0;">
                                        Enable Effect
                                    </label>
                                    <small style="display: block; margin-top: 0.5rem; text-align: center;">Turn glow on/off</small>
                                </div>

                                <div class="form-group" style="margin: 0;">
                                    <label for="hubParticleSize" style="font-weight: 600; display: block; margin-bottom: 0.25rem;">Size (px)</label>
                                    <input type="number" id="hubParticleSize" value="600" min="200" max="1200" step="50" style="width: 100%;">
                                    <small style="display: block; margin-top: 0.25rem;">200-1200</small>
                                </div>                                                <div class="form-group" style="margin: 0;">
                                                    <label for="hubParticleBlur" style="font-weight: 600; display: block; margin-bottom: 0.25rem;">Blur (px)</label>
                                                    <input type="number" id="hubParticleBlur" value="150" min="50" max="400" step="10" style="width: 100%;">
                                                    <small style="display: block; margin-top: 0.25rem;">50-400</small>
                                                </div>

                                                <div class="form-group" style="margin: 0;">
                                                    <label for="hubParticleOpacity" style="font-weight: 600; display: block; margin-bottom: 0.25rem;">Opacity</label>
                                                    <input type="number" id="hubParticleOpacity" value="0.15" min="0.05" max="0.5" step="0.05" style="width: 100%;">
                                                    <small style="display: block; margin-top: 0.25rem;">0.05-0.5</small>
                                                </div>

                                                <div class="form-group" style="margin: 0;">
                                                    <label for="hubParticleSpeed" style="font-weight: 600; display: block; margin-bottom: 0.25rem;">Speed (s)</label>
                                                    <input type="number" id="hubParticleSpeed" value="20" min="5" max="60" step="5" style="width: 100%;">
                                                    <small style="display: block; margin-top: 0.25rem;">5-60 sec</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Text Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Text Colors
                                        <span class="color-section-badge">9</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="color-pairs-grid">
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Primary Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textPrimary" value="#111827">
                                                    <input type="text" id="textPrimaryHex" value="#111827" maxlength="7">
                                                </div>
                                                <small>Main headings & body</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Secondary Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textSecondary" value="#374151">
                                                    <input type="text" id="textSecondaryHex" value="#374151" maxlength="7">
                                                </div>
                                                <small>Subheadings</small>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Muted Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textMuted" value="#6B7280">
                                                    <input type="text" id="textMutedHex" value="#6B7280" maxlength="7">
                                                </div>
                                                <small>Helper text</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Disabled Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textDisabled" value="#D1D5DB">
                                                    <input type="text" id="textDisabledHex" value="#D1D5DB" maxlength="7">
                                                </div>
                                                <small>Inactive elements</small>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Inverse Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textInverse" value="#FFFFFF">
                                                    <input type="text" id="textInverseHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                                <small>Text on dark backgrounds</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Link Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="linkColor" value="#3B82F6">
                                                    <input type="text" id="linkColorHex" value="#3B82F6" maxlength="7">
                                                </div>
                                                <small>Hyperlinks</small>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Hub Tile Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubTileText" value="#333333">
                                                    <input type="text" id="hubTileTextHex" value="#333333" maxlength="7">
                                                </div>
                                                <small>Text on Hub section tiles</small>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Hub Title Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubTitleColor" value="#000000">
                                                    <input type="text" id="hubTitleColorHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Hub page main title (Site Name)</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Hub Subtitle Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubSubtitleColor" value="#666666">
                                                    <input type="text" id="hubSubtitleColorHex" value="#666666" maxlength="7">
                                                </div>
                                                <small>Hub page subtitle (Welcome Message)</small>
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Button Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Button Colors
                                        <span class="color-section-badge">9</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="color-pairs-grid">
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Primary Button Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonPrimaryBg" value="#C99700">
                                                    <input type="text" id="buttonPrimaryBgHex" value="#C99700" maxlength="7">
                                                </div>
                                            </div>
                                            <div class="color-item">
                                                <label>Primary Button Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonPrimaryText" value="#FFFFFF">
                                                    <input type="text" id="buttonPrimaryTextHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Secondary Button Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonSecondaryBg" value="#6B7280">
                                                    <input type="text" id="buttonSecondaryBgHex" value="#6B7280" maxlength="7">
                                                </div>
                                            </div>
                                            <div class="color-item">
                                                <label>Secondary Button Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonSecondaryText" value="#FFFFFF">
                                                    <input type="text" id="buttonSecondaryTextHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Danger Button Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonDangerBg" value="#DC2626">
                                                    <input type="text" id="buttonDangerBgHex" value="#DC2626" maxlength="7">
                                                </div>
                                            </div>
                                            <div class="color-item">
                                                <label>Danger Button Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonDangerText" value="#FFFFFF">
                                                    <input type="text" id="buttonDangerTextHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Success Button Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonSuccessBg" value="#059669">
                                                    <input type="text" id="buttonSuccessBgHex" value="#059669" maxlength="7">
                                                </div>
                                            </div>
                                            <div class="color-item">
                                                <label>Success Button Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonSuccessText" value="#FFFFFF">
                                                    <input type="text" id="buttonSuccessTextHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="color-item" style="margin-top: 1rem;">
                                            <label>Unsaved Changes Glow</label>
                                            <div class="color-input-group">
                                                <input type="color" id="unsavedChangesGlowColor" value="#C99700">
                                                <input type="text" id="unsavedChangesGlowColorHex" value="#C99700" maxlength="7">
                                            </div>
                                            <small>Save button glow animation</small>
                                        </div>

                                        <div class="color-preview-bar">
                                            <button class="btn btn-primary" style="pointer-events: none;">Primary</button>
                                            <button class="btn btn-secondary" style="pointer-events: none;">Secondary</button>
                                            <button class="btn btn-danger" style="pointer-events: none;">Danger</button>
                                            <button class="btn btn-success" style="pointer-events: none;">Success</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Role Badge Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Role Badge Colors
                                        <span class="color-section-badge">14</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <!-- Staff -->
                                        <div class="role-badge-section">
                                            <?php
                                            $staffBg = SiteSettings::get('role_staff_bg', '#e0e7ff');
                                            $staffText = SiteSettings::get('role_staff_text', '#3730a3');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="staffBadgePreview" style="background: <?php echo htmlspecialchars($staffBg); ?>; color: <?php echo htmlspecialchars($staffText); ?>;">Staff</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleStaffBg" value="<?php echo htmlspecialchars($staffBg); ?>">
                                                        <input type="text" id="roleStaffBgHex" value="<?php echo htmlspecialchars($staffBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleStaffText" value="<?php echo htmlspecialchars($staffText); ?>">
                                                        <input type="text" id="roleStaffTextHex" value="<?php echo htmlspecialchars($staffText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Maintenance -->
                                        <div class="role-badge-section">
                                            <?php
                                            $maintenanceBg = SiteSettings::get('role_maintenance_bg', '#fef3c7');
                                            $maintenanceText = SiteSettings::get('role_maintenance_text', '#92400e');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="maintenanceBadgePreview" style="background: <?php echo htmlspecialchars($maintenanceBg); ?>; color: <?php echo htmlspecialchars($maintenanceText); ?>;">Maintenance</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleMaintenanceBg" value="<?php echo htmlspecialchars($maintenanceBg); ?>">
                                                        <input type="text" id="roleMaintenanceBgHex" value="<?php echo htmlspecialchars($maintenanceBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleMaintenanceText" value="<?php echo htmlspecialchars($maintenanceText); ?>">
                                                        <input type="text" id="roleMaintenanceTextHex" value="<?php echo htmlspecialchars($maintenanceText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Maintenance Director -->
                                        <div class="role-badge-section">
                                            <?php
                                            $directorBg = SiteSettings::get('role_maintenance_director_bg', '#fed7aa');
                                            $directorText = SiteSettings::get('role_maintenance_director_text', '#9a3412');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="directorBadgePreview" style="background: <?php echo htmlspecialchars($directorBg); ?>; color: <?php echo htmlspecialchars($directorText); ?>;">Maintenance Director</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleMaintenanceDirectorBg" value="<?php echo htmlspecialchars($directorBg); ?>">
                                                        <input type="text" id="roleMaintenanceDirectorBgHex" value="<?php echo htmlspecialchars($directorBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleMaintenanceDirectorText" value="<?php echo htmlspecialchars($directorText); ?>">
                                                        <input type="text" id="roleMaintenanceDirectorTextHex" value="<?php echo htmlspecialchars($directorText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Manager -->
                                        <div class="role-badge-section">
                                            <?php
                                            $managerBg = SiteSettings::get('role_manager_bg', '#ddd6fe');
                                            $managerText = SiteSettings::get('role_manager_text', '#5b21b6');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="managerBadgePreview" style="background: <?php echo htmlspecialchars($managerBg); ?>; color: <?php echo htmlspecialchars($managerText); ?>;">Manager</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleManagerBg" value="<?php echo htmlspecialchars($managerBg); ?>">
                                                        <input type="text" id="roleManagerBgHex" value="<?php echo htmlspecialchars($managerBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleManagerText" value="<?php echo htmlspecialchars($managerText); ?>">
                                                        <input type="text" id="roleManagerTextHex" value="<?php echo htmlspecialchars($managerText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Admin -->
                                        <div class="role-badge-section">
                                            <?php
                                            $adminBg = SiteSettings::get('role_admin_bg', '#fce7f3');
                                            $adminText = SiteSettings::get('role_admin_text', '#9f1239');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="adminBadgePreview" style="background: <?php echo htmlspecialchars($adminBg); ?>; color: <?php echo htmlspecialchars($adminText); ?>;">Admin</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleAdminBg" value="<?php echo htmlspecialchars($adminBg); ?>">
                                                        <input type="text" id="roleAdminBgHex" value="<?php echo htmlspecialchars($adminBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleAdminText" value="<?php echo htmlspecialchars($adminText); ?>">
                                                        <input type="text" id="roleAdminTextHex" value="<?php echo htmlspecialchars($adminText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Super Admin -->
                                        <div class="role-badge-section">
                                            <?php
                                            $superAdminBg = SiteSettings::get('role_super_admin_bg', '#fee2e2');
                                            $superAdminText = SiteSettings::get('role_super_admin_text', '#991b1b');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="superAdminBadgePreview" style="background: <?php echo htmlspecialchars($superAdminBg); ?>; color: <?php echo htmlspecialchars($superAdminText); ?>;">Super Admin</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleSuperAdminBg" value="<?php echo htmlspecialchars($superAdminBg); ?>">
                                                        <input type="text" id="roleSuperAdminBgHex" value="<?php echo htmlspecialchars($superAdminBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleSuperAdminText" value="<?php echo htmlspecialchars($superAdminText); ?>">
                                                        <input type="text" id="roleSuperAdminTextHex" value="<?php echo htmlspecialchars($superAdminText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Success Badge -->
                                        <div class="role-badge-section">
                                            <h4>
                                                <span class="role-badge-preview" id="successBadgePreview" style="background: #059669; color: white;">Success</span>
                                                <small style="font-weight: normal; margin-left: 0.5rem;">Used for active status, successful actions</small>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="badgeSuccessBg" value="#059669">
                                                        <input type="text" id="badgeSuccessBgHex" value="#059669" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="badgeSuccessText" value="#FFFFFF">
                                                        <input type="text" id="badgeSuccessTextHex" value="#FFFFFF" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- System Badge -->
                                        <div class="role-badge-section">
                                            <h4>
                                                <span class="role-badge-preview" id="systemBadgePreview" style="background: #6366f1; color: white;">SYSTEM</span>
                                                <small style="font-weight: normal; margin-left: 0.5rem;">System themes and automated actions</small>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="badgeSystemBg" value="#6366F1">
                                                        <input type="text" id="badgeSystemBgHex" value="#6366F1" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="badgeSystemText" value="#FFFFFF">
                                                        <input type="text" id="badgeSystemTextHex" value="#FFFFFF" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <!-- End subtab-colors -->

                        <!-- Themes Subtab -->
