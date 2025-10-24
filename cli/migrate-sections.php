#!/usr/bin/env php
<?php
/**
 * Multi-Section Architecture Migration
 * 
 * This migration:
 * 1. Creates sections table and section_access table
 * 2. Adds new roles (maintenance, maintenance_director) to system
 * 3. Migrates existing users to have access to fuel-travel section
 * 4. Preserves all existing data and permissions
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

echo "🚀 Multi-Section Architecture Migration\n";
echo "========================================\n\n";

$db = Database::getInstance();

try {
    // Read and execute the sections schema
    echo "📋 Creating sections table...\n";
    
    // Create sections table
    $db->execute("CREATE TABLE IF NOT EXISTS sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        slug VARCHAR(50) NOT NULL UNIQUE,
        display_name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(10) DEFAULT '📁',
        base_url VARCHAR(255) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_slug (slug),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Sections table created\n";

    echo "📋 Creating section_access table...\n";
    $db->execute("CREATE TABLE IF NOT EXISTS section_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        section_id INT NOT NULL,
        role ENUM('staff', 'maintenance', 'maintenance_director', 'manager', 'admin', 'super_admin') NOT NULL DEFAULT 'staff',
        granted_by INT,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_section (user_id, section_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_section (user_id, section_id),
        INDEX idx_section (section_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Section access table created\n";

    echo "📋 Creating section_access_view...\n";
    $db->execute("CREATE OR REPLACE VIEW section_access_view AS
        SELECT 
            sa.id,
            sa.user_id,
            u.email as user_email,
            u.name as user_name,
            sa.section_id,
            s.name as section_name,
            s.slug as section_slug,
            s.display_name as section_display_name,
            s.icon as section_icon,
            s.base_url as section_base_url,
            s.is_active as section_is_active,
            sa.role,
            sa.granted_by,
            gb.name as granted_by_name,
            sa.granted_at,
            sa.updated_at
        FROM section_access sa
        JOIN users u ON sa.user_id = u.id
        JOIN sections s ON sa.section_id = s.id
        LEFT JOIN users gb ON sa.granted_by = gb.id");
    echo "✅ Section access view created\n\n";

    echo "📋 Inserting initial sections...\n";
    $db->execute("INSERT INTO sections (name, slug, display_name, description, icon, base_url, sort_order) VALUES
        ('fuel-travel', 'fuel-travel', 'Maintenance Fuel & Travel', 'Track fuel consumption, mileage, and travel purposes for district vehicles', '🚗', '/modules/fuel-travel/', 1),
        ('vehicle-maintenance', 'vehicle-maintenance', 'Vehicle Maintenance', 'Schedule and track vehicle maintenance, repairs, and inspections', '🔧', '/modules/vehicle-maintenance/', 2),
        ('travel-reimbursement', 'travel-reimbursement', 'Travel Reimbursement', 'Submit and manage travel reimbursement requests', '✈️', '/modules/travel-reimbursement/', 3),
        ('substitute-request', 'substitute-request', 'Substitute Request', 'Request and manage substitute staffing', '👤', '/modules/substitute-request/', 4)
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP");
    echo "✅ Initial sections inserted\n\n";

    echo "\n📊 Migrating existing users to fuel-travel section...\n";
    
    // Get all existing users
    $users = $db->fetchAll("SELECT id, email, role, name FROM users");
    echo "Found " . count($users) . " users\n";

    // Get fuel-travel section ID
    $fuelTravelSection = $db->fetchOne("SELECT id FROM sections WHERE slug = 'fuel-travel'");
    
    if (!$fuelTravelSection) {
        throw new \Exception("fuel-travel section not found in database!");
    }

    $sectionId = $fuelTravelSection['id'];
    $migratedCount = 0;

    foreach ($users as $user) {
        // Check if user already has access
        $existing = $db->fetchOne(
            "SELECT id FROM section_access WHERE user_id = ? AND section_id = ?",
            [$user['id'], $sectionId]
        );

        if (!$existing) {
            // Grant access with user's current role
            $db->execute(
                "INSERT INTO section_access (user_id, section_id, role, granted_by) VALUES (?, ?, ?, NULL)",
                [$user['id'], $sectionId, $user['role']]
            );
            echo "✅ Granted {$user['role']} access to {$user['name']} ({$user['email']})\n";
            $migratedCount++;
        } else {
            echo "ℹ️  {$user['name']} already has access\n";
        }
    }

    echo "\n✅ Migration completed successfully!\n";
    echo "📊 Migrated $migratedCount users to fuel-travel section\n\n";

    // Show summary
    echo "📋 Section Summary:\n";
    $sections = $db->fetchAll("SELECT * FROM sections ORDER BY sort_order");
    foreach ($sections as $section) {
        $accessCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM section_access WHERE section_id = ?",
            [$section['id']]
        )['count'];
        
        echo "  {$section['icon']} {$section['display_name']}: $accessCount users\n";
    }

    echo "\n✨ Ready to use the new multi-section platform!\n";
    echo "\n📝 Next steps:\n";
    echo "  1. Update Auth class to use section-based permissions\n";
    echo "  2. Create /public/sections.php landing page\n";
    echo "  3. Move existing code into /public/modules/fuel-travel/\n";
    echo "  4. Test section access for all user roles\n\n";

} catch (\Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
