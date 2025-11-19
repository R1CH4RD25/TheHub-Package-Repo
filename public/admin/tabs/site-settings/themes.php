                        <div id="subtab-themes" class="user-subtab">
                            <div class="settings-section">
                                <h2>Theme Management</h2>
                                <p style="color: #6B7280; margin-bottom: 1rem;">Save current color scheme as a theme, load saved themes, or update existing themes with current settings</p>

                                <!-- Save or Update Theme -->
                                <div style="background: #F9FAFB; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; border: 2px solid #E5E7EB;">
                                    <h3 style="font-size: 1.1rem; margin-bottom: 1rem; font-weight: 600;">Save Current Color Scheme</h3>
                                    <div class="settings-grid">
                                        <div class="form-group">
                                            <label for="newThemeName">Theme Name *</label>
                                            <input type="text" id="newThemeName" placeholder="My Custom Theme">
                                        </div>
                                        <div class="form-group">
                                            <label for="newThemeDescription">Description</label>
                                            <input type="text" id="newThemeDescription" placeholder="Optional description">
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <button id="saveCurrentTheme" class="btn btn-primary">Save as New Theme</button>
                                        <button id="updateSelectedTheme" class="btn btn-secondary" style="display: none;">Update Selected Theme</button>
                                    </div>
                                    <div id="updateThemeNotice" style="display: none; margin-top: 1rem; padding: 0.75rem; background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 4px;">
                                        <strong>Update Mode:</strong> <span id="updateThemeName"></span>
                                        <button onclick="cancelUpdateMode()" style="float: right; background: none; border: none; cursor: pointer; font-weight: bold;">✕</button>
                                    </div>
                                </div>

                                <!-- Saved Themes List -->
                                <div id="themesList">
                                    <h3 style="font-size: 1.1rem; margin-bottom: 1rem; font-weight: 600;">Saved Themes</h3>
                                    <div id="themesContainer" style="display: grid; gap: 1rem;">
                                        Loading themes...
                                    </div>
                                </div>
                            </div>
                        </div><!-- end subtab-themes -->

                        <!-- Management System Subtab -->
