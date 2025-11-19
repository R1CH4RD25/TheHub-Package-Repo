                        <div id="subtab-sidebar" class="user-subtab">
                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Click sections to expand and customize sidebar and menu settings.
                            </p>

                            <!-- Active Menu Item Styling -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Active Menu Item Styling
                                        <span class="color-section-badge">2</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group">
                                        <label for="activeMenuFontSize">Active Menu Item Font Size</label>
                                        <input type="number" id="activeMenuFontSize" value="16" min="12" max="24" placeholder="16">
                                        <small>Font size for active/selected menu items (12-24px)</small>
                                    </div>

                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="activeMenuBold">
                                            <strong>Bold Active Menu Items</strong>
                                        </label>
                                        <small>Make active menu item text bold</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sidebar Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Sidebar Colors
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group">
                                        <label for="sidebarBg">Sidebar Background Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarBg" value="#FFFFFF">
                                            <input type="text" id="sidebarBgHex" value="#FFFFFF" placeholder="#FFFFFF">
                                        </div>
                                        <small>Background color of the admin sidebar</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="sidebarTextColor">Sidebar Text Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarTextColor" value="#374151">
                                            <input type="text" id="sidebarTextColorHex" value="#374151" placeholder="#374151">
                                        </div>
                                        <small>Text color for regular menu items</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="sidebarActiveHighlight">Active Item Background</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarActiveHighlight" value="#FEF3C7">
                                            <input type="text" id="sidebarActiveHighlightHex" value="#FEF3C7" placeholder="#FEF3C7">
                                        </div>
                                        <small>Background color for active/selected menu item</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="sidebarActiveTextColor">Active Item Text Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarActiveTextColor" value="#C99700">
                                            <input type="text" id="sidebarActiveTextColorHex" value="#C99700" placeholder="#C99700">
                                        </div>
                                        <small>Text color for active menu item</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="sidebarHoverBg">Hover Background Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarHoverBg" value="#F9FAFB">
                                            <input type="text" id="sidebarHoverBgHex" value="#F9FAFB" placeholder="#F9FAFB">
                                        </div>
                                        <small>Background color when hovering over menu items</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Branding & Images Subtab -->
