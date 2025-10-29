<?php
/**
 * User Profile Page
 * Allows users to update their contact preferences and personal information
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Auth;
use Hub\Layout;
use Hub\Database;

Auth::requireLogin();

$user = Auth::getCurrentUser();
$userRole = Auth::getUserRole();

// Get current tab from query string
$activeTab = $_GET['tab'] ?? 'profile';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php Layout::renderHead('My Profile - ' . $user['name'], 'hub'); ?>
    <style>
        .profile-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin-bottom: 20px;
        }
        
        .profile-tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
        }
        
        .profile-tab {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .profile-tab.active {
            color: var(--primary-color, #2196f3);
            border-bottom-color: var(--primary-color, #2196f3);
        }
        
        .profile-tab:hover {
            color: var(--primary-color, #2196f3);
        }
        
        .profile-section {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .profile-section.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .btn-save {
            background: var(--primary-color, #2196f3);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }
        
        .info-badge {
            display: inline-block;
            padding: 6px 12px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php Layout::renderHeader($user, $userRole, 'hub'); ?>
    
    <div class="profile-container">
        <div class="profile-header">
            <?php if (!empty($user['picture'])): ?>
                <img src="<?php echo e($user['picture']); ?>" alt="<?php echo e($user['name']); ?>" class="profile-avatar-large">
            <?php else: ?>
                <img src="/assets/images/default-avatar.svg" alt="Default Avatar" class="profile-avatar-large">
            <?php endif; ?>
            <h1><?php echo e($user['name']); ?></h1>
            <p><?php echo e($user['email']); ?></p>
            <span class="info-badge"><?php echo ucfirst(str_replace('_', ' ', $userRole)); ?></span>
        </div>
        
        <div class="profile-tabs">
            <button class="profile-tab <?php echo $activeTab === 'profile' ? 'active' : ''; ?>" onclick="switchTab('profile')">
                Profile Information
            </button>
            <button class="profile-tab <?php echo $activeTab === 'contact' ? 'active' : ''; ?>" onclick="switchTab('contact')">
                Contact Preferences
            </button>
        </div>
        
        <!-- Profile Information Tab -->
        <div id="profile-section" class="profile-section <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
            <h2>Profile Information</h2>
            <p>Your profile information is managed through Google Workspace.</p>
            
            <div class="form-group">
                <label>Name</label>
                <input type="text" value="<?php echo e($user['name']); ?>" disabled>
                <div class="help-text">Managed by Google Workspace</div>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?php echo e($user['email']); ?>" disabled>
                <div class="help-text">Managed by Google Workspace</div>
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $userRole)); ?>" disabled>
                <div class="help-text">Contact an administrator to change your role</div>
            </div>
        </div>
        
        <!-- Contact Preferences Tab -->
        <div id="contact-section" class="profile-section <?php echo $activeTab === 'contact' ? 'active' : ''; ?>">
            <h2>Contact Preferences</h2>
            <p>Manage how you receive notifications from The Hub.</p>
            
            <form id="contactForm" method="POST" action="/api/profile.php">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="update_contact">
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo e($user['phone'] ?? ''); ?>"
                           placeholder="(555) 123-4567">
                    <div class="help-text">Used for SMS notifications (when enabled)</div>
                </div>
                
                <div class="form-group">
                    <label for="alt_email">Alternative Email</label>
                    <input type="email" id="alt_email" name="alt_email" 
                           value="<?php echo e($user['alt_email'] ?? ''); ?>"
                           placeholder="alternate@example.com">
                    <div class="help-text">Optional secondary email address</div>
                </div>
                
                <div class="form-group">
                    <label for="preferred_contact_method">Preferred Contact Method</label>
                    <select id="preferred_contact_method" name="preferred_contact_method">
                        <option value="email" <?php echo ($user['preferred_contact_method'] ?? 'email') === 'email' ? 'selected' : ''; ?>>
                            Primary Email Only
                        </option>
                        <option value="alt_email" <?php echo ($user['preferred_contact_method'] ?? '') === 'alt_email' ? 'selected' : ''; ?>>
                            Alternative Email
                        </option>
                        <option value="sms" <?php echo ($user['preferred_contact_method'] ?? '') === 'sms' ? 'selected' : ''; ?>>
                            SMS/Text Message
                        </option>
                        <option value="email_only" <?php echo ($user['preferred_contact_method'] ?? '') === 'email_only' ? 'selected' : ''; ?>>
                            Email Only (No SMS)
                        </option>
                        <option value="sms_only" <?php echo ($user['preferred_contact_method'] ?? '') === 'sms_only' ? 'selected' : ''; ?>>
                            SMS Only (No Email)
                        </option>
                    </select>
                    <div class="help-text">How you'd like to receive notifications</div>
                </div>
                
                <button type="submit" class="btn-save">💾 Save Changes</button>
            </form>
        </div>
    </div>
    
    <?php Layout::renderFooter($user, 'hub'); ?>
    
    <script>
        function switchTab(tabName) {
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
            
            // Update tabs
            document.querySelectorAll('.profile-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.profile-section').forEach(section => {
                section.classList.remove('active');
            });
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-section').classList.add('active');
        }
        
        // Form submission
        document.getElementById('contactForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            
            try {
                const response = await fetch('/api/profile.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Contact preferences updated successfully!');
                } else {
                    alert('❌ Error: ' + (result.error || 'Failed to update preferences'));
                }
            } catch (error) {
                console.error('Update error:', error);
                alert('❌ Network error. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '💾 Save Changes';
            }
        });
    </script>
</body>
</html>
