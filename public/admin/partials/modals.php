<?php
/**
 * Modal Components
 * All reusable modal dialogs for the admin dashboard
 *
 * Note: This file is included in index.php, so it has access to all variables
 * defined there: $isSuperAdmin, $canManageUsers, etc.
 */

use Hub\Roles;
?>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" id="confirmYes" class="btn btn-primary">Confirm</button>
                <button type="button" id="confirmNo" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Invitation Modal -->
<?php if ($canManageUsers): ?>
<div class="modal fade" id="invitationModal" tabindex="-1" aria-labelledby="invitationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invitationModalLabel">
                    <i class="bi bi-envelope-plus"></i> Send Invitation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="invitationForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                    <div class="mb-3">
                        <label for="inviteEmail" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="inviteEmail" name="email" required placeholder="user@woodsonisd.net">
                        <div class="form-text">Must be a @woodsonisd.net email address</div>
                    </div>

                    <div class="mb-3">
                        <label for="inviteRole" class="form-label">Initial Role *</label>
                        <select class="form-select" id="inviteRole" name="role" required>
                            <option value="user">User</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="maintenance_director">Maintenance Director</option>
                            <?php if ($isSuperAdmin): ?>
                            <option value="super_admin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Send Invitation
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- User Roles Modal -->
<div class="modal fade" id="userRolesModal" tabindex="-1" aria-labelledby="userRolesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userRolesModalLabel">
                    <i class="bi bi-shield-lock"></i> Manage Global Roles
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userRolesForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" id="roleUserId" name="user_id">

                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <p id="roleUserName" class="fw-bold mb-0"></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Platform-Wide Roles</label>
                        <p class="text-muted small mb-3">
                            Select all roles this user should have. They can have multiple roles simultaneously.
                        </p>

                        <div class="roles-grid">
                            <?php
                            $allRoles = Roles::getOrdered();
                            foreach ($allRoles as $role):
                                // Only show super_admin option to super admins
                                if ($role['value'] === 'super_admin' && !$isSuperAdmin) {
                                    continue;
                                }
                            ?>
                            <label class="role-checkbox-label">
                                <input type="checkbox" name="roles[]" value="<?php echo $role['value']; ?>">
                                <div>
                                    <strong <?php echo isset($role['color']) ? 'style="color: ' . $role['color'] . ';"' : ''; ?>>
                                        <?php echo e($role['label']); ?>
                                    </strong>
                                    <div class="role-description"><?php echo e($role['description']); ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Roles
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Section Access Modal -->
<div class="modal fade" id="sectionAccessModal" tabindex="-1" aria-labelledby="sectionAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sectionAccessModalLabel">
                    <i class="bi bi-key"></i> Manage Section Access
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sectionAccessForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" id="accessUserId" name="user_id">
                    <input type="hidden" id="accessSectionSlug" name="section_slug">

                    <div class="mb-3">
                        <label id="accessUserLabel" class="form-label">User</label>
                        <p id="accessUserName" class="fw-bold mb-0"></p>
                    </div>

                    <div class="mb-3">
                        <label id="accessSectionLabel" class="form-label">Section</label>
                        <p id="accessSectionName" class="fw-bold mb-0"></p>
                    </div>

                    <div class="mb-3">
                        <label for="accessRole" class="form-label">Role in this Section *</label>
                        <select class="form-select" id="accessRole" name="role" required>
                            <option value="staff">Staff</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="maintenance_director">Maintenance Director</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                        <div class="form-text">Staff has basic access, higher roles can manage data and settings.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Grant Access
                    </button>
                    <button type="button" id="revokeAccessBtn" class="btn btn-danger" style="display: none;">
                        <i class="bi bi-trash"></i> Revoke Access
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Section Management Modal (Super Admin Only) -->
<?php if ($isSuperAdmin): ?>
<div class="modal fade" id="sectionModal" tabindex="-1" aria-labelledby="sectionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-wide">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sectionModalTitle">
                    <i class="bi bi-plus-circle"></i> Add New Section
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sectionForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">"
                    <input type="hidden" id="sectionId" name="id">

                    <div class="mb-3">
                        <label for="sectionDisplayName" class="form-label">Display Name *</label>
                        <input type="text" class="form-control" id="sectionDisplayName" name="display_name" required placeholder="e.g., Travel Request">
                        <div class="form-text">The name users will see</div>
                    </div>

                    <div class="mb-3" style="display: none;">
                        <label for="sectionName" class="form-label">Section Slug *</label>
                        <input type="text" class="form-control" id="sectionName" name="name" required placeholder="e.g., travel-request">
                        <div class="form-text">Auto-generated from Display Name (lowercase, hyphens only)</div>
                    </div>

                    <div class="mb-3">
                        <label for="sectionIcon" class="form-label">Icon *</label>
                        <select class="form-select" id="sectionIcon" name="icon" required style="font-size: 1.2rem;">
                            <!-- Buildings & Facilities -->
                            <option value="🏢">🏢 Building/Office</option>
                            <option value="🏫">🏫 School</option>
                            <option value="🏛️">🏛️ Institution/Government</option>
                            <option value="🏗️">🏗️ Construction</option>
                            <option value="🏭">🏭 Factory/Industrial</option>

                            <!-- Transportation -->
                            <option value="🚗">🚗 Car</option>
                            <option value="🚌">🚌 Bus</option>
                            <option value="🚐">🚐 Van</option>
                            <option value="✈️">✈️ Airplane/Travel</option>
                            <option value="🚛">🚛 Truck/Delivery</option>

                            <!-- Tools & Maintenance -->
                            <option value="🔧">🔧 Wrench/Repair</option>
                            <option value="⚙️">⚙️ Settings/Gear</option>
                            <option value="🔨">🔨 Hammer/Tools</option>
                            <option value="🛠️">🛠️ Tools</option>
                            <option value="⚡">⚡ Electric/Power</option>

                            <!-- Documents & Forms -->
                            <option value="📋">📋 Clipboard/Forms</option>
                            <option value="📝">📝 Document/Writing</option>
                            <option value="📄">📄 Page/Document</option>
                            <option value="📃">📃 Paper/Form</option>
                            <option value="🗂️">🗂️ File Organizer</option>
                            <option value="📁">📁 Folder</option>
                            <option value="📂">📂 Open Folder</option>

                            <!-- Data & Analytics -->
                            <option value="📊">📊 Bar Chart/Reports</option>
                            <option value="📈">📈 Trending Up</option>
                            <option value="📉">📉 Trending Down</option>
                            <option value="💹">💹 Chart/Finance</option>

                            <!-- Calendar & Time -->
                            <option value="📅">📅 Calendar</option>
                            <option value="📆">📆 Calendar Page</option>
                            <option value="⏰">⏰ Alarm Clock</option>
                            <option value="⏱️">⏱️ Stopwatch/Timer</option>
                            <option value="🕐">🕐 Clock</option>

                            <!-- Business & Finance -->
                            <option value="💼">💼 Briefcase/Business</option>
                            <option value="💰">💰 Money Bag</option>
                            <option value="💵">💵 Dollar Bill</option>
                            <option value="💳">💳 Credit Card</option>
                            <option value="🏦">🏦 Bank</option>
                            <option value="💸">💸 Money Transfer</option>

                            <!-- Education -->
                            <option value="📚">📚 Books/Library</option>
                            <option value="📖">📖 Open Book</option>
                            <option value="🎓">🎓 Graduation/Education</option>
                            <option value="✏️">✏️ Pencil</option>
                            <option value="🖊️">🖊️ Pen</option>
                            <option value="📐">📐 Ruler/Math</option>
                            <option value="🎒">🎒 Backpack</option>

                            <!-- People & Communication -->
                            <option value="👥">👥 People/Users</option>
                            <option value="👤">👤 Person/User</option>
                            <option value="👨‍🏫">👨‍🏫 Teacher</option>
                            <option value="👨‍💼">👨‍💼 Professional</option>
                            <option value="💬">💬 Chat/Message</option>
                            <option value="📞">📞 Phone/Contact</option>
                            <option value="📧">📧 Email</option>
                            <option value="✉️">✉️ Envelope/Mail</option>

                            <!-- Security & Safety -->
                            <option value="🔐">🔐 Locked/Security</option>
                            <option value="🔒">🔒 Lock</option>
                            <option value="🔓">🔓 Unlocked</option>
                            <option value="🛡️">🛡️ Shield/Protection</option>
                            <option value="⚠️">⚠️ Warning</option>
                            <option value="🚨">🚨 Alert/Emergency</option>

                            <!-- Food & Nutrition -->
                            <option value="🍽️">🍽️ Dining/Cafeteria</option>
                            <option value="🍎">🍎 Apple/Food</option>
                            <option value="🥗">🥗 Salad/Healthy</option>
                            <option value="☕">☕ Coffee/Break</option>

                            <!-- Sports & Activities -->
                            <option value="🏃">🏃 Running/Athletics</option>
                            <option value="⚽">⚽ Soccer</option>
                            <option value="🏀">🏀 Basketball</option>
                            <option value="🏈">🏈 Football</option>
                            <option value="🎯">🎯 Target/Goal</option>
                            <option value="🏆">🏆 Trophy/Achievement</option>

                            <!-- Technology & IT -->
                            <option value="🖥️">🖥️ Computer/Desktop</option>
                            <option value="💻">💻 Laptop</option>
                            <option value="⌨️">⌨️ Keyboard</option>
                            <option value="🖱️">🖱️ Mouse</option>
                            <option value="🖨️">🖨️ Printer</option>
                            <option value="🌐">🌐 Web/Internet</option>
                            <option value="📱">📱 Mobile/Phone</option>
                            <option value="💾">💾 Save/Storage</option>
                            <option value="🔌">🔌 Plugin/Electric</option>

                            <!-- Creative & Design -->
                            <option value="🎨">🎨 Art/Design</option>
                            <option value="🖼️">🖼️ Picture/Frame</option>
                            <option value="🎭">🎭 Theater/Drama</option>
                            <option value="🎵">🎵 Music</option>
                            <option value="🎬">🎬 Film/Video</option>

                            <!-- Logistics & Inventory -->
                            <option value="📦">📦 Package/Inventory</option>
                            <option value="📮">📮 Mailbox</option>
                            <option value="🏷️">🏷️ Tag/Label</option>
                            <option value="🛒">🛒 Shopping Cart</option>

                            <!-- Notifications & Alerts -->
                            <option value="🔔">🔔 Bell/Notifications</option>
                            <option value="📢">📢 Announcement</option>
                            <option value="📣">📣 Megaphone</option>
                            <option value="🔊">🔊 Volume/Audio</option>

                            <!-- Medical & Health -->
                            <option value="⚕️">⚕️ Medical/Health</option>
                            <option value="🏥">🏥 Hospital</option>
                            <option value="💊">💊 Medicine/Pill</option>
                            <option value="🩺">🩺 Stethoscope</option>
                            <option value="❤️">❤️ Heart/Health</option>

                            <!-- Science & Research -->
                            <option value="🔬">🔬 Microscope/Science</option>
                            <option value="🧪">🧪 Test Tube/Lab</option>
                            <option value="🔭">🔭 Telescope</option>
                            <option value="⚗️">⚗️ Chemistry</option>

                            <!-- Weather & Environment -->
                            <option value="🌍">🌍 Earth/Global</option>
                            <option value="🌳">🌳 Tree/Environment</option>
                            <option value="♻️">♻️ Recycle</option>
                            <option value="☀️">☀️ Sun/Weather</option>

                            <!-- Misc Useful -->
                            <option value="⭐">⭐ Star/Featured</option>
                            <option value="🎁">🎁 Gift/Reward</option>
                            <option value="🔑">🔑 Key/Access</option>
                            <option value="🏠">🏠 Home</option>
                            <option value="📍">📍 Location/Pin</option>
                            <option value="🎯">🎯 Target/Goal</option>
                            <option value="✅">✅ Checkmark/Complete</option>
                            <option value="❌">❌ X/Cancel</option>
                            <option value="ℹ️">ℹ️ Info/Information</option>
                            <option value="❓">❓ Question/Help</option>
                            <option value="💡">💡 Lightbulb/Idea</option>
                        </select>
                        <div class="form-text">Choose an icon that represents this section</div>
                    </div>

                    <div class="mb-3">
                        <label for="sectionDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="sectionDescription" name="description" rows="3" placeholder="Brief description of this section's purpose"></textarea>
                    </div>

                    <div class="mb-3" style="display: none;">
                        <label for="sectionBaseUrl" class="form-label">Base URL *</label>
                        <input type="text" class="form-control" id="sectionBaseUrl" name="base_url" required placeholder="/modules/section-name/">
                        <div class="form-text">Path to section's index.php (e.g., /modules/facilities-management/)</div>
                    </div>

                    <div class="mb-3">
                        <label for="sectionSortOrder" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="sectionSortOrder" name="sort_order" value="0" min="0">
                        <div class="form-text">Lower numbers appear first</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="sectionIsActive" name="is_active" value="1" checked>
                        <label class="form-check-label" for="sectionIsActive">
                            <strong>Active</strong> - Visible to users with access
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Section
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Google Groups Mapping Modal -->
<div class="modal fade" id="googleGroupModal" tabindex="-1" aria-labelledby="googleGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="googleGroupModalLabel">
                    <i class="bi bi-google"></i> Add Google Group Mapping
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="googleGroupForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="googleGroupEmail" class="form-label">Google Group Email *</label>
                        <?php
                        // Get primary domain from ALLOWED_DOMAINS
                        $allowedDomains = $_ENV['ALLOWED_DOMAINS'] ?? 'yourdomain.com';
                        $domains = array_map('trim', explode(',', $allowedDomains));
                        $primaryDomain = $domains[0] ?? 'yourdomain.com';
                        ?>
                        <input type="text" class="form-control" id="googleGroupEmail" placeholder="group@<?php echo htmlspecialchars($primaryDomain); ?> or seniors*@<?php echo htmlspecialchars($primaryDomain); ?>" required>
                        <div class="form-text">Full group email address. Use * as wildcard (e.g., seniors*@<?php echo htmlspecialchars($primaryDomain); ?> matches seniors2024@<?php echo htmlspecialchars($primaryDomain); ?>, seniors2025@<?php echo htmlspecialchars($primaryDomain); ?>, etc.)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Assign Roles * (select one or more)</label>
                        <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                            <?php
                            $allRoles = Roles::getOrdered(); // Gets roles in hierarchy order
                            foreach ($allRoles as $role):
                                // Only show super_admin to super admins
                                if ($role['value'] === 'super_admin' && !$isSuperAdmin) {
                                    continue;
                                }
                            ?>
                            <label class="role-checkbox-label d-block mb-2">
                                <input type="checkbox" name="googleGroupRoles" value="<?php echo $role['value']; ?>">
                                <div class="d-inline-block ms-2">
                                    <strong<?php echo isset($role['color']) ? ' style="color: ' . $role['color'] . ';"' : ''; ?>>
                                        <?php echo htmlspecialchars($role['label']); ?>
                                    </strong>
                                    <div class="role-description small text-muted"><?php echo htmlspecialchars($role['description']); ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Mapping
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Microsoft Groups Mapping Modal -->
<div class="modal fade" id="microsoftGroupModal" tabindex="-1" aria-labelledby="microsoftGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="microsoftGroupModalLabel">
                    <i class="bi bi-microsoft"></i> Add Microsoft Group Mapping
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="microsoftGroupForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="microsoftGroupId" class="form-label">Azure AD Group ID *</label>
                        <input type="text" class="form-control" id="microsoftGroupId" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required>
                        <div class="form-text">The Azure AD Group Object ID from Azure Portal → Groups. Note: Use Group ID, not the display name.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Assign Roles * (select one or more)</label>
                        <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                            <?php
                            $allRoles = Roles::getOrdered();
                            foreach ($allRoles as $role):
                                if ($role['value'] === 'super_admin' && !$isSuperAdmin) {
                                    continue;
                                }
                            ?>
                            <label class="role-checkbox-label d-block mb-2">
                                <input type="checkbox" name="microsoftGroupRoles" value="<?php echo $role['value']; ?>">
                                <div class="d-inline-block ms-2">
                                    <strong<?php echo isset($role['color']) ? ' style="color: ' . $role['color'] . ';"' : ''; ?>>
                                        <?php echo htmlspecialchars($role['label']); ?>
                                    </strong>
                                    <div class="role-description small text-muted"><?php echo htmlspecialchars($role['description']); ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Mapping
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================================
     REUSABLE DYNAMIC CONTENT MODALS
     These modals are populated dynamically via JavaScript
     ============================================================================ -->

