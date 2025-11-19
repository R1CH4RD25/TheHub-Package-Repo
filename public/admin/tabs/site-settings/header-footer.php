                            <div id="subtab-header-footer" class="user-subtab active">
                                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                    Click sections to expand and customize header and footer settings.
                                    </p>

                                <!-- Header Configuration -->
                                <div class="color-section">
                                    <div class="color-section-header" onclick="toggleColorSection(this)">
                                        <h3>
                                            Header Configuration
                                            <span class="color-section-badge">11</span>
                                        </h3>
                                        <span class="color-section-toggle collapsed">▼</span>
                                    </div>
                                    <div class="color-section-body collapsed">
                                        <div class="color-section-content">
                                            <div class="settings-grid">
                                                <div class="form-group">
                                                    <label for="headerHeight">Header Height (pixels)</label>
                                                    <input type="number" id="headerHeight" value="80" min="60" max="150" placeholder="80">
                                                    <small>Navbar height in pixels (60-150px)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label>
                                                        <input type="checkbox" id="headerMatchLogoHeight">
                                                        <strong>Auto-adjust to Logo Height</strong>
                                                    </label>
                                                    <small>Header will automatically match uploaded logo height + padding</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="navbarSubtitle">Navbar Subtitle</label>
                                                    <input type="text" id="navbarSubtitle" value="The Hub" placeholder="The Hub">
                                                    <small>Shown under organization name in navbar</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerBgColor">Header Background Color</label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" id="headerBgColor" value="#000000">
                                                        <input type="text" id="headerBgColorHex" value="#000000" placeholder="#000000">
                                                    </div>
                                                    <small>Navbar background color</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerTextColor">Header Text Color</label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" id="headerTextColor" value="#FFFFFF">
                                                        <input type="text" id="headerTextColorHex" value="#FFFFFF" placeholder="#FFFFFF">
                                                    </div>
                                                    <small>Navbar text and link color</small>
                                                            </div>

                                                <div class="form-group">
                                                    <label for="headerSubtitleColor">Header Subtitle Color</label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" id="headerSubtitleColor" value="#FFD700">
                                                        <input type="text" id="headerSubtitleColorHex" value="#FFD700" placeholder="#FFD700">
                                                    </div>
                                                    <small>Color for the subtitle under organization name</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerTitleFont">Header Title Font</label>
                                                    <select id="headerTitleFont">
                                                        <option value="Roboto">Roboto</option>
                                                        <option value="Open Sans">Open Sans</option>
                                                        <option value="Lato">Lato</option>
                                                        <option value="Montserrat">Montserrat</option>
                                                        <option value="Raleway">Raleway</option>
                                                        <option value="Poppins">Poppins</option>
                                                        <option value="Oswald">Oswald</option>
                                                        <option value="Merriweather">Merriweather</option>
                                                        <option value="Playfair Display">Playfair Display</option>
                                                        <option value="Ubuntu">Ubuntu</option>
                                                    </select>
                                                    <small>Google Font for organization name</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerSubtitleFont">Header Subtitle Font</label>
                                                    <select id="headerSubtitleFont">
                                                        <option value="Roboto">Roboto</option>
                                                        <option value="Open Sans">Open Sans</option>
                                                        <option value="Lato">Lato</option>
                                                        <option value="Montserrat">Montserrat</option>
                                                        <option value="Raleway">Raleway</option>
                                                        <option value="Poppins">Poppins</option>
                                                        <option value="Oswald">Oswald</option>
                                                        <option value="Merriweather">Merriweather</option>
                                                        <option value="Playfair Display">Playfair Display</option>
                                                        <option value="Ubuntu">Ubuntu</option>
                                                    </select>
                                                    <small>Google Font for subtitle text</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerTitleFontSize">Header Title Font Size (rem)</label>
                                                    <input type="number" id="headerTitleFontSize" value="1.3" min="0.5" max="3" step="0.05" placeholder="1.3">
                                                    <small>Font size for organization name (0.5-3rem)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerSubtitleFontSize">Header Subtitle Font Size (rem)</label>
                                                    <input type="number" id="headerSubtitleFontSize" value="0.85" min="0.5" max="2" step="0.05" placeholder="0.85">
                                                    <small>Font size for subtitle text (0.5-2rem)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label>
                                                        <input type="checkbox" id="headerShowSubtitle" checked>
                                                        <strong>Show Header Subtitle</strong>
                                                    </label>
                                                    <small>Display subtitle below organization name</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Configuration -->
                                <div class="color-section">
                                    <div class="color-section-header" onclick="toggleColorSection(this)">
                                        <h3>
                                            Footer Configuration
                                            <span class="color-section-badge">7</span>
                                        </h3>
                                        <span class="color-section-toggle collapsed">▼</span>
                                    </div>
                                    <div class="color-section-body collapsed">
                                        <div class="color-section-content">
                                            <div class="settings-grid">
                                                <div class="form-group">
                                                    <label for="footerHeight">Footer Height (pixels)</label>
                                                    <input type="number" id="footerHeight" value="40" min="30" max="80" placeholder="40">
                                                    <small>Footer height in pixels (30-80px)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="footerTextSize">Footer Text Size (rem)</label>
                                                    <input type="number" id="footerTextSize" value="0.875" min="0.5" max="1.5" step="0.05" placeholder="0.875">
                                                    <small>Font size for footer text (0.5-1.5rem)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label>
                                                        <input type="checkbox" id="footerShowVersion" checked>
                                                        <strong>Show Version Number</strong>
                                                    </label>
                                                    <small>Display version info in footer</small>
                                                </div>

                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="footerShowUser" checked>
                                            <strong>Show Current User</strong>
                                        </label>
                                        <small>Display logged-in username in footer</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="footerCustomText">Custom Footer Text</label>
                                        <input type="text" id="footerCustomText" value="" placeholder="Optional custom footer message">
                                        <small>Additional text to display in footer (optional)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="footerBgColor">Footer Background Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="footerBgColor" value="#F3F4F6">
                                            <input type="text" id="footerBgColorHex" value="#F3F4F6" placeholder="#F3F4F6">
                                        </div>
                                        <small>Footer background color</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="footerTextColor">Footer Text Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="footerTextColor" value="#6B7280">
                                            <input type="text" id="footerTextColorHex" value="#6B7280" placeholder="#6B7280">
                                        </div>
                                        <small>Footer text color</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar & Menu Subtab -->
