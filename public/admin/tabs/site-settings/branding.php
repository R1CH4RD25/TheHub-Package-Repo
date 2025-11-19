                        <div id="subtab-branding" class="user-subtab">
                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Click sections to expand and customize branding elements.
                            </p>

                            <!-- Site Identity -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Site Identity
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group">
                                        <label for="organizationName">Organization Name</label>
                                        <input type="text" id="organizationName" value="Your Organization" placeholder="Your Organization">
                                        <small>Your school/district name - displayed in navbar, footer, and login page</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="siteName">Site Name</label>
                                        <input type="text" id="siteName" value="The Hub" placeholder="The Hub">
                                        <small>Displayed in browser tabs and page headers</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="welcomeMessage">Welcome Message</label>
                                        <input type="text" id="welcomeMessage" value="Central hub for student services and resources" placeholder="Central hub for student services and resources">
                                        <small>Displayed on the hub landing page</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="landingPageTitle">Landing Page Title</label>
                                        <input type="text" id="landingPageTitle" value="The Hub" placeholder="The Hub">
                                        <small>Main title displayed on hub landing page</small>
                                    </div>

                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0; cursor: pointer;">
                                            <span>Show Hub Title Icon in Header</span>
                                            <input type="checkbox" id="landingPageShowIcon" checked>
                                        </label>
                                        <small>Display the uploaded Hub Title Icon above the landing page title</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Logo -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Logo
                                        <span class="color-section-badge">4</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <div class="logo-preview-container" style="margin-bottom: 1rem;">
                                            <img id="currentLogoPreview" src="/assets/images/branding/Branding_NoBG.png" alt="Current Logo" style="max-height: 100px; max-width: 300px; background: #f3f4f6; padding: 1rem; border-radius: 8px;">
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                            <input type="file" id="logoUpload" accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" style="display: none;">
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('logoUpload').click();">Upload New Logo</button>
                                            <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0; cursor: pointer;">
                                                <span>Enable Logo Glow Effect</span>
                                                <input type="checkbox" id="logoGlowEnabled" checked>
                                            </label>
                                        </div>
                                        <small style="display: block; margin-top: 0.5rem;">Accepts PNG, JPG, SVG, WebP. Transparent background recommended.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="logoGlowColor">Logo Glow Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="logoGlowColor" value="#FFD700">
                                            <input type="text" id="logoGlowColorHex" value="#FFD700" placeholder="#FFD700">
                                        </div>
                                        <small>Color for the logo glow effect when enabled</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="logoHeight">Logo Height (Desktop)</label>
                                        <input type="number" id="logoHeight" value="90" min="40" max="150" placeholder="90">
                                        <small>Logo height in pixels for desktop (40-150px)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="logoHeightMobile">Logo Height (Mobile)</label>
                                        <input type="number" id="logoHeightMobile" value="50" min="30" max="100" placeholder="50">
                                        <small>Logo height in pixels for mobile (30-100px)</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Favicon -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Favicon
                                        <span class="color-section-badge">1</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <div class="logo-preview-container" style="margin-bottom: 1rem;">
                                            <img id="currentFaviconPreview" src="/assets/images/Cowboy_SM_favicon.png" alt="Current Favicon" style="max-height: 48px; max-width: 48px; background: #f3f4f6; padding: 0.5rem; border-radius: 8px;">
                                        </div>
                                        <div>
                                            <input type="file" id="faviconUpload" accept="image/png,image/x-icon,image/vnd.microsoft.icon" style="display: none;">
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('faviconUpload').click();">Upload New Favicon</button>
                                            <small style="display: block; margin-top: 0.5rem;">Accepts PNG, ICO. Recommended size: 32x32 or 48x48 pixels.</small>
                                        </div>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hub Title Icon -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Hub Title Icon
                                        <span class="color-section-badge">2</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <div class="logo-preview-container" style="margin-bottom: 1rem;">
                                            <img id="currentHubTileIconPreview" src="/assets/images/branding/Branding_NoBG.png" alt="Current Hub Title Icon" style="max-height: 80px; max-width: 80px; background: #f3f4f6; padding: 0.5rem; border-radius: 8px;">
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
                                            <input type="file" id="hubTileIconUpload" accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" style="display: none;">
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('hubTileIconUpload').click();">Upload Custom Icon</button>
                                            <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0; cursor: pointer;">
                                                <span>Use Custom Icon</span>
                                                <input type="checkbox" id="hubTileIconCustomEnabled">
                                            </label>
                                        </div>
                                        <small style="display: block;">When enabled, this icon will appear in the landing page header (if enabled above) AND on all section tiles. Accepts PNG, JPG, SVG, WebP. Recommended size: 120x120 pixels.</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Colors & Theme Subtab - COMPACT VERSION -->