<!-- Package Validation Report Modal -->
<div class="modal micromodal-slide" id="packageValidationModal" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
        <div class="modal__container modal-wide" role="dialog" aria-modal="true" aria-labelledby="packageValidationModalLabel">
            <header class="modal__header">
                <h5 class="modal-title" id="packageValidationModalLabel">
                    <i class="bi bi-clipboard-check"></i> Package Validation Report
                </h5>
                <button class="modal__close" aria-label="Close modal" data-micromodal-close title="Close (ESC)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </header>
            <main class="modal__content" id="packageValidationModalBody">
                <!-- Content populated by JavaScript -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading validation report...</p>
                </div>
            </main>
            <footer class="modal__footer" id="packageValidationModalFooter">
                <button type="button" class="btn btn-secondary" data-micromodal-close>
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </footer>
        </div>
    </div>
</div>

<!-- Generic Large Content Modal (for dynamic content) -->
<div class="modal fade" id="dynamicContentModal" tabindex="-1" aria-labelledby="dynamicContentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 90vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dynamicContentModalLabel">Modal Title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="dynamicContentModalBody">
                <!-- Content populated by JavaScript -->
            </div>
            <div class="modal-footer" id="dynamicContentModalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Package Discovery Modal (Browse Repository) -->
<div class="modal micromodal-slide" id="packageDiscoveryModal" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
        <div class="modal__container modal-wide" role="dialog" aria-modal="true" aria-labelledby="packageDiscoveryModalLabel">
            <header class="modal__header">
                <h5 class="modal-title" id="packageDiscoveryModalLabel">
                    <i class="bi bi-box-seam text-primary"></i> Browse Available Packages
                </h5>
                <button class="modal__close" aria-label="Close modal" data-micromodal-close title="Close (ESC)">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </header>
            <main class="modal__content" id="packageDiscoveryModalBody">
                <!-- Content populated by JavaScript -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading package repository...</p>
                </div>
            </main>
            <footer class="modal__footer" id="packageDiscoveryModalFooter">
                <button type="button" class="btn btn-secondary" data-micromodal-close>
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </footer>
        </div>
    </div>
</div>
